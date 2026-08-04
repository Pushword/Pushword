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
use Pushword\Newsletter\Trigger\Source\ContactTriggerSource;
use Pushword\Newsletter\Trigger\Source\PageTriggerSource;
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
        $audience = new Audience();
        $audience->slug = 'test-'.bin2hex(random_bytes(6));
        $audience->name = 'Test audience';
        $audience->mainHost = $mainHost;
        $audience->fromName = 'Test';
        $audience->fromEmail = 'newsletter@localhost.dev';
        $audience->requireDoubleOptIn = $requireDoubleOptIn;
        $audience->interests = $interests;
        $audience->rateSeconds = $rateSeconds;

        $this->entityManager->persist($audience);
        $this->entityManager->flush();

        $audienceId = $audience->id;
        self::assertIsInt($audienceId);
        $this->trackAudience($audienceId);

        return $audience;
    }

    /** Purge an audience created elsewhere — over the API, say — with the others. */
    protected function trackAudience(int $audienceId): void
    {
        $this->audienceIds[] = $audienceId;
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
        $contact->name = 'Test';
        $contact->locale = $locale;
        $contact->setTags($tags);
        $contact->customProperties = $customProperties;
        $contact->optIn(! $subscribed);

        if (null !== $registeredAt) {
            // createdAt is a mutable datetime column (TimestampableTrait).
            $contact->createdAt = DateTime::createFromInterface($registeredAt);
        }

        $this->entityManager->persist($contact);
        $this->entityManager->flush();

        return $contact;
    }

    /** Somebody the site can only phone: consented, stored, never a recipient. */
    protected function createPhoneContact(Audience $audience, string $phone, bool $subscribed = true): Contact
    {
        $contact = new Contact($audience, null, $phone);
        $contact->name = 'Test';
        $contact->locale = 'en';
        $contact->optIn(! $subscribed);

        $this->entityManager->persist($contact);
        $this->entityManager->flush();

        return $contact;
    }

    /** @param array<mixed> $segment */
    protected function createCampaign(Audience $audience, array $segment = [], string $subject = 'Hello'): Campaign
    {
        $campaign = new Campaign();
        $campaign->audience = $audience;
        $campaign->subject = $subject;
        $campaign->bodyMarkdown = 'Hello **%name%**.';
        $campaign->segment = $segment;

        $this->entityManager->persist($campaign);
        $this->entityManager->flush();

        return $campaign;
    }

    /**
     * A drip on the contact source: the shape every automation had before there
     * were others.
     *
     * @param list<array{delay: int, subject: string}> $steps
     * @param array<int, array<string, mixed>>         $triggerWhen
     * @param array<int, array<string, mixed>>         $stopWhen
     */
    protected function createAutomation(Audience $audience, array $steps, array $triggerWhen = [], array $stopWhen = []): Automation
    {
        $automation = new Automation();
        $automation->audience = $audience;
        $automation->name = 'Welcome';
        $automation->source = ContactTriggerSource::NAME;
        $automation->triggerWhen = $triggerWhen;
        $automation->stopWhen = $stopWhen;
        $automation->activeFrom = new DateTimeImmutable('-1 year');

        return $this->persistAutomation($automation, $steps);
    }

    /**
     * A broadcast on the page source — what a content trigger used to be, now
     * one automation among the others.
     *
     * @param string[]                                 $hosts
     * @param array<mixed>                             $triggerWhen
     * @param array<mixed>                             $recipientWhen
     * @param list<array{delay: int, subject: string}> $steps
     */
    protected function createPageAutomation(
        Audience $audience,
        array $hosts = ['localhost.dev'],
        array $triggerWhen = [],
        array $recipientWhen = [],
        ?array $steps = null,
        ?DateTimeImmutable $activeFrom = null,
    ): Automation {
        $automation = new Automation();
        $automation->audience = $audience;
        $automation->name = 'New articles';
        $automation->source = PageTriggerSource::NAME;
        $automation->hosts = $hosts;
        $automation->triggerWhen = $triggerWhen;
        $automation->recipientWhen = $recipientWhen;
        $automation->activeFrom = $activeFrom ?? new DateTimeImmutable('-1 hour');

        return $this->persistAutomation(
            $automation,
            $steps ?? [['delay' => 1440, 'subject' => 'New article: {{ page.h1 }}']],
            'Read [{{ page.h1 }}]({{ page.url }}).'
        );
    }

    /** @param list<array{delay: int, subject: string}> $steps */
    private function persistAutomation(Automation $automation, array $steps, string $body = 'Step body.'): Automation
    {
        foreach ($steps as $position => $step) {
            $automationStep = new AutomationStep();
            $automationStep->position = $position;
            $automationStep->delayMinutes = $step['delay'];
            $automationStep->subject = $step['subject'];
            $automationStep->bodyMarkdown = $body;

            $automation->addStep($automationStep);
        }

        $this->entityManager->persist($automation);
        $this->entityManager->flush();

        return $automation;
    }

    private function purge(int $audienceId): void
    {
        $connection = $this->entityManager->getConnection();

        $statements = [
            'DELETE FROM newsletter_trigger_log WHERE automation_id IN (SELECT id FROM newsletter_automation WHERE audience_id = :id)',
            'DELETE FROM newsletter_automation_delivery WHERE contact_id IN (SELECT id FROM newsletter_contact WHERE audience_id = :id)',
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
