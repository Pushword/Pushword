<?php

namespace Pushword\Newsletter\Tests\Trigger;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\AutomationStep;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Enum\CampaignStatus;
use Pushword\Newsletter\Service\AutomationRunner;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Email;

/**
 * An automation on the page source: an occurrence that names no contact, whose
 * steps go out as scheduled campaigns.
 */
#[Group('integration')]
final class PageAutomationTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    /**
     * An automation watches a whole host, and the fixtures publish pages on the
     * one these tests use. Every page created here therefore lives under a slug
     * prefix of its own, and every automation is scoped to it.
     */
    private string $prefix = '';

    /** @var list<int> */
    private array $pageIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = 'pa'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach ($this->pageIds as $pageId) {
            $this->entityManager->getConnection()->executeStatement('DELETE FROM page WHERE id = :id', ['id' => $pageId]);
        }

        $this->pageIds = [];
        parent::tearDown();
    }

    public function testAPublishedPageBecomesAScheduledCampaign(): void
    {
        $audience = $this->createAudience();
        $automation = $this->automation($audience);
        $page = $this->createPage('blog/hello', publishedAt: '-10 minutes');

        self::assertSame(1, $this->runTriggers());

        $publishedAt = $page->publishedAt;
        self::assertInstanceOf(DateTime::class, $publishedAt);

        $campaign = $this->onlyCampaignOf($automation);
        self::assertSame('New article: Hello', $campaign->subject);
        self::assertSame('Read [Hello]('.$this->url('blog/hello').').', $campaign->bodyMarkdown);
        self::assertSame(CampaignStatus::Scheduled, $campaign->status);
        self::assertSame(
            $publishedAt->modify('+1440 minutes')->format('Y-m-d H:i'),
            $campaign->scheduledAt?->format('Y-m-d H:i'),
            'the delay counts from the publication, not from the tick that noticed it',
        );
        self::assertSame($page->id, $campaign->triggerSubjectId);
    }

    /** What the merge buys the page side: a sequence, not a single announcement. */
    public function testEveryStepIsScheduledFromTheOneBeforeIt(): void
    {
        $audience = $this->createAudience();
        $automation = $this->automation($audience, steps: [
            ['delay' => 0, 'subject' => 'Out now: {{ page.h1 }}'],
            ['delay' => 4320, 'subject' => 'Did you read {{ page.h1 }}?'],
        ]);
        $page = $this->createPage('blog/hello', publishedAt: '-10 minutes');

        self::assertSame(2, $this->runTriggers());

        $publishedAt = $page->publishedAt;
        self::assertInstanceOf(DateTime::class, $publishedAt);

        $campaigns = $this->campaignsOf($automation);
        self::assertCount(2, $campaigns);
        self::assertSame('Out now: Hello', $campaigns[0]->subject);
        self::assertSame('Did you read Hello?', $campaigns[1]->subject);
        self::assertSame(
            $publishedAt->modify('+4320 minutes')->format('Y-m-d H:i'),
            $campaigns[1]->scheduledAt?->format('Y-m-d H:i'),
            'the second step waits its own delay after the first',
        );

        // One page, two mails, two names in the report.
        self::assertNotSame($campaigns[0]->slug, $campaigns[1]->slug);
    }

    /**
     * Seventeen locale versions of an article are seventeen pages, so a page
     * automation produces seventeen campaigns — all of them addressed to the
     * same `recipientWhen`. Left alone, every reader gets the same article once
     * per language.
     */
    public function testEachLanguageVersionOnlyReachesItsOwnReaders(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'fr@example.tld', locale: 'fr');
        $this->createContact($audience, 'de@example.tld', locale: 'de');
        $automation = $this->automation($audience, activeFrom: new DateTimeImmutable('-3 days'));

        $this->createPage('blog/bonjour', publishedAt: '-2 days', locale: 'fr');
        $this->createPage('blog/hallo', publishedAt: '-2 days', locale: 'de');

        $report = $this->tick();
        self::assertSame(2, $report['scheduled']);

        $segments = array_map(
            static fn (Campaign $campaign): array => $campaign->segment,
            $this->campaignsOf($automation),
        );

        self::assertSame([
            [['field' => 'locale', 'op' => '=', 'value' => 'fr']],
            [['field' => 'locale', 'op' => '=', 'value' => 'de']],
        ], $segments);

        // What the readers actually get: one mail each. Unnarrowed, the two
        // campaigns would reach both of them — the same article twice, once per
        // language.
        self::assertEmailCount(2);
    }

    /** The automation's own rule is kept, and narrowed — never replaced or widened. */
    public function testTheLocaleIsAndedOntoTheAutomationsOwnRule(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'fr@example.tld', locale: 'fr');
        $this->createContact($audience, 'de@example.tld', locale: 'de');
        $automation = $this->automation($audience, recipientWhen: ['any' => [
            ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek'],
            ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek-VIP'],
        ]]);

        $this->createPage('blog/bonjour', publishedAt: '-10 minutes', locale: 'fr');

        self::assertSame(1, $this->runTriggers());

        self::assertSame([
            ['any' => [
                ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek'],
                ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek-VIP'],
            ]],
            ['field' => 'locale', 'op' => '=', 'value' => 'fr'],
        ], $this->onlyCampaignOf($automation)->segment);
    }

    /** An audience read in one language has nothing to disambiguate. */
    public function testASingleLanguageAudienceKeepsItsRuleUntouched(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'fr@example.tld', locale: 'fr');
        $automation = $this->automation($audience);

        $this->createPage('blog/bonjour', publishedAt: '-10 minutes', locale: 'fr');

        self::assertSame(1, $this->runTriggers());
        self::assertSame([], $this->onlyCampaignOf($automation)->segment);
    }

    public function testTheSamePageIsOnlyEverMailedOnce(): void
    {
        $audience = $this->createAudience();
        $this->automation($audience);
        $this->createPage('blog/hello', publishedAt: '-10 minutes');

        self::assertSame(1, $this->runTriggers());
        self::assertSame(0, $this->runTriggers());
    }

    public function testPagesOutsideTheRuleAreLeftAlone(): void
    {
        $audience = $this->createAudience();
        $this->automation($audience, triggerWhen: [
            ['field' => 'slug', 'op' => 'startsWith', 'value' => $this->prefix.'/blog/'],
        ]);
        $this->createPage('legal/terms', publishedAt: '-10 minutes');
        $this->createPage('blog/hello', publishedAt: '-10 minutes');

        self::assertSame(1, $this->runTriggers());
    }

    public function testAnotherHostIsNotWatched(): void
    {
        $audience = $this->createAudience();
        $this->automation($audience, hosts: ['admin-block-editor.test']);
        $this->createPage('blog/hello', publishedAt: '-10 minutes');

        self::assertSame(0, $this->runTriggers());
    }

    public function testADisabledAutomationPicksNothingUp(): void
    {
        $audience = $this->createAudience();
        $automation = $this->automation($audience);
        $automation->enabled = false;

        $this->entityManager->flush();

        $this->createPage('blog/hello', publishedAt: '-10 minutes');

        self::assertSame(0, $this->runTriggers());
    }

    /** Steps are what it sends; without them there is nothing to mark as handled. */
    public function testAnAutomationWithoutStepsLeavesItsSubjectsWaiting(): void
    {
        $audience = $this->createAudience();
        $automation = $this->automation($audience, steps: []);
        $this->createPage('blog/hello', publishedAt: '-10 minutes');

        self::assertSame(0, $this->runTriggers());

        $step = new AutomationStep();
        $step->position = 0;
        $step->delayMinutes = 0;
        $step->subject = 'Late but here: {{ page.h1 }}';
        $step->bodyMarkdown = 'Body.';

        $automation->addStep($step);
        $this->entityManager->flush();

        self::assertSame(1, $this->runTriggers(), 'the page was still waiting for the steps to exist');
    }

    /**
     * A rule nobody can fix from the tick — hand-edited in the database, or left
     * behind by a grammar change. It must cost that automation its run, not the run.
     */
    public function testAnUnusableRuleDoesNotTakeTheTickDownWithIt(): void
    {
        $audience = $this->createAudience();
        $broken = $this->automation($audience);
        $broken->triggerWhen = [['field' => 'nope', 'op' => '=', 'value' => 'x']];

        $this->entityManager->flush();

        $this->automation($this->createAudience());
        $this->createPage('blog/hello', publishedAt: '-10 minutes');

        self::assertSame(1, $this->runTriggers(), 'the healthy automation still ran');
    }

    /** The guard that makes an automation safe to switch on over an existing site. */
    public function testTheBackCatalogueIsNeverMailed(): void
    {
        $audience = $this->createAudience();
        $this->automation($audience, activeFrom: new DateTimeImmutable('-1 hour'));
        $this->createPage('blog/old', publishedAt: '-1 year');

        self::assertSame(0, $this->runTriggers());
    }

    public function testAPageScheduledForLaterWaitsForItsPublication(): void
    {
        $audience = $this->createAudience();
        $this->automation($audience);
        $page = $this->createPage('blog/embargo', publishedAt: '+1 day');

        self::assertSame(0, $this->runTriggers());

        $page->publishedAt = new DateTime('-1 minute');
        $this->entityManager->flush();

        self::assertSame(1, $this->runTriggers());
    }

    public function testUnpublishingDuringTheDelayCancelsTheMail(): void
    {
        $audience = $this->createAudience();
        $automation = $this->automation($audience);
        $page = $this->createPage('blog/oops', publishedAt: '-10 minutes');

        $this->runTriggers();
        $campaignId = $this->onlyCampaignOf($automation)->id;

        $page->publishedAt = new DateTime('+1 year');
        $this->entityManager->flush();

        self::assertSame(1, $this->runner()->cancelStale());
        self::assertNull($this->entityManager->getRepository(Campaign::class)->find($campaignId));

        // The marker went with it, so a proper publication still gets its mail.
        $page->publishedAt = new DateTime('-1 minute');
        $this->entityManager->flush();

        self::assertSame(1, $this->runTriggers());
    }

    /** Past arming the recipients are frozen and some mails are already out. */
    public function testAnArmedCampaignIsNotCancelled(): void
    {
        $audience = $this->createAudience();
        $automation = $this->automation($audience, steps: [['delay' => 0, 'subject' => 'New article: {{ page.h1 }}']]);
        $this->createContact($audience, 'reader@example.tld');
        $page = $this->createPage('blog/hello', publishedAt: '-10 minutes');

        $this->tick();
        $campaign = $this->onlyCampaignOf($automation);
        self::assertSame(CampaignStatus::Sent, $campaign->status);

        $page->publishedAt = new DateTime('+1 year');
        $this->entityManager->flush();

        self::assertSame(0, $this->runner()->cancelStale());
        self::assertNotNull($this->entityManager->getRepository(Campaign::class)->find($campaign->id));
    }

    /**
     * The half-sent sequence: the article was announced, then unpublished before
     * the follow-up. That step goes, but the marker stays — re-publishing must
     * not announce to everyone a second time.
     */
    public function testUnpublishingAfterTheFirstStepKeepsTheSubjectHandled(): void
    {
        $audience = $this->createAudience();
        $automation = $this->automation($audience, steps: [
            ['delay' => 0, 'subject' => 'Out now: {{ page.h1 }}'],
            ['delay' => 4320, 'subject' => 'Did you read {{ page.h1 }}?'],
        ]);
        $this->createContact($audience, 'reader@example.tld');
        $page = $this->createPage('blog/hello', publishedAt: '-10 minutes');

        $this->tick();

        $campaigns = $this->campaignsOf($automation);
        self::assertCount(2, $campaigns);
        self::assertSame(CampaignStatus::Sent, $campaigns[0]->status);
        self::assertSame(CampaignStatus::Scheduled, $campaigns[1]->status);

        [$sentId, $pendingId] = [$campaigns[0]->id, $campaigns[1]->id];

        $page->publishedAt = new DateTime('+1 year');
        $this->entityManager->flush();

        self::assertSame(1, $this->runner()->cancelStale(), 'only the step nobody received');
        self::assertNotNull($this->entityManager->getRepository(Campaign::class)->find($sentId));
        self::assertNull($this->entityManager->getRepository(Campaign::class)->find($pendingId));

        $page->publishedAt = new DateTime('-1 minute');
        $this->entityManager->flush();

        self::assertSame(0, $this->runTriggers(), 'the article was already announced');
    }

    /** Uninstalling a bundle is not a reason to cancel the mail its automations queued. */
    public function testAnAutomationWhoseSourceIsGoneCancelsNothing(): void
    {
        $audience = $this->createAudience();
        $automation = $this->automation($audience);
        $this->createPage('blog/hello', publishedAt: '-10 minutes');

        $this->runTriggers();
        $campaignId = $this->onlyCampaignOf($automation)->id;

        $automation->source = 'a-bundle-that-left';
        $this->entityManager->flush();

        self::assertSame(0, $this->runner()->cancelStale());
        self::assertNotNull($this->entityManager->getRepository(Campaign::class)->find($campaignId));
    }

    /**
     * The whole path in one run: the trigger step precedes arming, so a page
     * whose delay has already elapsed goes out in the pass that noticed it.
     */
    public function testTheMailCarriesTheArticleLinkAbsoluteAndTagged(): void
    {
        $audience = $this->createAudience();
        $audience->utmSource = 'newsletter';
        $this->createContact($audience, 'reader@example.tld');
        $this->automation($audience, activeFrom: new DateTimeImmutable('-3 days'));
        $this->createPage('blog/hello', publishedAt: '-2 days');

        $report = $this->tick();

        self::assertSame(1, $report['scheduled']);
        self::assertSame(1, $report['armed']);
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('New article: Hello', $email->getSubject());
        self::assertStringContainsString(
            $this->url('blog/hello').'?utm_source=newsletter&amp;utm_medium=email&amp;utm_campaign=',
            (string) $email->getHtmlBody(),
        );
    }

    public function testRecipientWhenNarrowsWhoHearsAboutIt(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $this->createContact($audience, 'subscriber@example.tld', tags: ['AmTrek']);
        $this->automation($audience, recipientWhen: [
            ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek'],
        ], steps: [['delay' => 0, 'subject' => 'New article: {{ page.h1 }}']]);
        $this->createPage('blog/hello', publishedAt: '-10 minutes');

        $this->tick();

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('subscriber@example.tld', $email->getTo()[0]->getAddress());
    }

    /**
     * The case the merge was for: a publication is the trigger, and who hears
     * about it is a state of the contact, read when the mail goes out.
     */
    public function testRecipientWhenReadsAContactPropertyAsADuration(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'active@example.tld', customProperties: [
            'lastSeenAt' => new DateTimeImmutable('-2 days')->format(DateTimeInterface::ATOM),
        ]);
        $this->createContact($audience, 'dormant@example.tld', customProperties: [
            'lastSeenAt' => new DateTimeImmutable('-90 days')->format(DateTimeInterface::ATOM),
        ]);
        $this->automation($audience, recipientWhen: [
            ['field' => 'prop.lastSeenAt', 'op' => 'olderThan', 'value' => '30d'],
        ], steps: [['delay' => 0, 'subject' => 'We miss you — {{ page.h1 }}']]);
        $this->createPage('blog/hello', publishedAt: '-10 minutes');

        $this->tick();

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('dormant@example.tld', $email->getTo()[0]->getAddress());
        self::assertSame('We miss you — Hello', $email->getSubject());
    }

    /**
     * @param array<int, array<string, mixed>>              $triggerWhen
     * @param array<mixed>                                  $recipientWhen a rule as stored, so a group as well as a list
     * @param string[]                                      $hosts
     * @param list<array{delay: int, subject: string}>|null $steps
     */
    private function automation(
        Audience $audience,
        array $triggerWhen = [],
        array $recipientWhen = [],
        array $hosts = ['localhost.dev'],
        ?array $steps = null,
        ?DateTimeImmutable $activeFrom = null,
    ): Automation {
        return $this->createPageAutomation(
            $audience,
            hosts: $hosts,
            triggerWhen: [] === $triggerWhen
                ? [['field' => 'slug', 'op' => 'startsWith', 'value' => $this->prefix.'/']]
                : $triggerWhen,
            recipientWhen: $recipientWhen,
            steps: $steps,
            activeFrom: $activeFrom,
        );
    }

    private function createPage(string $slug, string $publishedAt, string $locale = ''): Page
    {
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->slug = $this->prefix.'/'.$slug;
        $page->h1 = 'Hello';
        $page->locale = $locale;
        $page->setSearchExcerpt('What it is about.');
        $page->publishedAt = new DateTime($publishedAt);

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        $pageId = $page->id;
        self::assertIsInt($pageId);
        $this->pageIds[] = $pageId;

        return $page;
    }

    private function url(string $slug): string
    {
        return 'https://localhost.dev/'.$this->prefix.'/'.$slug;
    }

    private function runner(): AutomationRunner
    {
        return self::getContainer()->get(AutomationRunner::class);
    }

    /** @return int the number of campaigns scheduled */
    private function runTriggers(): int
    {
        return $this->runner()->trigger(new DateTimeImmutable())['scheduled'];
    }

    private function onlyCampaignOf(Automation $automation): Campaign
    {
        $campaigns = $this->campaignsOf($automation);
        self::assertCount(1, $campaigns);

        return $campaigns[0];
    }

    /** @return list<Campaign> */
    private function campaignsOf(Automation $automation): array
    {
        /** @var list<Campaign> $campaigns */
        $campaigns = $this->entityManager->getRepository(Campaign::class)
            ->findBy(['automation' => $automation], ['id' => 'ASC']);

        return $campaigns;
    }

    /** @return array<string, int> */
    private function tick(): array
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        $tester = new CommandTester(new Application($kernel)->find('pw:newsletter:tick'));
        $tester->execute(['--format' => 'agent']);
        $tester->assertCommandIsSuccessful();

        $decoded = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($decoded);

        /** @var array<string, int> $decoded */
        return $decoded;
    }
}
