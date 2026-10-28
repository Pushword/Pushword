<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Admin;

use App\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Service\EditorialTimezone;
use Pushword\Newsletter\Admin\StatisticsController;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\CampaignRecipient;
use Pushword\Newsletter\Entity\ClickEvent;
use Pushword\Newsletter\Entity\ContactEvent;
use Pushword\Newsletter\Entity\DeliveryMessage;
use Pushword\Newsletter\Repository\AudienceRepository;
use Pushword\Newsletter\Repository\ClickEventRepository;
use Pushword\Newsletter\Repository\StatisticsRepository;
use Pushword\Newsletter\Statistics\LinkReport;
use Pushword\Newsletter\Statistics\LocalStatisticsProvider;
use Pushword\Newsletter\Statistics\Metric;
use Pushword\Newsletter\Statistics\Report;
use Pushword\Newsletter\Statistics\ReportQuery;
use Pushword\Newsletter\Statistics\StatisticsDashboard;
use Pushword\Newsletter\Statistics\StatisticsProvider;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Group('integration')]
final class NewsletterStatisticsTest extends AbstractNewsletterTestCase
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

    public function testCampaignsCountEachClickerOncePerCampaign(): void
    {
        $audience = $this->createAudience();
        $audience->clickTracking = true;

        $small = $this->sentCampaign($audience, 'Small', 2);
        $large = $this->sentCampaign($audience, 'Large', 8);
        $reader = $this->createContact($audience, 'reader@example.tld');
        $other = $this->createContact($audience, 'other@example.tld');
        foreach ([$small, $small, $large] as $campaign) {
            $this->entityManager->persist(ClickEvent::onCampaign($reader, $campaign, 'https://example.tld/article'));
            $campaign->incrementClick();
        }

        $this->entityManager->persist(ClickEvent::onCampaign($other, $large, 'https://example.tld/second'));
        $large->incrementClick();
        $this->entityManager->flush();

        $repository = $this->repository();
        $campaigns = $repository->campaigns(new DateTimeImmutable('yesterday'), new DateTimeImmutable('tomorrow'), $audience->id);
        self::assertCount(2, $campaigns);
        $byId = array_column($campaigns, null, 'id');
        self::assertNotNull($small->id);
        self::assertNotNull($large->id);
        self::assertArrayHasKey($small->id, $byId);
        self::assertArrayHasKey($large->id, $byId);
        self::assertSame(1, $byId[$small->id]['clickers'], 'The same reader counts once in each campaign.');
        self::assertSame(2, $byId[$small->id]['clicks']);
        self::assertSame(2, $byId[$large->id]['clickers']);
        self::assertSame(2, $byId[$large->id]['clicks']);
    }

    public function testPeriodUsesFirstHandoffAndExcludesDraftsOtherAudiencesAndBoundaries(): void
    {
        $audience = $this->createAudience();
        $first = $this->sentCampaign($audience, 'First');
        $last = $this->sentCampaign($audience, 'Last');
        $before = $this->sentCampaign($audience, 'Before');
        $after = $this->sentCampaign($audience, 'After');
        $outside = $this->sentCampaign($this->createAudience(), 'Other audience');
        $draft = $this->createCampaign($audience);
        $scheduled = $this->createCampaign($audience)->schedule(new DateTimeImmutable('2026-10-25'));
        $sending = $this->createCampaign($audience)->markSending(1);
        $reader = $this->createContact($audience, 'reader@example.tld');
        $recipient = new CampaignRecipient($sending, $reader)->markSent();
        $this->entityManager->persist($recipient);
        $this->entityManager->flush();
        foreach ([[$first, '2026-10-25 00:00:00'], [$last, '2026-10-25 23:59:59'], [$before, '2026-10-24 23:59:59'], [$after, '2026-10-26 00:00:00'], [$outside, '2026-10-25 12:00:00']] as [$campaign, $date]) {
            $this->entityManager->getConnection()->update('newsletter_campaign', ['sent_at' => $date], ['id' => $campaign->id]);
        }

        $this->entityManager->getConnection()->update('newsletter_campaign_recipient', ['sent_at' => '2026-10-25 08:00:00'], ['id' => $recipient->id]);
        $campaigns = $this->repository()->campaigns(new DateTimeImmutable('2026-10-25'), new DateTimeImmutable('2026-10-26'), $audience->id);
        self::assertSame([$last->id, $sending->id, $first->id], array_column($campaigns, 'id'));
        self::assertNotContains($draft->id, array_column($campaigns, 'id'));
        self::assertNotContains($scheduled->id, array_column($campaigns, 'id'));
        self::assertCount(4, $this->repository()->campaigns(new DateTimeImmutable('2026-10-25'), new DateTimeImmutable('2026-10-26'), null));
    }

    public function testRecipientStatesAreCountedWithoutInferringSuccess(): void
    {
        $audience = $this->createAudience();
        $campaign = $this->sentCampaign($audience, 'States', 2);
        $campaign->markSending(5)->incrementFailed()->incrementBounce();
        foreach (['pending', 'skipped', 'failed', 'sent', 'bounced'] as $state) {
            $contact = $this->createContact($audience, $state.'@example.tld');
            $recipient = new CampaignRecipient($campaign, $contact);
            match ($state) {
                'skipped' => $recipient->markSkipped(),
                'failed' => $recipient->markFailed('Transport refused'),
                'sent' => $recipient->markSent(),
                'bounced' => $recipient->markSent()->markBounced(),
                default => null,
            };
            $this->entityManager->persist($recipient);
        }

        $this->entityManager->flush();
        self::assertNotNull($campaign->id);
        $data = $this->repository()->campaign($campaign->id);
        self::assertNotNull($data);
        self::assertSame(2, $data['sent']);
        self::assertSame(1, $data['pending']);
        self::assertSame(1, $data['skipped']);
        self::assertSame(1, $data['failed']);
        self::assertSame(1, $data['bounced']);
    }

    public function testContactsAndActivityKeepCurrentStateSeparateFromPeriodEvents(): void
    {
        $audience = $this->createAudience();
        $subscribed = $this->createContact($audience, 'subscribed@example.tld');
        $this->createPhoneContact($audience, '+33612345678');
        $this->createContact($audience, 'pending@example.tld', subscribed: false);
        $this->createContact($audience, 'left@example.tld')->unsubscribe();
        $this->createContact($this->createAudience(), 'elsewhere@example.tld');
        $this->entityManager->persist(ContactEvent::optIn($subscribed, 'test', null, null));
        $this->entityManager->persist(ContactEvent::confirmed($subscribed, 'test', null, null));
        $this->entityManager->flush();

        self::assertSame(['prospect' => 0, 'pending' => 1, 'subscribed' => 2, 'unsubscribed' => 1, 'bounced' => 0, 'mailable' => 1], $this->repository()->contacts($audience->id));
        $activity = $this->repository()->subscriptionActivity(new DateTimeImmutable('yesterday'), new DateTimeImmutable('tomorrow'), $audience->id);
        self::assertSame(1, $activity['optin']);
        self::assertSame(1, $activity['confirm']);
        self::assertSame(0, $activity['unsubscribe']);
        $past = $this->repository()->subscriptionActivity(new DateTimeImmutable('-2 days'), new DateTimeImmutable('yesterday'), $audience->id);
        self::assertSame(0, $past['optin']);
    }

    public function testStatisticsPagesNavigationLinksAndCsvUseTheSelectedAudience(): void
    {
        $this->login();
        $audience = $this->createAudience();
        $audience->clickTracking = true;

        $campaign = $this->sentCampaign($audience, '=1+1', 2);
        $this->sentCampaign($this->createAudience(), 'Excluded');
        $contact = $this->createContact($audience, 'reader@example.tld');
        foreach (['https://example.tld/a?x=<script>', 'https://example.tld/a?x=<script>', 'https://example.tld/b'] as $url) {
            $this->entityManager->persist(ClickEvent::onCampaign($contact, $campaign, $url));
            $campaign->incrementClick();
        }

        $this->entityManager->flush();
        $query = '?from='.date('Y-m-d').'&to='.date('Y-m-d').'&audience='.$audience->id;
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics'.$query);
        self::assertResponseIsSuccessful();
        self::assertSame('Statistics', $crawler->filter('.pw-newsletter-nav [aria-current="page"]')->text());
        self::assertCount(1, $crawler->filter('[data-campaign]'));
        self::assertSame('50.00 %', $crawler->filter('[data-statistic="clickRate"] [data-rate-value]')->text());
        self::assertStringNotContainsString('newsletter.statistics.', $crawler->filter('article')->text());
        $crawler = $this->client->click($crawler->selectLink('=1+1')->link());
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('.pw-statistics-links tbody tr'));
        self::assertStringContainsString('audience='.$audience->id, $crawler->filter('a.pw-statistics-back')->link()->getUri());
        self::assertStringContainsString('from='.date('Y-m-d'), $crawler->filter('a.pw-statistics-back')->link()->getUri());
        self::assertStringContainsString('https://example.tld/a?x=<script>', $crawler->filter('.pw-statistics-url')->first()->text());
        self::assertCount(0, $crawler->filter('.pw-statistics-url script'));
        $this->client->click($crawler->selectLink('Target recipients')->link());
        self::assertResponseIsSuccessful();

        $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics/export'.$query);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        $rows = new CsvEncoder()->decode((string) $this->client->getResponse()->getContent(), 'csv');
        self::assertIsArray($rows);
        self::assertCount(1, $rows);
        $row = $rows[0];
        self::assertIsArray($row);
        self::assertSame("'=1+1", $row['subject']);
        self::assertSame('50', $row['clickRate']);
    }

    public function testEmptyPeriodAndZeroSendsHaveNoInventedRates(): void
    {
        $this->login();
        $audience = $this->createAudience();
        $this->sentCampaign($audience, 'No sends');
        $this->entityManager->flush();
        $query = '?from=2000-01-01&to=2000-01-01&audience='.$audience->id;
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics'.$query);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('No campaigns sent', $crawler->filter('.pw-statistics-empty')->text());
        self::assertCount(0, $crawler->filter('[data-statistic]'));
        self::assertCount(0, $crawler->filter('.pw-statistics-export'));
        self::assertSame('#statistics-from', $crawler->selectLink('Change the period')->attr('href'));
        $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics/export'.$query);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('sourceId', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('clickRateDenominator', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('acceptedState', (string) $this->client->getResponse()->getContent());
    }

    public function testConsentWithdrawalRemovesUniqueClicksAndLinksButKeepsTheCumulativeCounter(): void
    {
        $this->login();
        $audience = $this->createAudience();
        $audience->clickTracking = true;

        $campaign = $this->sentCampaign($audience, 'Consent', 1);
        $contact = $this->createContact($audience, 'consenting@example.tld');
        $this->entityManager->persist(ClickEvent::onCampaign($contact, $campaign, 'https://example.tld/article'));
        $campaign->incrementClick();
        $this->entityManager->flush();
        self::assertNotNull($campaign->id);
        $before = $this->repository()->campaign($campaign->id);
        self::assertNotNull($before);
        self::assertSame(1, $before['clickers']);
        self::getContainer()->get(ClickEventRepository::class)->purgeFor($contact);
        $after = $this->repository()->campaign($campaign->id);
        self::assertNotNull($after);
        self::assertSame(1, $after['clicks']);
        self::assertSame(0, $after['clickers']);
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics/campaign/'.$campaign->id);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.pw-statistics-url'));
        self::assertSame('1', $crawler->filter('[data-statistic="clicks"]')->text());
    }

    public function testReportLinksToTheFailedRecipientsAndUntrackedExportHasNoClickMeasurement(): void
    {
        $this->login();
        $audience = $this->createAudience();
        $campaign = $this->sentCampaign($audience, 'Failures', 1)->incrementFailed();
        $failed = new CampaignRecipient($campaign, $this->createContact($audience, 'failed@example.tld'))->markFailed('Mailbox refused');
        $sent = new CampaignRecipient($campaign, $this->createContact($audience, 'sent@example.tld'))->markSent();
        $this->entityManager->persist($failed);
        $this->entityManager->persist($sent);
        $this->entityManager->flush();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics/campaign/'.$campaign->id);
        self::assertResponseIsSuccessful();
        $crawler = $this->client->click($crawler->selectLink('Sending failures')->link());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('failed@example.tld', $crawler->filter('tbody')->text());
        self::assertStringNotContainsString('sent@example.tld', $crawler->filter('tbody')->text());
        $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics/export?audience='.$audience->id);
        $rows = new CsvEncoder()->decode((string) $this->client->getResponse()->getContent(), 'csv');
        self::assertIsArray($rows);
        self::assertCount(1, $rows);
        $row = $rows[0];
        self::assertIsArray($row);
        self::assertSame('', $row['clickers']);
        self::assertSame('', $row['clickRate']);
    }

    #[DataProvider('invalidFilters')]
    public function testInvalidFiltersAreRejected(string $query): void
    {
        $this->login();
        $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics'.$query);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics/export'.$query);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testInvalidRangeStaysInTheFormWithAccessibleErrorsAndEnteredValues(): void
    {
        $this->login();
        $audience = $this->createAudience();
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics?from=2026-02-02&to=2026-02-01&audience='.$audience->id);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertStringContainsString('end date must be on or after', $crawler->filter('.pw-statistics-errors[role="alert"]')->text());
        self::assertSame('2026-02-02', $crawler->filter('[name="from"]')->attr('value'));
        self::assertSame('2026-02-01', $crawler->filter('[name="to"]')->attr('value'));
        $to = $crawler->filter('[name="to"]');
        self::assertSame('true', $to->attr('aria-invalid'));
        self::assertCount(1, $crawler->filter('#'.$to->attr('aria-describedby')));
        self::assertCount(0, $crawler->filter('[data-statistic]'));
        self::assertSame((string) $audience->id, $crawler->filter('[name="audience"] option[selected]')->attr('value'));
        parse_str((string) parse_url($crawler->selectLink('Last 7 days')->link()->getUri(), \PHP_URL_QUERY), $shortcut);
        self::assertSame((string) $audience->id, $shortcut['audience']);
        $form = $crawler->filter('form[data-statistics-filters]')->form();
        $form['to'] = '2026-02-03';
        $this->client->submit($form);
        self::assertResponseIsSuccessful();
    }

    public function testPeriodShortcutsPreserveTheAudienceAndResetReturnsToTheDefault(): void
    {
        $this->login();
        $audience = $this->createAudience();
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics?audience='.$audience->id);
        $crawler = $this->client->click($crawler->selectLink('Last 7 days')->link());
        self::assertResponseIsSuccessful();
        self::assertSame(date('Y-m-d', strtotime('-6 days')), $crawler->filter('[name="from"]')->attr('value'));
        self::assertSame((string) $audience->id, $crawler->filter('[name="audience"] option[selected]')->attr('value'));
        self::assertSame('Last 7 days', $crawler->filter('.pw-statistics-presets [aria-current]')->text());
        $crawler = $this->client->click($crawler->selectLink('Reset')->link());
        self::assertResponseIsSuccessful();
        self::assertSame(date('Y-m-d', strtotime('-29 days')), $crawler->filter('[name="from"]')->attr('value'));
        self::assertSame('', $crawler->filter('form[data-statistics-filters]')->form()->getPhpValues()['audience']);
        $viewport = $crawler->filter('meta[name="viewport"]')->attr('content') ?? '';
        self::assertStringNotContainsString('user-scalable=no', $viewport);
        self::assertStringNotContainsString('maximum-scale=1', $viewport);
    }

    public function testReportsDistinguishNoSendsUnavailableTrackingAndMeasuredZero(): void
    {
        $this->login();
        $tracked = $this->createAudience();
        $tracked->clickTracking = true;

        $noSends = $this->sentCampaign($tracked);
        $measuredZero = $this->sentCampaign($tracked, sent: 1);
        $untracked = $this->sentCampaign($this->createAudience(), sent: 1);
        foreach ([[$noSends, 'Not measured'], [$measuredZero, '0.00 %'], [$untracked, 'Not measured']] as [$campaign, $expected]) {
            $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics/campaign/'.$campaign->id);
            self::assertResponseIsSuccessful();
            self::assertSame($expected, $crawler->filter('[data-statistic="clickRate"] [data-rate-value]')->text());
            self::assertCount(1, $crawler->filter('[data-delivery-coverage="partial"]'));
            self::assertStringContainsString('incomplete', $crawler->filter('[data-delivery-coverage="partial"]')->text());
            if ($campaign === $noSends) {
                self::assertCount(2, $crawler->filter('.pw-statistics-quality .pw-statistics-unmeasured'));
                self::assertSame(['Not measured', 'Not measured'], $crawler->filter('.pw-statistics-quality .pw-statistics-unmeasured')->each(static fn (Crawler $node): string => $node->text()));
            }

            if ($campaign === $untracked) {
                self::assertCount(1, $crawler->selectLink('Audience settings'));
            }
        }
    }

    public function testLinkReportPrioritizesUniqueReadersAndRetainsTheCompleteUrl(): void
    {
        $this->login();
        $audience = $this->createAudience();
        $audience->clickTracking = true;

        $campaign = $this->sentCampaign($audience, sent: 3);
        $reader = $this->createContact($audience, 'reader@example.tld');
        $other = $this->createContact($audience, 'other@example.tld');
        for ($i = 0; $i < 3; ++$i) {
            $this->entityManager->persist(ClickEvent::onCampaign($reader, $campaign, 'https://example.tld/repeat'));
            $campaign->incrementClick();
        }

        $url = 'https://example.tld/reach?utm_campaign=test&name=reader';
        foreach ([$reader, $other] as $contact) {
            $this->entityManager->persist(ClickEvent::onCampaign($contact, $campaign, $url));
            $campaign->incrementClick();
        }

        $this->entityManager->flush();
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics/campaign/'.$campaign->id);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-report] [data-statistic="clicks"]'));
        $first = $crawler->filter('.pw-statistics-links tbody tr')->first();
        self::assertSame($url, $first->filter('a')->attr('href'));
        self::assertSame($url, $first->filter('code')->text());
        self::assertSame('noopener noreferrer', $first->filter('a')->attr('rel'));
        self::assertSame('2', $first->filter('meter')->attr('value'));
    }

    #[DataProvider('reportLocales')]
    public function testReportNumbersFollowTheUserLocale(string $locale, string $expectedCount, string $expectedRate): void
    {
        $this->login();
        self::assertNotNull($this->user);
        $this->user->locale = $locale;
        $audience = $this->createAudience();
        $audience->clickTracking = true;

        $campaign = $this->sentCampaign($audience, sent: 1000);
        $reader = $this->createContact($audience, 'reader@example.tld');
        $this->entityManager->persist(ClickEvent::onCampaign($reader, $campaign, 'https://example.tld/article'));
        $campaign->incrementClick();
        $this->entityManager->flush();
        $this->client->loginUser($this->user);
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics/campaign/'.$campaign->id);
        self::assertResponseIsSuccessful();
        $count = str_replace(["\u{00A0}", "\u{202F}"], ' ', $crawler->filter('[data-statistic="sent"]')->text());
        self::assertSame($expectedCount, $count);
        self::assertSame($expectedRate, $crawler->filter('[data-statistic="clickRate"] [data-rate-value]')->text());
        self::assertStringNotContainsString('newsletter.statistics.', $crawler->filter('article')->text());
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function reportLocales(): iterable
    {
        yield 'English' => ['en', '1,000', '0.10 %'];
        yield 'French' => ['fr', '1 000', '0,10 %'];
    }

    /** @return iterable<string, array{string}> */
    public static function invalidFilters(): iterable
    {
        yield 'impossible date' => ['?from=2026-02-30'];
        yield 'trailing data' => ['?from=2026-01-01oops'];
        yield 'reversed dates' => ['?from=2026-02-02&to=2026-02-01'];
        yield 'unknown audience' => ['?audience=2147483647'];
        yield 'malformed audience' => ['?audience=all'];
        yield 'missing date' => ['?from='];
        yield 'date array' => ['?from[]=2026-01-01'];
        yield 'trailing whitespace' => ['?from=2026-01-01%20'];
        yield 'audience array' => ['?audience[]=1'];
    }

    public function testEditorialDayIncludesBothHoursOfTheAutumnClockChange(): void
    {
        $this->login();
        $timezone = new EditorialTimezone('Europe/Paris');
        $controller = new StatisticsController(
            $this->repository(),
            self::getContainer()->get(AudienceRepository::class),
            $timezone,
            self::getContainer()->get(TranslatorInterface::class),
            self::getContainer()->get(StatisticsDashboard::class),
            self::getContainer()->get(LocalStatisticsProvider::class),
        );
        $controller->setContainer(self::getContainer());
        self::getContainer()->set(StatisticsController::class, $controller);
        $audience = $this->createAudience();
        foreach (['2026-10-24 23:59:59', '2026-10-25 00:00:00', '2026-10-25 23:59:59', '2026-10-26 00:00:00'] as $date) {
            $campaign = $this->sentCampaign($audience, $date);
            $this->entityManager->getConnection()->update('newsletter_campaign', ['sent_at' => $timezone->parse($date)->format('Y-m-d H:i:s')], ['id' => $campaign->id]);
        }

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics?from=2026-10-25&to=2026-10-25&audience='.$audience->id);
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('[data-campaign]'));
        self::assertSame('2026-10-25', $crawler->filter('input[name="to"]')->attr('value'));
    }

    public function testReportsRequireAnEditor(): void
    {
        $audience = $this->createAudience();
        $campaign = $this->sentCampaign($audience);
        foreach (['', '/export', '/campaign/'.$campaign->id] as $suffix) {
            $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics'.$suffix);
            self::assertResponseRedirects();
        }

        $this->login('ROLE_USER');
        foreach (['', '/export', '/campaign/'.$campaign->id] as $suffix) {
            $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics'.$suffix);
            self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        }
    }

    public function testUnknownCampaignReturns404(): void
    {
        $this->login();
        $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics/campaign/2147483647');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testExternalReportsUseTheSameInterfaceAndExportStatesAndFractions(): void
    {
        $this->login();
        $report = new Report('brevo', '42', 'External campaign', 'https://example.tld/manage/42', new DateTimeImmutable(), [
            'accepted' => new Metric(100),
            'trackedMessages' => new Metric(80),
            'clickers' => new Metric(8),
        ]);
        $provider = new readonly class($report) implements StatisticsProvider {
            public function __construct(private Report $report)
            {
            }

            public function reports(ReportQuery $query): iterable
            {
                if (null === $query->windowDays) {
                    yield $this->report;

                    return;
                }

                yield new Report($this->report->source, $this->report->id, $this->report->label, $this->report->managementUrl, $this->report->date, [], mature: false);
            }
        };
        self::getContainer()->set(StatisticsDashboard::class, new StatisticsDashboard([$provider]));
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-source="brevo"]'));
        self::assertSame('10.00 %', $crawler->filter('[data-statistic="clickRate"] [data-rate-value]')->text());
        self::assertSame('https://example.tld/manage/42', $crawler->selectLink('Manage in source tool')->link()->getUri());
        $crawler = $this->client->click($crawler->selectLink('External campaign')->link());
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('.pw-statistics-windows thead th small'));
        $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics/export');
        $rows = new CsvEncoder()->decode((string) $this->client->getResponse()->getContent(), 'csv');
        self::assertIsArray($rows);
        self::assertIsArray($rows[0]);
        self::assertSame('brevo', $rows[0]['source']);
        self::assertSame('42', $rows[0]['sourceId']);
        self::assertSame('sent', $rows[0]['basis']);
        self::assertSame('lifetime', $rows[0]['window']);
        self::assertArrayHasKey('asOf', $rows[0]);
        self::assertSame('unavailable', $rows[0]['deliveredState']);
        self::assertSame('8', $rows[0]['clickRateNumerator']);
        self::assertSame('80', $rows[0]['clickRateDenominator']);
    }

    /** @return iterable<string, array{'info'|'warning'|'danger'}> */
    public static function reportNoticeLevels(): iterable
    {
        yield 'routine' => ['info'];
        yield 'retained stale snapshot' => ['warning'];
        yield 'unavailable source' => ['danger'];
    }

    /** @param 'info'|'warning'|'danger' $level */
    #[DataProvider('reportNoticeLevels')]
    public function testReportBasesAndObservationMetadataAppearInListDetailAndExport(string $level): void
    {
        $this->login();
        $observedAt = new DateTimeImmutable('2026-01-12T08:45:00+00:00');
        $notice = '=Retained snapshot <script>alert(1)</script>';
        $report = new Report('external', 'snapshot', 'Cached campaign', 'https://example.tld/manage', null, [
            'accepted' => new Metric(100),
            'delivered' => new Metric(80),
            'clickers' => new Metric(8),
        ], rateDenominators: ['clickRate' => 'delivered', 'filteredClickRate' => null], observedAt: $observedAt, notice: $notice, noticeLevel: $level);
        $provider = new readonly class($report) implements StatisticsProvider {
            public function __construct(private Report $report)
            {
            }

            public function reports(ReportQuery $query): iterable
            {
                return null === $query->windowDays ? [$this->report] : [];
            }
        };
        self::getContainer()->set(StatisticsDashboard::class, new StatisticsDashboard([$provider]));
        foreach (['', '/report/external/snapshot'] as $suffix) {
            $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics'.$suffix);
            self::assertResponseIsSuccessful();
            self::assertSame('10.00 %', $crawler->filter('[data-statistic="clickRate"] [data-rate-value]')->text());
            self::assertStringContainsString('8 Unique clickers / 80 Confirmed delivery', $crawler->filter('[data-statistic="clickRate"]')->text());
            self::assertSame('Unavailable', $crawler->filter('[data-statistic="coverage"] [data-rate-value]')->text());
            self::assertStringContainsString('Rate base unavailable', $crawler->filter('[data-statistic="filteredClickRate"]')->text());
            self::assertSame($observedAt->format(\DATE_ATOM), $crawler->filter('[data-report-observed-at] time')->attr('datetime'));
            self::assertSame($level, $crawler->filter('[data-report-notice]')->attr('data-notice-level'));
            self::assertSame($notice, $crawler->filter('[data-report-notice]')->text());
            self::assertCount(0, $crawler->filter('[data-report-notice] script'));
        }

        $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics/export');
        self::assertResponseIsSuccessful();
        $rows = new CsvEncoder()->decode((string) $this->client->getResponse()->getContent(), 'csv');
        self::assertIsArray($rows);
        self::assertIsArray($rows[0]);
        $row = $rows[0];
        self::assertSame('10', $row['clickRate']);
        self::assertSame('clickers', $row['clickRateNumeratorMetric']);
        self::assertSame('delivered', $row['clickRateDenominatorMetric']);
        self::assertSame('', $row['filteredClickRateDenominatorMetric']);
        self::assertSame('unavailable', $row['filteredClickRateState']);
        self::assertSame('', $row['trackedMessages']);
        self::assertSame('unavailable', $row['trackedMessagesState']);
        self::assertSame($observedAt->format(\DATE_ATOM), $row['observedAt']);
        self::assertSame("'".$notice, $row['notice'], 'Provider notices must keep CSV formula protection.');
        self::assertSame($level, $row['noticeLevel']);
        $keys = array_keys($row);
        $matureColumn = array_search('mature', $keys, true);
        self::assertIsInt($matureColumn);
        self::assertSame(['mature', 'observedAt', 'notice', 'noticeLevel', 'clickRateNumeratorMetric'], \array_slice($keys, $matureColumn, 5));
    }

    public function testLegacyWindowReportsUnavailableLinksInsteadOfMeasuredZero(): void
    {
        $this->login();
        $audience = $this->createAudience();
        $campaign = $this->sentCampaign($audience, sent: 1);
        foreach ($this->entityManager->getRepository(DeliveryMessage::class)->findBy(['campaign' => $campaign]) as $message) {
            $this->entityManager->remove($message);
        }

        $campaign->incrementClick();
        $this->entityManager->flush();
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics/campaign/'.$campaign->id.'?window=7');
        self::assertResponseIsSuccessful();
        self::assertSame('Unavailable', $crawler->filter('.pw-statistics-links-empty h3')->text());
    }

    public function testExternalLinksKeepTheirStatesAndCsvKeepsTheVersion81Prefix(): void
    {
        $this->login();
        $report = new Report('external', 'links', 'External links', 'https://example.org/manage', null, [
            'accepted' => new Metric(10), 'delivered' => new Metric(8), 'trackedMessages' => new Metric(5), 'clickers' => new Metric(1),
        ], links: [
            new LinkReport('https://example.org/old', 'trip-main', 'body', Metric::unavailable(), new Metric(1)),
            new LinkReport('https://example.org/new', 'trip-main', 'body', Metric::unavailable(), new Metric(1, complete: false)),
            new LinkReport('javascript:alert(1)', '<script>unsafe</script>', 'unknown', Metric::unavailable(), Metric::unavailable()),
        ]);
        $provider = new readonly class($report) implements StatisticsProvider {
            public function __construct(private Report $report)
            {
            }

            public function reports(ReportQuery $query): iterable
            {
                return null === $query->windowDays ? [$this->report] : [];
            }
        };
        self::getContainer()->set(StatisticsDashboard::class, new StatisticsDashboard([$provider]));
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics/report/external/links?clickScope=body');
        self::assertResponseIsSuccessful();
        self::assertSame('20.00 %', $crawler->filter('[data-statistic="clickRate"] [data-rate-value]')->text());
        self::assertSame('12.50 %', $crawler->filter('[data-statistic="clickRateDelivered"] [data-rate-value]')->text());
        self::assertCount(3, $crawler->filter('.pw-statistics-links tbody tr'));
        self::assertCount(2, $crawler->filter('.pw-statistics-links a'));
        self::assertCount(0, $crawler->filter('.pw-statistics-links script'));
        self::assertStringContainsString('Unavailable', $crawler->filter('.pw-statistics-links tbody tr')->first()->text());
        self::assertStringContainsString('≥', $crawler->filter('.pw-statistics-links tbody tr')->eq(1)->text());
        self::assertStringContainsString('clickScope=body', (string) $crawler->filter('.pw-statistics-back')->attr('href'));

        $this->client->request(Request::METHOD_GET, '/admin/newsletter/statistics/export?clickScope=body');
        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $header = explode("\n", ltrim($content, "\xEF\xBB\xBF"))[0];
        $prefix = trim((string) file_get_contents(__DIR__.'/../Statistics/fixtures/report-1.0.81-header.txt'));
        self::assertStringStartsWith($prefix.',clickScope,', $header);
        $rows = new CsvEncoder()->decode($content, 'csv');
        self::assertIsArray($rows);
        self::assertIsArray($rows[0]);
        self::assertSame('body', $rows[0]['clickScope']);
        self::assertSame('12.5', $rows[0]['clickRateDelivered']);
        self::assertSame('delivered', $rows[0]['clickRateDeliveredDenominatorMetric']);
        self::assertSame('yes', $rows[0]['deliveredComplete']);
    }

    private function login(string $role = 'ROLE_SUPER_ADMIN'): void
    {
        $this->user = new User();
        $this->user->email = 'statistics-'.bin2hex(random_bytes(6)).'@example.tld';
        $this->user->setRoles([$role]);

        $this->entityManager->persist($this->user);
        $this->entityManager->flush();

        $this->client->loginUser($this->user);
    }

    private function sentCampaign(Audience $audience, string $subject = 'Statistics', int $sent = 0): Campaign
    {
        $campaign = $this->createCampaign($audience, subject: $subject)->markSending($sent)->markSent();
        for ($index = 0; $index < $sent; ++$index) {
            $campaign->incrementSent();
        }

        if ($sent > 0) {
            $contact = $this->createContact($audience, 'snapshot-'.bin2hex(random_bytes(6)).'@example.tld');
            for ($index = 0; $index < $sent; ++$index) {
                $message = new DeliveryMessage($contact, $audience->clickTracking, $campaign);
                $message->accept(null);
                $this->entityManager->persist($message);
            }
        }

        $this->entityManager->flush();

        return $campaign;
    }

    private function repository(): StatisticsRepository
    {
        return new StatisticsRepository($this->entityManager->getConnection());
    }
}
