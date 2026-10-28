<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Admin;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Event\CampaignIndexItem;
use Pushword\Newsletter\Event\CollectCampaignIndex;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class CampaignCatalogueSourceTest extends AbstractNewsletterTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = new User();
        $this->user->email = 'catalogue-source-'.bin2hex(random_bytes(6)).'@example.tld';
        $this->user->setRoles(['ROLE_EDITOR']);
        $this->user->locale = 'en';

        $this->entityManager->persist($this->user);
        $this->entityManager->flush();

        $this->client->loginUser($this->user);
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->delete($connection->quoteIdentifier('user'), ['id' => $this->user->id]);
        parent::tearDown();
    }

    public function testNativeCatalogueHidesSourceBeforeAndAfterFiltering(): void
    {
        $campaign = $this->createCampaign($this->createAudience(), subject: 'Native newsletter');

        foreach (['', '?status=draft&q=Native', '?q=missing'] as $query) {
            $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/campaigns'.$query);
            self::assertResponseIsSuccessful();
            self::assertCount(0, $crawler->filter('[name="source"], label[for="campaign-source"], td[data-label="Source"]'));
            self::assertNotContains('Source', $crawler->filter('thead th')->extract(['_text']));
            self::assertCount(1, $crawler->filter('[name="status"]'));
            self::assertCount(1, $crawler->filter('[name="q"]'));
            if ('?q=missing' !== $query) {
                self::assertCount(1, $crawler->filter('[data-campaign-key="pushword:'.$campaign->id.'"]'));
                self::assertSame(['Subject', 'Status', 'Scheduled for', 'Sent at', 'Actions'], $crawler->filter('thead th')->extract(['_text']));
            }
        }
    }

    /** @return iterable<string, array{list<string>, list<string>, string}> */
    public static function externalCatalogues(): iterable
    {
        yield 'external rows only' => [['external'], [], ''];
        yield 'filtered to native rows' => [['pushword', 'external'], [], '?source=pushword'];
        yield 'unavailable external source' => [['pushword'], ['external'], ''];
    }

    /**
     * @param list<string> $sources
     * @param list<string> $unavailableSources
     */
    #[DataProvider('externalCatalogues')]
    public function testExternalSourceRemainsVisible(array $sources, array $unavailableSources, string $query): void
    {
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(CollectCampaignIndex::class, static function (CollectCampaignIndex $index) use ($sources, $unavailableSources): void {
            $index->items = [];
            foreach ($sources as $source) {
                $index->add(new CampaignIndexItem($source, '42', 'Newsletter', 'draft'));
            }

            foreach ($unavailableSources as $source) {
                $index->notices[$source] = 'Source unavailable.';
            }
        });

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/campaigns'.$query);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[name="source"]'));
        self::assertCount(1, $crawler->filter('[name="source"] option[value="external"]'));
        self::assertSame(['Subject', 'Source', 'Status', 'Scheduled for', 'Sent at', 'Actions'], $crawler->filter('thead th')->extract(['_text']));
        self::assertCount(1, $crawler->filter('td[data-label="Source"]'));
    }
}
