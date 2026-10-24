<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Admin;

use App\Entity\User;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Service\EditorialTimezone;
use Pushword\Newsletter\Admin\CampaignIndex;
use Pushword\Newsletter\Admin\CampaignIndexController;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Event\CampaignIndexItem;
use Pushword\Newsletter\Event\CollectCampaignIndex;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class CampaignIndexTest extends AbstractNewsletterTestCase
{
    private ?User $user = null;

    protected function tearDown(): void
    {
        if (null !== $this->user) {
            $connection = $this->entityManager->getConnection();
            $connection->delete($connection->quoteIdentifier('user'), ['id' => $this->user->id]);
        }

        parent::tearDown();
    }

    public function testCatalogueCombinesSourcesAndPreservesOwningActionsAndUnknownValues(): void
    {
        $this->login();
        $audience = $this->createAudience();
        $native = $this->createCampaign($audience, subject: 'Catalogue native');
        $native->schedule(new DateTimeImmutable('2026-11-02 12:00:00'));

        $this->entityManager->flush();
        $nativeId = (string) $native->id;
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(CollectCampaignIndex::class, static function (CollectCampaignIndex $index) use ($nativeId): void {
            $index->items['pushword:'.$nativeId]->actions['Commercial results'] = '/commercial-results';
            $index->add(new CampaignIndexItem('external', $nativeId, '<script>External</script>', scheduledAt: new DateTimeImmutable('2026-11-01 12:00:00'), actions: ['Open source' => 'https://example.tld/campaign/'.$nativeId]));
            $index->notices['external'] = 'Last synchronized yesterday. <script>Notice</script>';
        });

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/campaigns');
        self::assertResponseIsSuccessful();
        $local = $crawler->filter('[data-campaign-key="pushword:'.$nativeId.'"]');
        $remote = $crawler->filter('[data-campaign-key="external:'.$nativeId.'"]');
        self::assertCount(1, $local);
        self::assertCount(1, $remote);
        self::assertSame('/commercial-results', $local->selectLink('Commercial results')->attr('href'));
        self::assertStringContainsString('/newsletter/campaign/'.$nativeId, $local->selectLink('Review')->attr('href') ?? '');
        self::assertSame('https://example.tld/campaign/'.$nativeId, $remote->selectLink('Open source')->attr('href'));
        self::assertSame('Not provided', $remote->filter('td')->eq(1)->text());
        self::assertSame('Not provided', $remote->filter('td')->eq(3)->text());
        self::assertSame(['Source', 'Status', 'Scheduled for', 'Sent at', 'Actions'], $remote->filter('td')->extract(['data-label']));
        self::assertCount(0, $remote->filter('script'));
        self::assertStringContainsString('Last synchronized yesterday.', $crawler->filter('[role="status"]')->text());
        self::assertCount(0, $crawler->filter('[role="status"] script'));
        self::assertSame('scheduled', $this->entityManager->find(Campaign::class, $native->id)?->getStatusLabel());
        self::assertStringNotContainsString('Not provided', $local->filter('td')->eq(2)->text());
        self::assertSame('/admin/newsletter/campaigns', $crawler->filter('.pw-newsletter-nav [aria-current="page"]')->attr('href'));
        self::assertCount(1, $crawler->selectLink('Create in Pushword'));
        self::assertCount(1, $crawler->selectLink('Manage in Pushword'));
    }

    public function testFiltersUseTheWholeCatalogueAndRetainTheSelection(): void
    {
        $this->login();
        $native = $this->createCampaign($this->createAudience(), subject: 'Catalogue café');
        $nativeKey = 'pushword:'.$native->id;
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(CollectCampaignIndex::class, static function (CollectCampaignIndex $index): void {
            $index->add(new CampaignIndexItem('external', '42', 'Catalogue remote', 'sent', sentAt: new DateTimeImmutable('2026-10-20'), audience: 'Walkers'));
            $index->add(new CampaignIndexItem('external', '43', 'Catalogue draft', 'draft'));
        });

        foreach ([
            ['source=external&status=sent', ['external:42']],
            ['source=pushword&q=caf%C3%A9', [$nativeKey]],
            ['q=walkers', ['external:42']],
            ['q=Catalogue&status=draft', ['external:43', $nativeKey]],
        ] as [$query, $expected]) {
            $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/campaigns?'.$query);
            self::assertResponseIsSuccessful();
            self::assertSame($expected, $crawler->filter('[data-campaign-key]')->extract(['data-campaign-key']), $query);
            parse_str($query, $selection);
            $form = $crawler->filter('form[aria-label="Filter campaigns"]')->form();
            $values = $form->getValues();
            foreach (['source', 'status', 'q'] as $field) {
                self::assertSame($selection[$field] ?? '', $values[$field]);
            }
        }
    }

    public function testUnavailableSourceNoticeSurvivesAnEmptyFilteredCatalogue(): void
    {
        $this->login();
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(CollectCampaignIndex::class, static function (CollectCampaignIndex $index): void {
            $index->notices['external'] = 'Not synchronized. Campaigns are unavailable.';
            $index->creationActions['Create at source'] = 'https://example.tld/campaign/new';
        });
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/campaigns?source=external');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-campaign-key]'));
        self::assertStringContainsString('Campaigns are unavailable.', $crawler->filter('[role="status"]')->text());
        self::assertSame('external', $crawler->filter('[name="source"] option[selected]')->attr('value'));
        self::assertSame('https://example.tld/campaign/new', $crawler->selectLink('Create at source')->attr('href'));
    }

    public function testCatalogueRequiresAnEditor(): void
    {
        $this->client->request(Request::METHOD_GET, '/admin/newsletter/campaigns');
        self::assertResponseRedirects();
        $this->login('ROLE_USER');
        $this->client->request(Request::METHOD_GET, '/admin/newsletter/campaigns');
        self::assertResponseStatusCodeSame(403);
    }

    public function testFilteredCatalogueShowsAvailableCountsAndAnEmptyRecovery(): void
    {
        $this->login();
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(CollectCampaignIndex::class, static function (CollectCampaignIndex $index): void {
            $index->items = [];
            $index->add(new CampaignIndexItem('pushword', 'local', 'Native draft', 'draft'));
            $index->add(new CampaignIndexItem('external', 'sent', 'Remote sent', 'sent'));
            $index->add(new CampaignIndexItem('external', 'draft', 'Remote draft', 'draft'));
            $index->notices['missing'] = 'Source unavailable, no cached campaigns.';
            $index->noticeLevels['missing'] = 'danger';
        });

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/campaigns?source=external&status=sent');
        self::assertResponseIsSuccessful();
        self::assertSame('Displayed: 1 / 3 available', $crawler->filter('[data-campaign-results]')->text());
        self::assertCount(1, $crawler->filter('[data-campaign-key]'));

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/campaigns?q=not-found');
        self::assertResponseIsSuccessful();
        self::assertSame('Displayed: 0 / 3 available', $crawler->filter('[data-campaign-results]')->text());
        self::assertCount(0, $crawler->filter('.pw-catalogue table'));
        self::assertSame('No matching campaigns', $crawler->filter('[data-catalogue-empty="filtered"] h2')->text());
        self::assertSame('not-found', $crawler->filter('[name="q"]')->attr('value'));
        self::assertSame('/admin/newsletter/campaigns', $crawler->selectLink('Reset')->attr('href'));
        $crawler = $this->client->click($crawler->selectLink('Reset')->link());
        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('[data-campaign-key]'));
        self::assertSame('Displayed: 3 / 3 available', $crawler->filter('[data-campaign-results]')->text());
    }

    public function testInitialEmptyCatalogueOffersBothCreationsOnceWithoutAnEmptyTable(): void
    {
        $this->login();
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(CollectCampaignIndex::class, static function (CollectCampaignIndex $index): void {
            $index->items = [];
            $index->creationActions['Create in partner'] = 'https://example.tld/new';
            $index->notices['external'] = 'Not synchronized, campaigns are unavailable.';
            $index->noticeLevels['external'] = 'warning';
        });
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/campaigns');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.pw-catalogue table'));
        $empty = $crawler->filter('[data-catalogue-empty="initial"]');
        self::assertSame('No campaigns available', $empty->filter('h2')->text());
        self::assertCount(1, $empty->selectLink('Create in Pushword'));
        self::assertCount(1, $empty->selectLink('Create in partner'));
        self::assertCount(1, $crawler->selectLink('Create in Pushword'));
        self::assertCount(1, $crawler->selectLink('Create in partner'));
        self::assertSame('https://example.tld/new', $empty->selectLink('Create in partner')->attr('href'));
        self::assertStringContainsString('campaigns are unavailable', $crawler->filter('[role="status"]')->text());
        self::assertCount(0, $crawler->filter('.pw-catalogue-filters, [data-campaign-results]'));
    }

    /** @return iterable<string, array{'info'|'warning'|'danger'|null, string}> */
    public static function noticeLevels(): iterable
    {
        yield 'existing listener without level' => [null, 'info'];
        yield 'routine' => ['info', 'info'];
        yield 'delayed or failed refresh' => ['warning', 'warning'];
        yield 'unavailable source' => ['danger', 'danger'];
    }

    /** @param 'info'|'warning'|'danger'|null $level */
    #[DataProvider('noticeLevels')]
    public function testNoticeSeverityIsOptionalAndKeepsEscaping(?string $level, string $expected): void
    {
        $this->login();
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(CollectCampaignIndex::class, static function (CollectCampaignIndex $index) use ($level): void {
            $index->notices['external'] = 'Cache status <script>alert(1)</script>';
            if (null !== $level) {
                $index->noticeLevels['external'] = $level;
            }
        });
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/campaigns');
        self::assertResponseIsSuccessful();
        $notice = $crawler->filter('[role="status"][data-notice-level]');
        self::assertSame($expected, $notice->attr('data-notice-level'));
        self::assertStringContainsString('Cache status', $notice->text());
        self::assertCount(0, $notice->filter('script'));
    }

    public function testDatesUseEditorialTimezoneAndOpaqueKeysGiveAStableChronologicalOrder(): void
    {
        $this->login();
        $controller = new CampaignIndexController(self::getContainer()->get(CampaignIndex::class), new EditorialTimezone('Europe/Paris'));
        $controller->setContainer(self::getContainer());
        self::getContainer()->set(CampaignIndexController::class, $controller);
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(CollectCampaignIndex::class, static function (CollectCampaignIndex $index): void {
            $index->add(new CampaignIndexItem('external', 'undated', 'Chronology undated'));
            $index->add(new CampaignIndexItem('external', 'a b', 'Chronology first tie', scheduledAt: new DateTimeImmutable('2026-10-25T00:30:00Z')));
            $index->add(new CampaignIndexItem('external', 'a  b', 'Chronology second tie', scheduledAt: new DateTimeImmutable('2026-10-25T00:30:00Z')));
            $index->add(new CampaignIndexItem('external', 'sent', 'Chronology sent', sentAt: new DateTimeImmutable('2026-10-24T12:00:00Z')));
            $index->add(new CampaignIndexItem('external', 'scheduled', 'Chronology scheduled', scheduledAt: new DateTimeImmutable('2026-10-25T12:00:00Z')));
        });
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/campaigns?q=Chronology');
        self::assertResponseIsSuccessful();
        self::assertSame(['external:scheduled', 'external:a  b', 'external:a b', 'external:sent', 'external:undated'], $crawler->filter('[data-campaign-key]')->extract(['data-campaign-key']));
        self::assertStringContainsString('1:00', $crawler->filter('[data-campaign-key="external:scheduled"] td')->eq(2)->text());
        self::assertStringContainsString('2:30', $crawler->filter('[data-campaign-key="external:a b"] td')->eq(2)->text());
        self::assertStringContainsString('2:00', $crawler->filter('[data-campaign-key="external:sent"] td')->eq(3)->text());
        self::assertStringContainsString('Europe/Paris', $crawler->filter('[data-campaign-timezone]')->text());
    }

    /** @return iterable<string, array{bool}> */
    public static function unsafeActions(): iterable
    {
        yield 'creation link' => [true];
        yield 'enriched native link' => [false];
    }

    #[DataProvider('unsafeActions')]
    public function testListenerActionsAreValidatedAfterCollection(bool $creation): void
    {
        $native = $this->createCampaign($this->createAudience());
        $nativeKey = 'pushword:'.$native->id;
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(CollectCampaignIndex::class, static function (CollectCampaignIndex $index) use ($creation, $nativeKey): void {
            if ($creation) {
                $index->creationActions['Unsafe'] = 'javascript:alert(1)';
            } else {
                $index->items[$nativeKey]->actions['Unsafe'] = 'javascript:alert(1)';
            }
        });
        $this->expectException(InvalidArgumentException::class);
        self::getContainer()->get(CampaignIndex::class)->collect();
    }

    private function login(string $role = 'ROLE_EDITOR'): void
    {
        $this->user = new User();
        $this->user->email = 'catalogue-'.bin2hex(random_bytes(6)).'@example.tld';
        $this->user->setRoles([$role]);
        $this->user->locale = 'en';

        $this->entityManager->persist($this->user);
        $this->entityManager->flush();

        $this->client->loginUser($this->user);
    }
}
