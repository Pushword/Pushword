<?php

namespace Pushword\Newsletter\Tests;

use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\AutomationStep;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Entity\ContentTrigger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Every test owns one audience with a unique slug and drops everything hanging
 * from it afterwards. Repository lookups the tick relies on (due campaigns,
 * enabled automations) are global, so leftovers from one test would otherwise
 * be picked up by the next.
 */
abstract class AbstractNewsletterTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected EntityManagerInterface $entityManager;

    /** @var list<int> */
    private array $audienceIds = [];

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
    }

    protected function tearDown(): void
    {
        foreach ($this->audienceIds as $audienceId) {
            $this->purge($audienceId);
        }

        $this->audienceIds = [];
        $this->entityManager->clear();
        parent::tearDown();
    }

    /**
     * A CSRF token, fetched the way a browser gets one. It is issued per session
     * under one id, so whichever audience opens the session, the token fits any
     * subscription posted with the same client afterwards.
     */
    protected function csrfToken(string $audienceSlug): string
    {
        $this->client->request(Request::METHOD_POST, '/newsletter/form?audiences='.$audienceSlug);

        $html = (string) $this->client->getResponse()->getContent();
        self::assertSame(1, preg_match('/name="_token" value="([^"]+)"/', $html, $matches));

        return $matches[1];
    }

    /** @param string[] $interests */
    protected function createAudience(bool $requireDoubleOptIn = true, array $interests = [], int $rateSeconds = 30, string $mainHost = 'localhost.dev'): Audience
    {
        $audience = new Audience()
            ->setSlug('test-'.bin2hex(random_bytes(6)))
            ->setName('Test audience')
            ->setMainHost($mainHost)
            ->setFromName('Test')
            ->setFromEmail('newsletter@localhost.dev')
            ->setRequireDoubleOptIn($requireDoubleOptIn)
            ->setInterests($interests)
            ->setRateSeconds($rateSeconds);

        $this->entityManager->persist($audience);
        $this->entityManager->flush();

        $audienceId = $audience->id;
        self::assertIsInt($audienceId);
        $this->audienceIds[] = $audienceId;

        return $audience;
    }

    /**
     * @param string[]             $tags
     * @param array<string, mixed> $customProperties
     */
    protected function createContact(
        Audience $audience,
        string $email,
        array $tags = [],
        array $customProperties = [],
        bool $subscribed = true,
        ?DateTimeImmutable $registeredAt = null,
        string $locale = 'en',
    ): Contact {
        $contact = new Contact($audience, $email);
        $contact->setName('Test');
        $contact->setLocale($locale);
        $contact->setTags($tags);
        $contact->setCustomProperties($customProperties);
        $contact->optIn(! $subscribed);

        if (null !== $registeredAt) {
            // createdAt is a mutable datetime column (TimestampableTrait).
            $contact->createdAt = DateTime::createFromInterface($registeredAt);
        }

        $this->entityManager->persist($contact);
        $this->entityManager->flush();

        return $contact;
    }

    /** @param array<mixed> $segment */
    protected function createCampaign(Audience $audience, array $segment = [], string $subject = 'Hello'): Campaign
    {
        $campaign = new Campaign()
            ->setAudience($audience)
            ->setSubject($subject)
            ->setBodyMarkdown('Hello **%name%**.')
            ->setSegment($segment);

        $this->entityManager->persist($campaign);
        $this->entityManager->flush();

        return $campaign;
    }

    /**
     * @param list<array{delay: int, subject: string}> $steps
     * @param array<int, array<string, mixed>>         $enrollWhen
     * @param array<int, array<string, mixed>>         $stopWhen
     */
    protected function createAutomation(Audience $audience, array $steps, array $enrollWhen = [], array $stopWhen = []): Automation
    {
        $automation = new Automation()
            ->setAudience($audience)
            ->setName('Welcome')
            ->setEnrollWhen($enrollWhen)
            ->setStopWhen($stopWhen)
            ->setEnrollFrom(new DateTimeImmutable('-1 year'));

        foreach ($steps as $position => $step) {
            $automation->addStep(new AutomationStep()
                ->setPosition($position)
                ->setDelayMinutes($step['delay'])
                ->setSubject($step['subject'])
                ->setBodyMarkdown('Step body.'));
        }

        $this->entityManager->persist($automation);
        $this->entityManager->flush();

        return $automation;
    }

    /**
     * @param string[]     $hosts
     * @param array<mixed> $pageWhen
     * @param array<mixed> $segment
     */
    protected function createContentTrigger(
        Audience $audience,
        array $hosts = ['localhost.dev'],
        array $pageWhen = [],
        array $segment = [],
        int $delayMinutes = 1440,
        string $subjectTemplate = 'New article: {{ page.h1 }}',
        string $bodyTemplate = 'Read [{{ page.h1 }}]({{ page.url }}).',
        ?DateTimeImmutable $triggerFrom = null,
    ): ContentTrigger {
        $trigger = new ContentTrigger()
            ->setAudience($audience)
            ->setName('New articles')
            ->setHosts($hosts)
            ->setPageWhen($pageWhen)
            ->setSegment($segment)
            ->setDelayMinutes($delayMinutes)
            ->setSubjectTemplate($subjectTemplate)
            ->setBodyTemplate($bodyTemplate)
            ->setTriggerFrom($triggerFrom ?? new DateTimeImmutable('-1 hour'));

        $this->entityManager->persist($trigger);
        $this->entityManager->flush();

        return $trigger;
    }

    private function purge(int $audienceId): void
    {
        $connection = $this->entityManager->getConnection();

        $statements = [
            'DELETE FROM newsletter_content_trigger_log WHERE trigger_id IN (SELECT id FROM newsletter_content_trigger WHERE audience_id = :id)',
            'DELETE FROM newsletter_content_trigger WHERE audience_id = :id',
            'DELETE FROM newsletter_enrollment WHERE contact_id IN (SELECT id FROM newsletter_contact WHERE audience_id = :id)',
            'DELETE FROM newsletter_campaign_recipient WHERE campaign_id IN (SELECT id FROM newsletter_campaign WHERE audience_id = :id)',
            'DELETE FROM newsletter_automation_step WHERE automation_id IN (SELECT id FROM newsletter_automation WHERE audience_id = :id)',
            'DELETE FROM newsletter_automation WHERE audience_id = :id',
            'DELETE FROM newsletter_campaign WHERE audience_id = :id',
            'DELETE FROM newsletter_contact WHERE audience_id = :id',
            'DELETE FROM newsletter_audience WHERE id = :id',
        ];

        foreach ($statements as $sql) {
            $connection->executeStatement($sql, ['id' => $audienceId]);
        }
    }
}
