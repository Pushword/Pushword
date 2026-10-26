<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Statistics;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Click\ClickTracker;
use Pushword\Newsletter\Click\QualifyClick;
use Pushword\Newsletter\Delivery\DeliveryEventRecorder;
use Pushword\Newsletter\Delivery\DeliveryNotification;
use Pushword\Newsletter\Entity\CampaignRecipient;
use Pushword\Newsletter\Entity\ClickEvent;
use Pushword\Newsletter\Entity\ContactEvent;
use Pushword\Newsletter\Entity\DeliveryEvent;
use Pushword\Newsletter\Entity\DeliveryMessage;
use Pushword\Newsletter\Enum\ContactStatus;
use Pushword\Newsletter\Enum\DeliveryEventKind;
use Pushword\Newsletter\Enum\RecipientState;
use Pushword\Newsletter\Event\QualifyDeliveryCoverage;
use Pushword\Newsletter\Repository\ClickEventRepository;
use Pushword\Newsletter\Service\CampaignSender;
use Pushword\Newsletter\Service\ContactManager;
use Pushword\Newsletter\Service\NewsletterMailer;
use Pushword\Newsletter\Statistics\LocalStatisticsProvider;
use Pushword\Newsletter\Statistics\MessageObservations;
use Pushword\Newsletter\Statistics\Report;
use Pushword\Newsletter\Statistics\ReportQuery;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Pushword\Newsletter\Utm\UtmTag;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Group('integration')]
final class DeliveryStatisticsTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    public function testObservationsCoverMoreMessagesThanOneQueryBinds(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience);
        $messages = [];
        for ($index = 0; $index <= MessageObservations::CHUNK; ++$index) {
            $messages[] = $message = new DeliveryMessage($contact, true, $campaign);
            $this->entityManager->persist($message);
        }

        foreach ([$messages[0], $messages[MessageObservations::CHUNK]] as $index => $message) {
            $click = ClickEvent::onCampaign($contact, $campaign, 'https://localhost.dev/article');
            $click->message = $message;
            $unsubscribe = ContactEvent::unsubscribed($contact, 'test');
            $unsubscribe->message = $message;
            $now = new DateTimeImmutable();
            $this->entityManager->persist($click);
            $this->entityManager->persist($unsubscribe);
            $this->entityManager->persist(new DeliveryEvent($message, hash('sha256', 'chunk-'.$index), 'test', 'chunk-'.$index, DeliveryEventKind::RemoteAccepted, $now, '', $now));
        }

        $this->entityManager->flush();
        $observations = new MessageObservations($this->entityManager, $messages);
        foreach ([$messages[0], $messages[MessageObservations::CHUNK]] as $message) {
            self::assertCount(1, $observations->clicks[$message->id ?? 0] ?? []);
            self::assertCount(1, $observations->deliveries[$message->id ?? 0] ?? []);
            self::assertCount(1, $observations->unsubscribes[$message->id ?? 0] ?? []);
        }

        self::assertCount(2, $observations->clicks);
    }

    public function testNoMessagesMeansNoObservations(): void
    {
        $observations = new MessageObservations($this->entityManager, []);

        self::assertSame([], $observations->clicks);
        self::assertSame([], $observations->deliveries);
        self::assertSame([], $observations->unsubscribes);
    }

    public function testTheSendPersistsItsMessageIdConsentSnapshotAndPreciseLinks(): void
    {
        $audience = $this->createAudience();
        $audience->clickTracking = true;
        $audience->utmSource = 'newsletter';

        $contact = $this->createContact($audience, 'tracked@example.tld');
        $contact->clickTrackingConsentAt = new DateTimeImmutable();

        $campaign = $this->createCampaign($audience);
        $campaign->bodyMarkdown = '[Article](https://localhost.dev/article)';

        $sender = self::getContainer()->get(CampaignSender::class);
        $sender->arm($campaign);
        self::assertSame(1, $sender->drain($campaign, 1));
        $message = $this->entityManager->getRepository(DeliveryMessage::class)->findOneBy(['campaign' => $campaign]);
        self::assertNotNull($message);
        self::assertNotNull($message->acceptedAt);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame($message->messageId, trim($email->getHeaders()->get('Message-ID')?->getBodyAsString() ?? '', '<> '));
        self::assertSame($message->reference, $email->getHeaders()->get('X-Pushword-Newsletter-Message')?->getBodyAsString());
        self::assertSame(['body'], $message->trackingScopes);
        $html = $email->getHtmlBody();
        self::assertIsString($html);
        self::assertStringContainsString('message='.$message->reference, $html);
        $payload = $this->payload($email);
        $decoded = self::getContainer()->get(ClickTracker::class)->decode($payload);
        self::assertNotNull($decoded);
        self::assertSame($message->reference, $decoded->messageReference);
        self::assertStringContainsString('utm_id='.$campaign->getUtmId(), $decoded->url);
        $audience->clickTracking = false;
        $contact->clickTrackingConsentAt = null;
        $this->entityManager->flush();
        $report = self::getContainer()->get(LocalStatisticsProvider::class)->campaign($campaign->id ?? 0, $this->query($audience->id));
        self::assertSame(1, $report->metric('trackedMessages')->value);
        self::assertSame(100.0, $report->rate('coverage')['value']);
        self::assertSame(0.0, $report->rate('clickRate')['value']);
        self::assertSame('unavailable', $report->metric('delivered')->state, 'No bounce does not prove delivery.');
        $this->client->request(Request::METHOD_GET, '/newsletter/c/'.$payload);
        self::assertResponseRedirects();
        self::assertSame(0, $this->entityManager->getRepository(ClickEvent::class)->count(['message' => $message]));
    }

    public function testUnrecordedConfirmationAndPreviewDoNotExposeTheLedgerHeader(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'unrecorded@example.tld');
        $mailer = self::getContainer()->get(NewsletterMailer::class);
        $mailer->sendConfirmation($contact);
        $mailer->sendTest($audience, 'Preview', 'Body', null, 'preview@example.tld', null);
        self::assertEmailCount(2);
        foreach ([0, 1] as $index) {
            $email = self::getMailerMessage($index);
            self::assertInstanceOf(Email::class, $email);
            self::assertFalse($email->getHeaders()->has('X-Pushword-Newsletter-Message'));
        }

        self::assertSame(0, $this->entityManager->getRepository(DeliveryMessage::class)->count([]));
    }

    public function testDeliveryEventsAreIdempotentAndKeepRemoteDatesAndReasons(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'events@example.tld');
        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Step']]);
        $message = self::getContainer()->get(NewsletterMailer::class)->sendStep($automation->getOrderedSteps()[0], $contact, 'Step', 'Body', occurrenceId: 42);
        $this->backdate($message, '2026-01-01');
        $recorder = self::getContainer()->get(DeliveryEventRecorder::class);
        $remote = new DeliveryNotification('provider', 'accepted-42', $message->reference, DeliveryEventKind::RemoteAccepted, new DateTimeImmutable('2026-01-03'), '250 accepted by destination');
        self::assertTrue($recorder->record($remote));
        self::assertFalse($recorder->record($remote));
        // A late-arriving earlier deferral must not overwrite the later success.
        self::assertTrue($recorder->record(new DeliveryNotification('provider', 'deferred-42', $message->reference, DeliveryEventKind::Deferred, new DateTimeImmutable('2026-01-02'), '451 retry later')));
        $query = new ReportQuery(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-02-01'), $audience->id, asOf: new DateTimeImmutable('2026-02-10'));
        $report = $this->reports($query)[0];
        self::assertSame(1, $report->metric('delivered')->value);
        self::assertSame(0, $report->metric('bounced')->value);
        self::assertSame(ContactStatus::Subscribed, $contact->status);
        $events = $this->entityManager->getRepository(DeliveryEvent::class)->findBy(['message' => $message]);
        self::assertCount(2, $events);
        self::assertSame('250 accepted by destination', $events[0]->reason);
        self::assertSame('2026-01-03', $events[0]->occurredAt->format('Y-m-d'));
    }

    public function testIncompleteDeliveryFactsDoNotBecomeRatesAndQualificationPrecedesReadingEvents(): void
    {
        $audience = $this->createAudience();
        $contacts = [];
        foreach (['one', 'two'] as $name) {
            $contacts[] = $this->createContact($audience, $name.'@example.tld');
        }

        $campaign = $this->createCampaign($audience)->markSending(2);
        foreach ($contacts as $contact) {
            self::getContainer()->get(NewsletterMailer::class)->sendCampaign($campaign, $contact);
            $campaign->incrementSent();
        }

        $campaign->markSent();
        $this->entityManager->flush();
        $messages = $this->entityManager->getRepository(DeliveryMessage::class)->findBy(['campaign' => $campaign]);
        foreach ($messages as $message) {
            $this->backdate($message, '2026-01-01');
        }

        $recorder = self::getContainer()->get(DeliveryEventRecorder::class);
        $recorder->record(new DeliveryNotification('provider', 'one', $messages[0]->reference, DeliveryEventKind::RemoteAccepted, new DateTimeImmutable('2026-01-02'), '250 accepted'));

        $provider = self::getContainer()->get(LocalStatisticsProvider::class);
        $query = new ReportQuery(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-11-01'), $audience->id, asOf: new DateTimeImmutable('2026-11-02'));
        $partial = $provider->campaign($campaign->id ?? 0, $query);
        self::assertSame(1, $partial->metric('delivered')->value);
        self::assertFalse($partial->metric('delivered')->complete);
        self::assertNull($partial->rate('deliveryRate')['value']);
        self::assertNull($partial->rate('clickRateDelivered')['denominatorMetric']);

        $watermark = new DateTimeImmutable('2026-11-01');
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(QualifyDeliveryCoverage::class, static function (QualifyDeliveryCoverage $event) use ($recorder, $messages, &$watermark): void {
            self::assertCount(2, $event->messages);
            $recorder->record(new DeliveryNotification('provider', 'two', $messages[1]->reference, DeliveryEventKind::RemoteAccepted, new DateTimeImmutable('2026-01-03'), '250 accepted'));
            $event->complete = true;
            $event->observedAt = $watermark;
            $event->notice = 'Complete collector snapshot';
        });
        foreach ([$provider->campaign($campaign->id ?? 0, $query), $this->reports($query)[0]] as $complete) {
            self::assertSame(2, $complete->metric('delivered')->value);
            self::assertTrue($complete->metric('delivered')->complete);
            self::assertSame(100.0, $complete->rate('deliveryRate')['value']);
            self::assertSame('2026-11-01', $complete->observedAt?->format('Y-m-d'));
            self::assertSame('Complete collector snapshot', $complete->notice);
        }

        $watermark = new DateTimeImmutable('2026-01-02');
        $snapshot = $provider->campaign($campaign->id ?? 0, $query);
        self::assertSame(1, $snapshot->metric('delivered')->value, 'Events beyond the durable collector watermark belong to a later snapshot.');
        self::assertTrue($snapshot->metric('delivered')->complete);
        self::assertSame(50.0, $snapshot->rate('deliveryRate')['value']);
    }

    public function testOnlyCompleteDeliveryCoverageCanProveZeroAndLegacySendsCannotBeQualified(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'zero@example.tld');
        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Step']]);
        $message = self::getContainer()->get(NewsletterMailer::class)->sendStep($automation->getOrderedSteps()[0], $contact, 'Step', 'Body');
        $this->backdate($message, '2026-01-01');
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(QualifyDeliveryCoverage::class, static function (QualifyDeliveryCoverage $event): void {
            $event->complete = true;
        });
        $query = new ReportQuery(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-11-01'), $audience->id, asOf: new DateTimeImmutable('2026-11-02'));
        $report = $this->reports($query)[0];
        self::assertSame(0, $report->metric('delivered')->value);
        self::assertTrue($report->metric('delivered')->complete);
        self::assertSame(0.0, $report->rate('deliveryRate')['value']);

        $legacy = $this->createCampaign($audience)->markSending(1)->incrementSent()->markSent();
        $this->entityManager->flush();
        $report = self::getContainer()->get(LocalStatisticsProvider::class)->campaign($legacy->id ?? 0, $query);
        self::assertFalse($report->metric('delivered')->complete);
        self::assertNull($report->metric('delivered')->value);
        self::assertNull($report->rate('deliveryRate')['value']);
    }

    public function testAnOlderMessageGetsItsBounceAndUnsubscribeAndResubscribeKeepsHistory(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'older@example.tld');
        $sender = self::getContainer()->get(CampaignSender::class);
        $older = $this->createCampaign($audience, subject: 'Older');
        $newer = $this->createCampaign($audience, subject: 'Newer');
        foreach ([$older, $newer] as $campaign) {
            $sender->arm($campaign);
            $sender->drain($campaign, 1);
        }

        $message = $this->entityManager->getRepository(DeliveryMessage::class)->findOneBy(['campaign' => $older]);
        self::assertNotNull($message);
        $this->client->request(Request::METHOD_POST, '/newsletter/unsubscribe/'.$contact->token.'?message='.$message->reference);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $older->unsubCount);
        self::assertSame(0, $newer->unsubCount);
        self::getContainer()->get(ContactManager::class)->resubscribe($contact);
        self::assertSame(ContactStatus::Subscribed, $contact->status);
        self::assertSame(1, $older->unsubCount, 'Historical opt-outs survive a later re-opt-in.');
        self::assertSame(1, $this->entityManager->getRepository(ContactEvent::class)->count(['message' => $message]));
        $failure = new DeliveryNotification('provider', 'bounce-old', $message->reference, DeliveryEventKind::Failed, new DateTimeImmutable(), '550 no such mailbox');
        $recorder = self::getContainer()->get(DeliveryEventRecorder::class);
        self::assertTrue($recorder->record($failure));
        self::assertFalse($recorder->record($failure));
        self::assertSame(1, $older->bounceCount);
        self::assertSame(0, $newer->bounceCount);
        self::assertSame(RecipientState::Bounced, $this->entityManager->getRepository(CampaignRecipient::class)->findOneBy(['campaign' => $older])?->state);
        self::assertSame(RecipientState::Sent, $this->entityManager->getRepository(CampaignRecipient::class)->findOneBy(['campaign' => $newer])?->state);
    }

    public function testAutomationIdentityWindowsOccurrencesAndScannerQualification(): void
    {
        $audience = $this->createAudience();
        $audience->clickTracking = true;
        $audience->utmSource = 'newsletter';

        $contact = $this->createContact($audience, 'reader@example.tld');
        $contact->clickTrackingConsentAt = new DateTimeImmutable();

        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'First']]);
        $step = $automation->getOrderedSteps()[0];
        $tag = UtmTag::forStep($automation, $step);
        $automation->name = 'Renamed editorial title';
        self::assertSame($tag->id, UtmTag::forStep($automation, $step)->id);
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addListener(QualifyClick::class, static function (QualifyClick $event): void {
            self::assertGreaterThan(0, $event->payload->contactId);
            $event->automated = 'scanner' === $event->request->headers->get('User-Agent');
            $event->method = 'test-agent-v1';
        });
        $mailer = self::getContainer()->get(NewsletterMailer::class);
        $message = $mailer->sendStep($step, $contact, 'Step', '[Link](https://localhost.dev/article)', occurrenceId: 10);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        $payload = $this->payload($email);
        $this->backdate($message, '2026-01-01');
        $second = $mailer->sendStep($step, $contact, 'Another occurrence', 'Body', occurrenceId: 11);
        $this->backdate($second, '2026-01-01');
        foreach ([['scanner', '2026-01-02'], ['reader', '2026-01-10']] as [$agent, $date]) {
            $this->client->request(Request::METHOD_GET, '/newsletter/c/'.$payload, server: ['HTTP_USER_AGENT' => $agent]);
            self::assertResponseRedirects();
            $click = $this->entityManager->getRepository(ClickEvent::class)->findOneBy(['message' => $message], ['id' => 'DESC']);
            self::assertNotNull($click);
            $this->entityManager->getConnection()->update('newsletter_click_event', ['clicked_at' => new DateTimeImmutable($date)->format('Y-m-d H:i:s')], ['id' => $click->id]);
            $this->entityManager->refresh($click);
        }

        $query = static fn (?int $days, string $basis = 'sent', string $group = 'automation'): ReportQuery => new ReportQuery(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-02-01'), $audience->id, $basis, $days, $group, new DateTimeImmutable('2026-02-10'));
        $week = $this->reports($query(7))[0];
        $month = $this->reports($query(30))[0];
        self::assertSame(1, $week->metric('clicks')->value);
        self::assertSame(0, $week->metric('filteredClicks')->value);
        self::assertSame(2, $month->metric('clicks')->value);
        self::assertSame(1, $month->metric('filteredClicks')->value);
        self::assertSame(1, $month->metric('clickers')->value);
        self::assertSame('test-agent-v1', $month->qualificationMethod);
        self::assertCount(1, $this->reports($query(null, group: 'step')));
        self::assertCount(2, $this->reports($query(null, group: 'occurrence')));
        self::assertTrue($month->mature);
        $eventPeriod = new ReportQuery(new DateTimeImmutable('2026-01-09'), new DateTimeImmutable('2026-01-11'), $audience->id, 'events', groupBy: 'automation', asOf: new DateTimeImmutable('2026-02-10'));
        $events = $this->reports($eventPeriod);
        self::assertCount(1, $events);
        self::assertSame(1, $events[0]->metric('clicks')->value);
        self::assertNull($events[0]->rate('clickRate')['value']);
    }

    public function testUnknownHistoricalCoverageDoesNotUseTodaysAudienceSetting(): void
    {
        $audience = $this->createAudience();
        $audience->clickTracking = true;

        $campaign = $this->createCampaign($audience)->markSending(1)->incrementSent()->markSent();
        $this->entityManager->flush();
        $report = self::getContainer()->get(LocalStatisticsProvider::class)->campaign($campaign->id ?? 0, $this->query($audience->id));
        self::assertSame('notMeasured', $report->metric('trackedMessages')->state);
        self::assertNull($report->rate('clickRate')['value']);
        self::assertNull($report->rate('coverage')['value']);
    }

    public function testMissingReferencesDoNotBlameTheLastCampaign(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'unattributed@example.tld');
        $campaign = $this->createCampaign($audience);
        $sender = self::getContainer()->get(CampaignSender::class);
        $sender->arm($campaign);
        $sender->drain($campaign, 1);

        $manager = self::getContainer()->get(ContactManager::class);
        $manager->unsubscribe($contact);
        self::assertSame(0, $campaign->unsubCount);
        $manager->resubscribe($contact);
        $manager->markBounced($contact);
        self::assertSame(0, $campaign->bounceCount);
        self::assertSame(RecipientState::Sent, $this->entityManager->getRepository(CampaignRecipient::class)->findOneBy(['campaign' => $campaign])?->state);
    }

    public function testPartialTrackingCoverageUsesTheSendSnapshotAndItsOwnDenominator(): void
    {
        $audience = $this->createAudience(rateSeconds: 1);
        $audience->clickTracking = true;

        $consenting = $this->createContact($audience, 'yes@example.tld');
        $consenting->clickTrackingConsentAt = new DateTimeImmutable();
        $this->createContact($audience, 'no@example.tld');
        $campaign = $this->createCampaign($audience);
        $sender = self::getContainer()->get(CampaignSender::class);
        $sender->arm($campaign);
        self::assertSame(1, $sender->drain($campaign, 2));
        $this->entityManager->getConnection()->executeStatement('UPDATE newsletter_campaign_recipient SET sent_at = ? WHERE campaign_id = ? AND sent_at IS NOT NULL', [new DateTimeImmutable('yesterday')->format('Y-m-d H:i:s'), $campaign->id]);
        self::assertSame(1, $sender->drain($campaign, 2));
        $audience->clickTracking = false;
        $this->entityManager->flush();
        $report = self::getContainer()->get(LocalStatisticsProvider::class)->campaign($campaign->id ?? 0, $this->query($audience->id));
        self::assertSame(50.0, $report->rate('coverage')['value']);
        self::assertSame(1, $report->rate('clickRate')['denominator']->value);
        self::assertSame(2, $report->metric('coverageKnownMessages')->value);
    }

    public function testUnknownDeliveryReferencesAreRejectedWithoutChangingContacts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::getContainer()->get(DeliveryEventRecorder::class)->record(new DeliveryNotification('external', 'unknown', 'missing', DeliveryEventKind::Failed, new DateTimeImmutable(), 'unknown message'));
    }

    public function testLinkDetailsHonorTheSameObservationWindowAsTheReport(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'links@example.tld');
        $campaign = $this->createCampaign($audience);
        $message = new DeliveryMessage($contact, true, $campaign);
        $message->accept(null);

        $this->entityManager->persist($message);
        $this->entityManager->flush();
        $this->backdate($message, '2026-01-01');
        foreach (['2026-01-02', '2026-01-10'] as $date) {
            $event = ClickEvent::onCampaign($contact, $campaign, 'https://example.tld/article');
            $event->message = $message;
            $this->entityManager->persist($event);
            $this->entityManager->flush();
            $this->entityManager->getConnection()->update('newsletter_click_event', ['clicked_at' => new DateTimeImmutable($date)->format('Y-m-d H:i:s')], ['id' => $event->id]);
            $this->entityManager->refresh($event);
        }

        $repository = self::getContainer()->get(ClickEventRepository::class);
        foreach ([7 => 1, 30 => 2] as $days => $expected) {
            $query = new ReportQuery(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-02-01'), $audience->id, windowDays: $days, asOf: new DateTimeImmutable('2026-02-10'));
            $links = $repository->byUrl($campaign, $query);
            self::assertCount(1, $links);
            self::assertSame($expected, $links[0]['clicks']);
            self::assertSame(1, $links[0]['contacts']);
        }
    }

    private function payload(Email $email): string
    {
        $html = $email->getHtmlBody();
        self::assertIsString($html);
        self::assertSame(1, preg_match('~/newsletter/c/([A-Za-z0-9_.-]+)~', $html, $matches));

        return $matches[1];
    }

    private function backdate(DeliveryMessage $message, string $date): void
    {
        $timestamp = new DateTimeImmutable($date)->format('Y-m-d H:i:s');
        $this->entityManager->getConnection()->update('newsletter_delivery_message', ['attempted_at' => $timestamp, 'accepted_at' => $timestamp], ['id' => $message->id]);
        $this->entityManager->refresh($message);
    }

    private function query(?int $audienceId): ReportQuery
    {
        return new ReportQuery(new DateTimeImmutable('yesterday'), new DateTimeImmutable('tomorrow'), $audienceId);
    }

    /** @return list<Report> */
    private function reports(ReportQuery $query): array
    {
        return iterator_to_array(self::getContainer()->get(LocalStatisticsProvider::class)->reports($query), false);
    }
}
