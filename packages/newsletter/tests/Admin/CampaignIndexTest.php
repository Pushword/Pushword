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
use Pushword\Newsletter\Enum\CampaignStatus;
use Pushword\Newsletter\Enum\EditorialStage;
use Pushword\Newsletter\Event\CampaignIndexItem;
use Pushword\Newsletter\Event\CollectCampaignIndex;
use Pushword\Newsletter\Repository\CampaignRepository;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Link;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Group('integration')]
final class CampaignIndexTest extends AbstractNewsletterTestCase
{
    private ?User $user = null;

    protected function tearDown(): void
    {
        if ($this->user instanceof User) {
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
            $index->add(new CampaignIndexItem('external', $nativeId, '<script>External</script>', scheduledAt: new DateTimeImmutable('2026-11-01 12:00:00'), actions: ['Open source' => 'https://example.tld/campaign/'.$nativeId, 'Other report' => '/other-report']));
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
        self::assertSame('https://example.tld/campaign/'.$nativeId, $remote->selectLink('<script>External</script>')->attr('href'));
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

    /** @return iterable<string, array{CampaignStatus, string}> */
    public static function subjectDestinations(): iterable
    {
        yield 'draft opens review' => [CampaignStatus::Draft, 'campaign'];
        yield 'scheduled opens review' => [CampaignStatus::Scheduled, 'campaign'];
        yield 'sending opens statistics' => [CampaignStatus::Sending, 'statistics/campaign'];
        yield 'sent opens statistics' => [CampaignStatus::Sent, 'statistics/campaign'];
    }

    #[DataProvider('subjectDestinations')]
    public function testSubjectOpensTheScreenForTheCampaignState(CampaignStatus $status, string $destination): void
    {
        $this->login();
        $campaign = $this->createCampaign($this->createAudience(), subject: 'Clickable campaign');
        match ($status) {
            CampaignStatus::Draft => null,
            CampaignStatus::Scheduled => $campaign->schedule(new DateTimeImmutable('+1 day')),
            CampaignStatus::Sending => $campaign->markSending(0),
            CampaignStatus::Sent => $campaign->markSent(),
        };
        $this->entityManager->flush();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/campaigns');
        self::assertResponseIsSuccessful();
        $subject = $crawler->filter('[data-campaign-key="pushword:'.$campaign->id.'"] th a');
        self::assertCount(1, $subject);
        self::assertSame('Clickable campaign', $subject->text());
        self::assertSame('/admin/newsletter/'.$destination.'/'.$campaign->id, $subject->attr('href'));

        $this->client->click($subject->link());
        self::assertResponseIsSuccessful();
    }

    public function testCampaignsOpenTheCatalogueOnlyOnceAnotherSourceListens(): void
    {
        $this->login();
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics');
        self::assertResponseIsSuccessful();
        self::assertSame(['/admin/newsletter/campaign', '/admin/newsletter/campaign'], $this->campaignLinks($crawler), 'Main menu and section navigation');

        self::getContainer()->get(EventDispatcherInterface::class)->addListener(CollectCampaignIndex::class, static function (): void {});
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics');
        self::assertSame(['/admin/newsletter/campaigns', '/admin/newsletter/campaigns'], $this->campaignLinks($crawler));
    }

    public function testOnlyPushwordStatusesAreTranslatedAndOthersDisplayAsGiven(): void
    {
        $this->login();
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(CollectCampaignIndex::class, static function (CollectCampaignIndex $index): void {
            $index->items = [];
            $index->add(new CampaignIndexItem('external', 'paused', 'Remote paused', 'paused'));
            $index->add(new CampaignIndexItem('external', 'sent', 'Remote sent', 'sent'));
        });

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/campaigns');
        self::assertResponseIsSuccessful();
        self::assertSame('paused', $crawler->filter('[data-campaign-key="external:paused"] td')->eq(1)->text());
        self::assertSame('Sent', $crawler->filter('[data-campaign-key="external:sent"] td')->eq(1)->text());
        self::assertSame(['All', 'paused', 'Sent'], $crawler->filter('[name="status"] option')->extract(['_text']));
        self::assertCount(0, $crawler->filter('[data-campaign-key] th a'));
        self::assertSame(['Remote paused', 'Remote sent'], $crawler->filter('[data-campaign-key] .pw-catalogue-subject')->extract(['_text']));
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

    #[DataProvider('catalogueViews')]
    public function testFilteredCatalogueShowsAvailableCountsAndAnEmptyRecovery(string $route): void
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

        $crawler = $this->client->request(Request::METHOD_GET, $route.'?source=external&status=sent');
        self::assertResponseIsSuccessful();
        self::assertSame('Displayed: 1 / 3 available', $crawler->filter('[data-campaign-results]')->text());
        self::assertCount(1, $crawler->filter('[data-campaign-key]'));

        $crawler = $this->client->request(Request::METHOD_GET, $route.'?q=not-found');
        self::assertResponseIsSuccessful();
        self::assertSame('Displayed: 0 / 3 available', $crawler->filter('[data-campaign-results]')->text());
        self::assertCount(0, $crawler->filter('.pw-catalogue table'));
        self::assertSame('No matching campaigns', $crawler->filter('[data-catalogue-empty="filtered"] h2')->text());
        self::assertSame('not-found', $crawler->filter('[name="q"]')->attr('value'));
        self::assertSame($route.'?host=', $crawler->selectLink('Reset')->attr('href'));
        $crawler = $this->client->click($crawler->selectLink('Reset')->link());
        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('[data-campaign-key]'));
        self::assertSame('Displayed: 3 / 3 available', $crawler->filter('[data-campaign-results]')->text());
    }

    #[DataProvider('catalogueViews')]
    public function testInitialEmptyCatalogueOffersBothCreationsOnceWithoutAnEmptyTable(string $route): void
    {
        $this->login();
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(CollectCampaignIndex::class, static function (CollectCampaignIndex $index): void {
            $index->items = [];
            $index->creationActions['Create in partner'] = 'https://example.tld/new';
            $index->notices['external'] = 'Not synchronized, campaigns are unavailable.';
            $index->noticeLevels['external'] = 'warning';
        });
        $crawler = $this->client->request(Request::METHOD_GET, $route);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.pw-catalogue table'));
        $isCalendar = '/admin/newsletter/calendar' === $route;
        $creations = $crawler->filter($isCalendar ? '.pw-catalogue-page-actions' : '[data-catalogue-empty="initial"]');
        if ($isCalendar) {
            self::assertCount(1, $crawler->filter('[data-newsletter-calendar][data-create-url="/admin/newsletter/campaign/new"]'));
            self::assertCount(0, $crawler->filter('[data-catalogue-empty], .pw-calendar-undated'));
        } else {
            self::assertSame('No campaigns available', $creations->filter('h2')->text());
        }

        self::assertCount(1, $creations->selectLink('Create in Pushword'));
        self::assertCount(1, $creations->selectLink('Create in partner'));
        self::assertCount(1, $crawler->selectLink('Create in Pushword'));
        self::assertCount(1, $crawler->selectLink('Create in partner'));
        self::assertSame('https://example.tld/new', $creations->selectLink('Create in partner')->attr('href'));
        self::assertStringContainsString('campaigns are unavailable', $crawler->filter('[role="status"]')->text());
        self::assertCount(0, $crawler->filter('.pw-catalogue-filters, [data-campaign-results]'));
    }

    /** @return iterable<string, array{string}> */
    public static function catalogueViews(): iterable
    {
        yield 'list' => ['/admin/newsletter/campaigns'];
        yield 'calendar' => ['/admin/newsletter/calendar'];
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

    /** @return list<string> */
    private function campaignLinks(Crawler $crawler): array
    {
        return array_values(array_map(static fn (Link $link): string => (string) parse_url($link->getUri(), \PHP_URL_PATH), $crawler->selectLink('Newsletters')->links()));
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

    public function testSiteFilterPreservesMultiSiteIdentityAndUnknownMetadata(): void
    {
        $this->login();
        $native = $this->createCampaign($this->createAudience(), subject: 'Site filter native');
        $other = $this->createCampaign($this->createAudience(mainHost: 'other.example'), subject: 'Site filter other');
        $nativeKey = 'pushword:'.$native->id;
        $externalKey = 'external:'.$native->id;
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(CollectCampaignIndex::class, static function (CollectCampaignIndex $index) use ($native): void {
            $index->add(new CampaignIndexItem('external', (string) $native->id, 'Site filter shared', actions: ['Source report' => '/whole-campaign-report'], hosts: ['localhost.dev', 'other.example']));
            $index->add(new CampaignIndexItem('external', 'unknown', 'Site filter unknown', audience: 'localhost.dev'));
            $index->notices['external'] = 'Cached yesterday';
        });

        foreach ([
            'localhost.dev' => [$externalKey, $nativeKey],
            'other.example' => [$externalKey, 'pushword:'.$other->id],
            '@unknown' => ['external:unknown'],
            'missing.example' => [],
        ] as $host => $keys) {
            $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/campaigns', ['host' => $host, 'q' => 'Site filter']);
            self::assertResponseIsSuccessful();
            self::assertSame($keys, $crawler->filter('[data-campaign-key]')->extract(['data-campaign-key']));
            self::assertSame($host, $crawler->filter('#campaign-host option[selected]')->attr('value'));
            self::assertStringContainsString('Cached yesterday', $crawler->filter('[role="status"]')->text());
            if (\in_array($externalKey, $keys, true)) {
                self::assertSame('Site: localhost.dev, other.example', $crawler->filter('[data-campaign-key="'.$externalKey.'"] [data-campaign-hosts]')->text());
                self::assertSame('/whole-campaign-report', $crawler->selectLink('Source report')->attr('href'));
            }
        }
    }

    public function testDefaultSiteCanBeClearedAndSharedInTheUrl(): void
    {
        $this->login();
        $native = $this->createCampaign($this->createAudience());
        $other = $this->createCampaign($this->createAudience(mainHost: 'other.example'));
        $index = new CampaignIndex(
            self::getContainer()->get(CampaignRepository::class),
            self::getContainer()->get(UrlGeneratorInterface::class),
            self::getContainer()->get(EventDispatcherInterface::class),
            'localhost.dev',
        );
        self::assertSame('admin_newsletter_campaign_catalogue', $index->route());
        $controller = new CampaignIndexController($index, new EditorialTimezone('Europe/Paris'), 'localhost.dev');
        $controller->setContainer(self::getContainer());
        self::getContainer()->set(CampaignIndexController::class, $controller);

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/campaigns');
        self::assertResponseIsSuccessful();
        self::assertSame(['pushword:'.$native->id], $crawler->filter('[data-campaign-key]')->extract(['data-campaign-key']));
        self::assertSame('localhost.dev', $crawler->filter('#campaign-host option[selected]')->attr('value'));

        $crawler = $this->client->click($crawler->selectLink('Reset')->link());
        self::assertResponseIsSuccessful();
        self::assertSame('', $crawler->filter('form')->form()->getValues()['host']);
        self::assertCount(1, $crawler->filter('[data-campaign-key="pushword:'.$other->id.'"]'));
        self::assertCount(1, $crawler->filter('[data-campaign-key="pushword:'.$native->id.'"]'));
        self::assertStringContainsString('host=', $this->client->getRequest()->getUri());
    }

    public function testCalendarKeepsListFiltersAndExternalOwnership(): void
    {
        $this->login();
        $campaign = $this->createCampaign($this->createAudience(), subject: 'Calendar native');
        $campaign->editorialStage = EditorialStage::Ready;
        $campaign->scheduledAt = new DateTimeImmutable('2030-03-31T00:30:00+01:00');
        $campaign->internalNotes = 'Private notes must not enter the calendar payload';

        $this->entityManager->flush();
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(CollectCampaignIndex::class, static function (CollectCampaignIndex $index) use ($campaign): void {
            $index->add(new CampaignIndexItem('external', (string) $campaign->id, '<script>Calendar remote</script>', 'draft', $campaign->scheduledAt, actions: ['Open' => 'https://example.tld/campaign'], hosts: ['localhost.dev']));
        });

        $crawler = $this->client->request('GET', '/admin/newsletter/calendar?host=localhost.dev&q=Calendar');
        self::assertResponseIsSuccessful();
        $events = json_decode($crawler->filter('[data-newsletter-calendar]')->attr('data-events') ?? '', true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($events);
        self::assertCount(2, $events);
        $byId = array_column($events, null, 'id');
        self::assertIsArray($byId['pushword:'.$campaign->id]);
        self::assertIsArray($byId['external:'.$campaign->id]);
        self::assertIsArray($byId['external:'.$campaign->id]['extendedProps']);
        self::assertTrue($byId['pushword:'.$campaign->id]['editable']);
        self::assertFalse($byId['external:'.$campaign->id]['editable']);
        self::assertNull($byId['external:'.$campaign->id]['extendedProps']['moveUrl']);
        self::assertSame('https://example.tld/campaign', $byId['external:'.$campaign->id]['url']);
        self::assertStringNotContainsString($campaign->internalNotes, (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('host=localhost.dev', $crawler->filter('.pw-catalogue-page-actions')->selectLink('List')->attr('href') ?? '');
        self::assertStringContainsString('q=Calendar', $crawler->filter('.pw-catalogue-page-actions')->selectLink('List')->attr('href') ?? '');
        $crawler = $this->client->click($crawler->filter('.pw-catalogue-page-actions')->selectLink('List')->link());
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('[data-campaign-key]'));

        $crawler = $this->client->request('GET', '/admin/newsletter/calendar?stage=ready&host=localhost.dev');
        $events = json_decode($crawler->filter('[data-newsletter-calendar]')->attr('data-events') ?? '', true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($events);
        self::assertCount(1, $events);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function calendarCreationDates(): iterable
    {
        yield 'day before spring transition' => ['2030-03-31', '2030-03-31T00:00', '2030-03-31T00:00:00+01:00'];
        yield 'time after spring transition' => ['2030-03-31T09:30:00+02:00', '2030-03-31T09:30', '2030-03-31T09:30:00+02:00'];
        yield 'instant on the next editorial day' => ['2030-03-31T22:30:00+00:00', '2030-04-01T00:30', '2030-04-01T00:30:00+02:00'];
    }

    #[DataProvider('calendarCreationDates')]
    public function testCalendarSelectionOpensAnUnsavedDraftAndSavingKeepsItsDate(string $selection, string $fieldValue, string $instant): void
    {
        self::getContainer()->set(EditorialTimezone::class, new EditorialTimezone('Europe/Paris'));
        $this->login();
        $repository = $this->entityManager->getRepository(Campaign::class);
        $before = $repository->count([]);
        $crawler = $this->client->request('GET', '/admin/newsletter/campaign/new?'.http_build_query(['scheduledAt' => $selection]));
        self::assertResponseIsSuccessful();
        self::assertSame($fieldValue, $crawler->filter('#Campaign_scheduledAt')->attr('value'));
        self::assertSame($before, $repository->count([]));
        $form = $crawler->selectButton('Save draft')->form();
        $form['Campaign[workingTitle]'] = 'Calendar creation';
        $this->client->submit($form);
        self::assertResponseRedirects();
        $this->entityManager->clear();
        $campaign = $repository->findOneBy(['workingTitle' => 'Calendar creation']);
        self::assertInstanceOf(Campaign::class, $campaign);

        try {
            self::assertTrue($campaign->isDraft());
            self::assertSame(new DateTimeImmutable($instant)->getTimestamp(), $campaign->scheduledAt?->getTimestamp());
            self::assertSame(0, $campaign->recipientCount);
            self::assertEmailCount(0);
            $crawler = $this->client->followRedirect();
            $crawler = $this->client->click($crawler->selectLink('Create newsletter')->link());
            self::assertSame('', $crawler->filter('#Campaign_scheduledAt')->attr('value') ?? '');
        } finally {
            $this->entityManager->getConnection()->delete('newsletter_campaign', ['id' => $campaign->id]);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCalendarCreationDates(): iterable
    {
        yield 'malformed' => ['not-a-date'];
        yield 'impossible day' => ['2030-02-30'];
        yield 'impossible time' => ['2030-03-31T25:30:00+02:00'];
    }

    #[DataProvider('invalidCalendarCreationDates')]
    public function testCalendarCreationRejectsInvalidDates(string $selection): void
    {
        $this->login();
        $before = $this->entityManager->getRepository(Campaign::class)->count([]);
        $this->client->request('GET', '/admin/newsletter/campaign/new?'.http_build_query(['scheduledAt' => $selection]));
        self::assertResponseStatusCodeSame(400);
        self::assertSame($before, $this->entityManager->getRepository(Campaign::class)->count([]));
    }

    public function testCalendarMovePreservesDraftAndRejectsStaleOrUnauthorizedRequests(): void
    {
        $this->login();
        $campaign = $this->createCampaign($this->createAudience());
        $campaign->editorialStage = EditorialStage::Review;
        $campaign->scheduledAt = new DateTimeImmutable('2030-03-30T09:30:00+01:00');

        $this->entityManager->flush();
        $version = $campaign->version;
        $crawler = $this->client->request('GET', '/admin/newsletter/calendar');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('[data-newsletter-calendar]')->attr('data-token');
        $url = '/admin/newsletter/calendar/'.$campaign->id.'/date';
        $data = ['_token' => $token, 'version' => $version, 'date' => '2030-03-31T09:30:00+02:00'];
        $this->client->request('POST', $url, [...$data, '_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $url, [...$data, 'date' => '2030-02-30T09:30:00+01:00']);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', $url, $data);
        self::assertResponseIsSuccessful();
        $this->entityManager->clear();
        $campaign = $this->entityManager->find(Campaign::class, $campaign->id);
        self::assertInstanceOf(Campaign::class, $campaign);
        self::assertSame(CampaignStatus::Draft, $campaign->status);
        self::assertSame(EditorialStage::Review, $campaign->editorialStage);
        self::assertSame(new DateTimeImmutable($data['date'])->getTimestamp(), $campaign->scheduledAt?->getTimestamp());
        self::assertSame(0, $campaign->recipientCount);
        self::assertGreaterThan($version, $campaign->version);
        $this->client->request('POST', $url, [...$data, 'date' => '2030-04-01T09:30:00+02:00']);
        self::assertResponseStatusCodeSame(409);
        $this->entityManager->clear();
        $campaign = $this->entityManager->find(Campaign::class, $campaign->id);
        self::assertInstanceOf(Campaign::class, $campaign);
        self::assertSame(new DateTimeImmutable($data['date'])->getTimestamp(), $campaign->scheduledAt?->getTimestamp());
    }

    #[DataProvider('lockedCalendarStates')]
    public function testCalendarCannotMoveDeliveryHistory(CampaignStatus $status): void
    {
        $this->login();
        $campaign = $this->createCampaign($this->createAudience());
        $date = new DateTimeImmutable('+1 day');
        $campaign->schedule($date);
        if (CampaignStatus::Sending === $status) {
            $campaign->markSending(0);
        } elseif (CampaignStatus::Sent === $status) {
            $campaign->markSent();
        }

        $this->entityManager->flush();
        $crawler = $this->client->request('GET', '/admin/newsletter/calendar');
        $this->client->request('POST', '/admin/newsletter/calendar/'.$campaign->id.'/date', [
            '_token' => $crawler->filter('[data-newsletter-calendar]')->attr('data-token'),
            'version' => $campaign->version,
            'date' => '2030-03-31T09:30:00+02:00',
        ]);
        self::assertResponseStatusCodeSame(409);
        $this->entityManager->clear();
        $campaign = $this->entityManager->find(Campaign::class, $campaign->id);
        self::assertInstanceOf(Campaign::class, $campaign);
        self::assertSame($status, $campaign->status);
        self::assertSame($date->getTimestamp(), $campaign->scheduledAt?->getTimestamp());
    }

    /** @return iterable<string, array{CampaignStatus}> */
    public static function lockedCalendarStates(): iterable
    {
        yield 'scheduled' => [CampaignStatus::Scheduled];
        yield 'sending' => [CampaignStatus::Sending];
        yield 'sent' => [CampaignStatus::Sent];
    }

    public function testGeneratedBroadcastsAreReadOnlyInTheCalendar(): void
    {
        $this->login();
        $audience = $this->createAudience();
        $campaign = $this->createCampaign($audience);
        $campaign->triggeredBy($this->createAutomation($audience, []), 42);
        $campaign->scheduledAt = new DateTimeImmutable('+1 day');

        $this->entityManager->flush();
        $crawler = $this->client->request('GET', '/admin/newsletter/calendar');
        $events = json_decode($crawler->filter('[data-newsletter-calendar]')->attr('data-events') ?? '', true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($events);
        self::assertIsArray($events[0]);
        self::assertFalse($events[0]['editable']);
        $this->client->request('POST', '/admin/newsletter/calendar/'.$campaign->id.'/date', [
            '_token' => $crawler->filter('[data-newsletter-calendar]')->attr('data-token'),
            'version' => $campaign->version,
            'date' => '2030-03-31T09:30:00+02:00',
        ]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testCalendarRequiresEditorRole(): void
    {
        $this->login('ROLE_USER');
        $this->client->request('GET', '/admin/newsletter/calendar');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/admin/newsletter/calendar/1/date');
        self::assertResponseStatusCodeSame(403);
    }
}
