<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Sms;

use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Entity\SmsSend;
use Pushword\Newsletter\Enum\SmsState;
use Pushword\Newsletter\Service\CampaignSender;
use Pushword\Newsletter\Sms\SmsCampaignSender;
use Pushword\Newsletter\Sms\SmsGateClient;
use Pushword\Newsletter\Sms\SmsQuotaLimiter;
use Pushword\Newsletter\Sms\SmsSettings;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Pushword\Newsletter\Validation\CampaignValidationFailed;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;

#[Group('integration')]
final class SmsCampaignSenderTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    private MockHttpClient $http;

    public function testSmsRequiresItsOwnConsentAndEmailAlwaysTakesPrecedence(): void
    {
        $this->configure();
        $audience = $this->createAudience();
        $campaign = $this->campaign($audience);
        $phoneOnly = $this->phone($audience);
        $phoneOnly->unsubscribe();
        // Email preferences do not withdraw SMS permission.
        $missingConsent = $this->phone($audience, '+33612345679');
        $missingConsent->smsConsentAt = null;

        $suppressed = $this->phone($audience, '+33612345680');
        $suppressed->smsDoNotContact = true;

        $landline = $this->phone($audience, '+33112345678');
        $landline->isMobile = false;

        $local = $this->phone($audience, '0612345681');
        $addressed = $this->phone($audience, '+33612345682');
        $addressed->email = 'reader@example.test';

        $this->entityManager->flush();

        self::assertSame([$phoneOnly], $this->sender()->recipients($campaign));
        self::assertFalse($local->canReceiveSms());
        self::assertSame(0, $this->http->getRequestsCount());
    }

    public function testDispatchFreezesLocalizedTextAndWaitsForBothChannels(): void
    {
        $this->configure();
        $audience = $this->createAudience();
        $contact = $this->phone($audience);
        $contact->locale = 'fr-CH';
        $contact->name = 'Camille';
        $this->createContact($audience, 'reader@example.test');
        $campaign = $this->campaign($audience);
        $campaign->mergeTranslations(['fr' => ['smsContent' => 'Bonjour %name%']]);

        $this->entityManager->flush();
        $email = self::getContainer()->get(CampaignSender::class);
        self::assertSame(1, $email->arm($campaign));
        self::assertSame(0, $this->http->getRequestsCount());
        $send = $this->sends($campaign)[0];
        self::assertSame('Bonjour Camille', $send->content);
        self::assertSame('+33612345678', $send->phone);
        $email->drain($campaign, 5);
        self::assertEmailCount(1);
        self::assertTrue($campaign->isSending());
        $report = $this->sender()->drain(5, new DateTimeImmutable('2026-11-13 12:00 UTC'));
        self::assertSame(1, $report['smsAccepted']);
        self::assertSame(SmsState::Accepted, $send->state);
        self::assertSame('sent', $campaign->status->value);
    }

    public function testQuotaIsDurableAndSharedByCampaignsAndTests(): void
    {
        $this->configure(limit: 1);
        $campaign = $this->campaign($this->createAudience());
        $one = $this->sender()->queueTest($campaign, '+33612345678', Uuid::v7()->toRfc4122());
        $other = $this->campaign($this->createAudience());
        $two = $this->sender()->queueTest($other, '+33612345679', Uuid::v7()->toRfc4122());
        $now = new DateTimeImmutable('2026-11-13 12:00 UTC');
        self::assertSame(1, $this->sender()->drain(5, $now)['smsAccepted']);
        self::assertEquals($now, $one->quotaReservedAt);
        self::assertEquals($now->modify('+24 hours'), $two->nextAttemptAt);
        $this->entityManager->clear();
        self::assertSame(0, $this->sender()->drain(5, $now->modify('+23 hours'))['smsAccepted']);
        self::assertSame(1, $this->sender()->drain(5, $now->modify('+24 hours'))['smsAccepted']);
        self::assertSame(4, $this->http->getRequestsCount());
    }

    public function testSamePhoneAndPacingAreSharedEvenWithQuotaRemaining(): void
    {
        $this->configure();
        $campaign = $this->campaign($this->createAudience());
        $one = $this->sender()->queueTest($campaign, '+33612345678', Uuid::v7()->toRfc4122());
        $same = $this->sender()->queueTest($campaign, '+33612345678', Uuid::v7()->toRfc4122());
        $another = $this->sender()->queueTest($campaign, '+33612345679', Uuid::v7()->toRfc4122());
        $now = new DateTimeImmutable('2026-11-13 12:00 UTC');
        $this->sender()->drain(5, $now);
        self::assertNotNull($one->acceptedAt);
        self::assertEquals($now->modify('+24 hours'), $same->nextAttemptAt);
        self::assertEquals($now->modify('+30 seconds'), $another->nextAttemptAt);
    }

    #[DataProvider('sendingWindows')]
    public function testSendingHoursAndClockChanges(string $date, ?string $next, int $start = 8, int $end = 20): void
    {
        $this->configure(start: $start, end: $end, timezone: 'Europe/Paris');
        $campaign = $this->campaign($this->createAudience());
        $send = $this->sender()->queueTest($campaign, '+33612345678', Uuid::v7()->toRfc4122());
        $this->sender()->drain(1, new DateTimeImmutable($date));
        if (null === $next) {
            self::assertSame(SmsState::Accepted, $send->state);
        } else {
            self::assertEquals(new DateTimeImmutable($next), $send->nextAttemptAt);
            self::assertNull($send->quotaReservedAt);
            self::assertSame(0, $this->http->getRequestsCount());
        }
    }

    /** @return iterable<string, array{0: string, 1: ?string, 2?: int, 3?: int}> */
    public static function sendingWindows(): iterable
    {
        yield 'opening included' => ['2026-11-13 07:00 UTC', null];
        yield 'closing excluded' => ['2026-11-13 19:00 UTC', '2026-11-14 07:00 UTC'];
        yield 'summer time' => ['2026-07-13 05:59 UTC', '2026-07-13 06:00 UTC'];
        yield 'missing spring hour' => ['2026-03-29 00:30 UTC', '2026-03-30 00:00 UTC', 2, 3];
    }

    public function testAnUncertainSubmissionKeepsItsIdentityAndQuotaReservation(): void
    {
        $this->configure();
        $campaign = $this->campaign($this->createAudience());
        $send = $this->sender()->queueTest($campaign, '+33612345678', Uuid::v7()->toRfc4122());
        $posts = [];
        $this->http->setResponseFactory(static function (string $method, string $url, array $options) use (&$posts): MockResponse {
            if ('POST' === $method) {
                self::assertIsString($options['body']);
                $payload = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
                self::assertIsArray($payload);
                $posts[] = $payload['id'];

                throw new TransportException('Uncertain response');
            }

            return [] === $posts ? new MockResponse('{}', ['http_code' => 404]) : new MockResponse('{"state":"Pending"}');
        });
        $now = new DateTimeImmutable('2026-11-13 12:00 UTC');
        $this->sender()->drain(1, $now);
        self::assertSame(SmsState::Pending, $send->state);
        self::assertNotNull($send->quotaReservedAt);
        self::assertNotNull($send->error);
        self::assertSame(1, $this->sender()->drain(1, $now->modify('+5 minutes'))['smsAccepted']);
        self::assertSame([$send->reference], $posts);
        self::assertNull($send->error);
    }

    /** @param Closure(Contact): void $withdraw */
    #[DataProvider('withdrawals')]
    public function testPendingMessagesRecheckTheRecipient(Closure $withdraw): void
    {
        $this->configure();
        $audience = $this->createAudience();
        $contact = $this->phone($audience);
        $campaign = $this->campaign($audience);
        self::getContainer()->get(CampaignSender::class)->arm($campaign);
        $withdraw($contact);
        $this->entityManager->flush();
        self::assertSame(1, $this->sender()->drain(5)['smsSkipped']);
        self::assertSame(0, $this->http->getRequestsCount());
        self::assertSame('sent', $campaign->status->value);
    }

    /** @return iterable<string, array{Closure(Contact): void}> */
    public static function withdrawals(): iterable
    {
        yield 'permission withdrawn' => [static function (Contact $contact): void {
            $contact->smsConsentAt = null;
        }];
        yield 'SMS suppression' => [static function (Contact $contact): void {
            $contact->smsDoNotContact = true;
        }];
        yield 'email added' => [static function (Contact $contact): void {
            $contact->email = 'new@example.test';
        }];
        yield 'phone changed' => [static function (Contact $contact): void {
            $contact->phone = '+33699999999';
        }];
        yield 'not a mobile' => [static function (Contact $contact): void {
            $contact->isMobile = false;
        }];
    }

    public function testGatewayFailureAndDeliveryDoNotChangeEmailConsent(): void
    {
        $this->configure();
        $audience = $this->createAudience();
        $contact = $this->phone($audience);
        $campaign = $this->campaign($audience);
        self::getContainer()->get(CampaignSender::class)->arm($campaign);
        $this->http->setResponseFactory([new MockResponse('{}', ['http_code' => 404]), new MockResponse('{"state":"Failed"}')]);
        self::assertSame(1, $this->sender()->drain(1)['smsFailed']);
        self::assertTrue($contact->isSubscribed());
        self::assertNull($contact->bouncedAt);
        self::assertSame(0, $campaign->bounceCount);
    }

    public function testTestsAreIdempotentExcludedFromCountsAndCanBeCancelled(): void
    {
        $this->configure();
        $campaign = $this->campaign($this->createAudience());
        $reference = Uuid::v7()->toRfc4122();
        $send = $this->sender()->queueTest($campaign, '+33612345678', $reference);
        self::assertSame($send, $this->sender()->queueTest($campaign, '+33612345678', $reference));
        self::assertSame(0, array_sum($this->sender()->counts($campaign)));
        $this->sender()->cancel($campaign);
        self::assertSame(SmsState::Skipped, $send->state);
        self::assertSame(0, $this->sender()->drain(1)['smsAccepted']);
        self::assertSame(0, $this->http->getRequestsCount());
        self::assertTrue($campaign->isDraft());
    }

    public function testPausedConnectorBlocksDispatchBeforeEmailRecipientsAreCreated(): void
    {
        $this->configure(enabled: false);
        $audience = $this->createAudience();
        $this->phone($audience);
        $this->createContact($audience, 'reader@example.test');
        $campaign = $this->campaign($audience);

        try {
            self::getContainer()->get(CampaignSender::class)->arm($campaign);
            self::fail('A paused SMS connector must block dispatch.');
        } catch (CampaignValidationFailed $campaignValidationFailed) {
            self::assertSame('sms.disabled', $campaignValidationFailed->issues[0]->code);
        }

        self::assertTrue($campaign->isDraft());
        self::assertSame([], $this->sends($campaign));
        self::assertSame(0, $campaign->recipientCount);
        self::assertSame(0, $this->http->getRequestsCount());
    }

    public function testDeliveryRefreshDistinguishesAcceptanceFromDeliveryIncludingTests(): void
    {
        $this->configure();
        $campaign = $this->campaign($this->createAudience());
        $send = $this->sender()->queueTest($campaign, '+33612345678', Uuid::v7()->toRfc4122());
        $this->sender()->drain(1);
        self::assertSame(SmsState::Accepted, $send->state);
        $this->http->setResponseFactory([new MockResponse('{"state":"Delivered"}')]);
        $this->sender()->refresh($campaign);
        self::assertSame(SmsState::Delivered, $send->state);
        self::assertNotNull($send->checkedAt);
        self::assertSame(0, array_sum($this->sender()->counts($campaign)));
    }

    public function testSegmentChangesSuppressPendingMessages(): void
    {
        $this->configure();
        $audience = $this->createAudience();
        $contact = $this->phone($audience);
        $contact->locale = 'fr';

        $campaign = $this->campaign($audience);
        $campaign->segment = [['field' => 'locale', 'op' => '=', 'value' => 'fr']];

        $this->entityManager->flush();
        self::getContainer()->get(CampaignSender::class)->arm($campaign);
        $contact->locale = 'en';
        $this->entityManager->flush();
        self::assertSame(1, $this->sender()->drain(5)['smsSkipped']);
        self::assertSame(0, $this->http->getRequestsCount());
    }

    public function testPersonalizedSmsIsValidatedBeforeEitherChannelIsQueued(): void
    {
        $this->configure();
        $audience = $this->createAudience();
        $contact = $this->phone($audience);
        $contact->customProperties = ['firstName' => str_repeat('x', 480)];

        $campaign = $this->campaign($audience);
        $campaign->smsContent = 'Hello %firstName%';

        $this->entityManager->flush();

        try {
            self::getContainer()->get(CampaignSender::class)->arm($campaign);
            self::fail('Expansion beyond the SMS content limit must block dispatch.');
        } catch (CampaignValidationFailed $campaignValidationFailed) {
            self::assertSame('sms.contentInvalid', $campaignValidationFailed->issues[0]->code);
        }

        self::assertSame([], $this->sends($campaign));
        self::assertTrue($campaign->isDraft());
    }

    public function testTheExistingTickSharesItsBudgetBetweenEmailAndSms(): void
    {
        $this->configure();
        $audience = $this->createAudience();
        $this->phone($audience);
        $this->createContact($audience, 'reader@example.test');
        $campaign = $this->campaign($audience);
        $campaign->schedule(new DateTimeImmutable('-1 minute'));

        $this->entityManager->flush();
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $command = new CommandTester(new Application($kernel)->find('pw:newsletter:tick'));
        $command->execute(['--batch' => 1, '--format' => 'agent']);
        $command->assertCommandIsSuccessful();

        $first = json_decode($command->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($first);
        self::assertSame(1, $first['campaignMails']);
        self::assertSame(0, $first['smsAttempts']);
        self::assertTrue($campaign->isSending());
        $command->execute(['--batch' => 1, '--format' => 'agent']);
        $command->assertCommandIsSuccessful();

        $second = json_decode($command->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($second);
        self::assertSame(1, $second['smsAccepted']);
        self::assertSame(0, $second['budgetLeft']);
        self::assertSame('sent', $campaign->status->value);
    }

    public function testDeletingACampaignPreservesItsQuotaReservationsAndSuppressesOrphanedTests(): void
    {
        $this->configure(limit: 1);
        $campaign = $this->campaign($this->createAudience());
        $sent = $this->sender()->queueTest($campaign, '+33612345678', Uuid::v7()->toRfc4122());
        $pending = $this->sender()->queueTest($campaign, '+33612345679', Uuid::v7()->toRfc4122());
        $now = new DateTimeImmutable('2026-11-13 12:00 UTC');
        $this->sender()->drain(1, $now);
        $references = [$sent->reference, $pending->reference];

        try {
            $this->entityManager->remove($campaign);
            $this->entityManager->flush();
            $this->entityManager->clear();
            $other = $this->campaign($this->createAudience());
            $later = $this->sender()->queueTest($other, '+33612345680', Uuid::v7()->toRfc4122());
            $report = $this->sender()->drain(5, $now);
            self::assertSame(1, $report['smsSkipped']);
            self::assertSame(0, $report['smsAttempts']);
            self::assertEquals($now->modify('+24 hours'), $later->nextAttemptAt);
            self::assertSame(2, $this->http->getRequestsCount());
            $retained = $this->entityManager->getRepository(SmsSend::class)->findOneBy(['reference' => $sent->reference]);
            self::assertNotNull($retained);
            self::assertNull($retained->campaign);
            self::assertNotNull($retained->quotaReservedAt);
        } finally {
            foreach ($references as $reference) {
                $this->entityManager->getConnection()->executeStatement('DELETE FROM newsletter_sms_send WHERE reference = ?', [$reference]);
            }
        }
    }

    private function configure(bool $enabled = true, int $limit = 24, int $start = 0, int $end = 24, string $timezone = 'UTC'): void
    {
        $settings = new SmsSettings('https://sms.example.test/api', 'test-user', 'test-password', 'test-device', 1, $enabled, $limit, 30, $start, $end, $timezone);
        $this->http = new MockHttpClient(static fn (string $method): MockResponse => new MockResponse('GET' === $method ? '{}' : '{"state":"Pending"}', ['http_code' => 'GET' === $method ? 404 : 201]));
        self::getContainer()->set(SmsSettings::class, $settings);
        self::getContainer()->set(SmsGateClient::class, new SmsGateClient($this->http, $settings));
        self::getContainer()->set(SmsQuotaLimiter::class, new SmsQuotaLimiter($this->entityManager, $settings));
    }

    private function campaign(Audience $audience): Campaign
    {
        $campaign = $this->createCampaign($audience);
        $campaign->smsContent = 'Hello %name%';

        $this->entityManager->flush();

        return $campaign;
    }

    private function phone(Audience $audience, string $number = '+33612345678'): Contact
    {
        $contact = $this->createPhoneContact($audience, $number);
        $contact->isMobile = true;
        $contact->smsConsentAt = new DateTimeImmutable('-1 day');

        $this->entityManager->flush();

        return $contact;
    }

    private function sender(): SmsCampaignSender
    {
        return self::getContainer()->get(SmsCampaignSender::class);
    }

    /** @return list<SmsSend> */
    private function sends(Campaign $campaign): array
    {
        return $this->entityManager->getRepository(SmsSend::class)->findBy(['campaign' => $campaign]);
    }
}
