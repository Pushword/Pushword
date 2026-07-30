<?php

namespace Pushword\Newsletter\Tests\Content;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Newsletter\Content\ContentTriggerRunner;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\ContentTrigger;
use Pushword\Newsletter\Enum\CampaignStatus;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Email;

#[Group('integration')]
final class ContentTriggerRunnerTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    /**
     * A trigger watches a whole host, and the fixtures publish pages on the one
     * these tests use. Every page created here therefore lives under a slug
     * prefix of its own, and every trigger is scoped to it.
     */
    private string $prefix = '';

    /** @var list<int> */
    private array $pageIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = 'ct'.bin2hex(random_bytes(4));
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
        $trigger = $this->trigger($audience);
        $page = $this->createPage('blog/hello', publishedAt: '-10 minutes');

        self::assertSame(1, $this->runTriggers()['triggered']);

        $publishedAt = $page->getPublishedAt();
        self::assertInstanceOf(DateTime::class, $publishedAt);

        $campaign = $this->campaignOf($trigger);
        self::assertSame('New article: Hello', $campaign->getSubject());
        self::assertSame('Read [Hello]('.$this->url('blog/hello').').', $campaign->getBodyMarkdown());
        self::assertSame(CampaignStatus::Scheduled, $campaign->getStatus());
        self::assertSame(
            $publishedAt->modify('+1440 minutes')->format('Y-m-d H:i'),
            $campaign->getScheduledAt()?->format('Y-m-d H:i'),
            'the delay counts from the publication, not from the tick that noticed it',
        );
    }

    public function testTheSamePageIsOnlyEverMailedOnce(): void
    {
        $audience = $this->createAudience();
        $this->trigger($audience);
        $this->createPage('blog/hello', publishedAt: '-10 minutes');

        self::assertSame(1, $this->runTriggers()['triggered']);
        self::assertSame(0, $this->runTriggers()['triggered']);
    }

    public function testPagesOutsideTheRuleAreLeftAlone(): void
    {
        $audience = $this->createAudience();
        $this->trigger($audience, pageWhen: [
            ['field' => 'slug', 'op' => 'startsWith', 'value' => $this->prefix.'/blog/'],
        ]);
        $this->createPage('legal/terms', publishedAt: '-10 minutes');
        $this->createPage('blog/hello', publishedAt: '-10 minutes');

        self::assertSame(1, $this->runTriggers()['triggered']);
    }

    public function testAnotherHostIsNotWatched(): void
    {
        $audience = $this->createAudience();
        $this->trigger($audience, hosts: ['admin-block-editor.test']);
        $this->createPage('blog/hello', publishedAt: '-10 minutes');

        self::assertSame(0, $this->runTriggers()['triggered']);
    }

    public function testADisabledTriggerPicksNothingUp(): void
    {
        $audience = $this->createAudience();
        $trigger = $this->trigger($audience);
        $trigger->setEnabled(false);

        $this->entityManager->flush();

        $this->createPage('blog/hello', publishedAt: '-10 minutes');

        self::assertSame(0, $this->runTriggers()['triggered']);
    }

    /**
     * A rule nobody can fix from the tick — hand-edited in the database, or left
     * behind by a grammar change. It must cost that trigger its run, not the run.
     */
    public function testAnUnusableRuleDoesNotTakeTheTickDownWithIt(): void
    {
        $audience = $this->createAudience();
        $broken = $this->trigger($audience);
        $broken->setPageWhen([['field' => 'nope', 'op' => '=', 'value' => 'x']]);

        $this->entityManager->flush();

        $this->trigger($this->createAudience());
        $this->createPage('blog/hello', publishedAt: '-10 minutes');

        self::assertSame(1, $this->runTriggers()['triggered'], 'the healthy trigger still ran');
    }

    /** The guard that makes a trigger safe to switch on over an existing site. */
    public function testTheBackCatalogueIsNeverMailed(): void
    {
        $audience = $this->createAudience();
        $this->trigger($audience, triggerFrom: new DateTimeImmutable('-1 hour'));
        $this->createPage('blog/old', publishedAt: '-1 year');

        self::assertSame(0, $this->runTriggers()['triggered']);
    }

    public function testAPageScheduledForLaterWaitsForItsPublication(): void
    {
        $audience = $this->createAudience();
        $this->trigger($audience);
        $page = $this->createPage('blog/embargo', publishedAt: '+1 day');

        self::assertSame(0, $this->runTriggers()['triggered']);

        $page->setPublishedAt(new DateTime('-1 minute'));
        $this->entityManager->flush();

        self::assertSame(1, $this->runTriggers()['triggered']);
    }

    public function testUnpublishingDuringTheDelayCancelsTheMail(): void
    {
        $audience = $this->createAudience();
        $trigger = $this->trigger($audience);
        $page = $this->createPage('blog/oops', publishedAt: '-10 minutes');

        $this->runTriggers();
        $campaignId = $this->campaignOf($trigger)->id;

        $page->setPublishedAt(new DateTime('+1 year'));
        $this->entityManager->flush();

        self::assertSame(1, $this->runTriggers()['cancelled']);
        self::assertNull($this->entityManager->getRepository(Campaign::class)->find($campaignId));

        // The marker went with it, so a proper publication still gets its mail.
        $page->setPublishedAt(new DateTime('-1 minute'));
        $this->entityManager->flush();

        self::assertSame(1, $this->runTriggers()['triggered']);
    }

    /** Past arming the recipients are frozen and some mails are already out. */
    public function testAnArmedCampaignIsNotCancelled(): void
    {
        $audience = $this->createAudience();
        $trigger = $this->trigger($audience, delayMinutes: 0);
        $this->createContact($audience, 'reader@example.tld');
        $page = $this->createPage('blog/hello', publishedAt: '-10 minutes');

        $this->tick();
        $campaign = $this->campaignOf($trigger);
        self::assertSame(CampaignStatus::Sent, $campaign->getStatus());

        $page->setPublishedAt(new DateTime('+1 year'));
        $this->entityManager->flush();

        self::assertSame(0, $this->runTriggers()['cancelled']);
        self::assertNotNull($this->entityManager->getRepository(Campaign::class)->find($campaign->id));
    }

    /**
     * The whole path in one run: the trigger step precedes arming, so a page
     * whose delay has already elapsed goes out in the pass that noticed it.
     */
    public function testTheMailCarriesTheArticleLinkAbsoluteAndTagged(): void
    {
        $audience = $this->createAudience();
        $audience->setUtmSource('newsletter');
        $this->createContact($audience, 'reader@example.tld');
        $this->trigger($audience, triggerFrom: new DateTimeImmutable('-3 days'));
        $this->createPage('blog/hello', publishedAt: '-2 days');

        $report = $this->tick();

        self::assertSame(1, $report['triggered']);
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

    public function testTheSegmentNarrowsWhoHearsAboutIt(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $this->createContact($audience, 'subscriber@example.tld', tags: ['AmTrek']);
        $this->trigger($audience, segment: [
            ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek'],
        ], delayMinutes: 0);
        $this->createPage('blog/hello', publishedAt: '-10 minutes');

        $this->tick();

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('subscriber@example.tld', $email->getTo()[0]->getAddress());
    }

    /**
     * @param array<int, array<string, mixed>> $pageWhen
     * @param array<int, array<string, mixed>> $segment
     * @param string[]                         $hosts
     */
    private function trigger(
        Audience $audience,
        array $pageWhen = [],
        array $segment = [],
        array $hosts = ['localhost.dev'],
        int $delayMinutes = 1440,
        ?DateTimeImmutable $triggerFrom = null,
    ): ContentTrigger {
        return $this->createContentTrigger(
            $audience,
            hosts: $hosts,
            pageWhen: [] === $pageWhen
                ? [['field' => 'slug', 'op' => 'startsWith', 'value' => $this->prefix.'/']]
                : $pageWhen,
            segment: $segment,
            delayMinutes: $delayMinutes,
            triggerFrom: $triggerFrom,
        );
    }

    private function createPage(string $slug, string $publishedAt): Page
    {
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->setSlug($this->prefix.'/'.$slug);
        $page->setH1('Hello');
        $page->setSearchExcerpt('What it is about.');
        $page->setPublishedAt(new DateTime($publishedAt));

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

    /** @return array{triggered: int, cancelled: int} */
    private function runTriggers(): array
    {
        return self::getContainer()->get(ContentTriggerRunner::class)->run(new DateTimeImmutable());
    }

    private function campaignOf(ContentTrigger $trigger): Campaign
    {
        $audience = $trigger->getAudience();
        self::assertInstanceOf(Audience::class, $audience);

        $campaigns = $this->entityManager->getRepository(Campaign::class)->findBy(['audience' => $audience]);
        self::assertCount(1, $campaigns);

        return $campaigns[0];
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
