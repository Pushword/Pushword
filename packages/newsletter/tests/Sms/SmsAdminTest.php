<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Sms;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\UserRepository;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\SmsSend;
use Pushword\Newsletter\Enum\SmsState;
use Pushword\Newsletter\Sms\SmsGateClient;
use Pushword\Newsletter\Sms\SmsSettings;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[Group('integration')]
final class SmsAdminTest extends AbstractNewsletterTestCase
{
    #[DataProvider('connectorConfigurations')]
    public function testSmsFieldsFollowConnectorConfiguration(bool $configured): void
    {
        if ($configured) {
            $this->configure(false);
        }

        $this->login();
        $this->client->request('GET', '/admin/newsletter/campaign/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount($configured ? 1 : 0, '#Campaign_smsContent');
        $audience = $this->createAudience();
        $contact = $this->createPhoneContact($audience, '+33612345678');
        $campaign = $this->createCampaign($audience);
        $campaign->smsContent = 'A saved SMS';

        $this->entityManager->flush();
        $this->client->request('GET', '/admin/newsletter/contact/'.$contact->id.'/edit');
        self::assertResponseIsSuccessful();
        foreach (['isMobile', 'smsConsentAt', 'smsDoNotContact'] as $property) {
            self::assertSelectorCount($configured ? 1 : 0, '[name^="Contact['.$property.']"]');
        }

        $this->client->request('GET', '/admin/newsletter/campaign/'.$campaign->id);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount($configured ? 1 : 0, '[data-campaign-sms]');
        self::assertSelectorNotExists('#sms-test-phone'); // Configured but paused stays visible without a test action.
    }

    public function testEmptySmsOffersAnAuthoringActionBeforeShowingTestControls(): void
    {
        $this->configure(true);
        $this->login();
        $campaign = $this->createCampaign($this->createAudience());

        $this->client->request('GET', '/admin/newsletter/campaign/'.$campaign->id);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-sms-empty] a[href$="/edit#Campaign_smsContent"]');
        self::assertSelectorNotExists('#sms-test-phone');
        self::assertSelectorTextContains('.page-actions', 'Send email test');

        $campaign->smsContent = 'A saved SMS';
        $this->entityManager->flush();
        $this->client->request('GET', '/admin/newsletter/campaign/'.$campaign->id);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-sms-empty]');
        self::assertSelectorTextContains('[data-sms-no-recipients]', 'No eligible SMS recipients');
        self::assertSelectorExists('[data-sms-no-recipients] a[href*="newsletter/contact"]');
        self::assertSelectorExists('#sms-test-phone');

        $campaign = $this->entityManager->find(Campaign::class, $campaign->id);
        self::assertInstanceOf(Campaign::class, $campaign);
        $audienceId = $campaign->audience?->id;
        $campaign->audience = null;
        $this->entityManager->flush();

        try {
            $crawler = $this->client->request('GET', '/admin/newsletter/campaign/'.$campaign->id);
            self::assertResponseIsSuccessful();
            self::assertSame('/admin/newsletter/contact', $crawler->filter('[data-sms-no-recipients] a')->attr('href'));
        } finally {
            $this->entityManager->getConnection()->update('newsletter_campaign', ['audience_id' => $audienceId], ['id' => $campaign->id]);
        }
    }

    public function testHistoryKeepsTestOutcomesSeparateAndOnlyOffersApplicableActions(): void
    {
        $http = $this->configure(true);
        $this->login();
        $campaign = $this->createCampaign($this->createAudience());
        $campaign->smsContent = 'A saved SMS';

        $pending = new SmsSend($campaign, null, '+33612345678', str_repeat('Long message ', 30), true);
        $pending->nextAttemptAt = new DateTimeImmutable('2030-06-01 09:30:00 UTC');
        $pending->error = 'TransportException';

        $accepted = new SmsSend($campaign, null, '+33612345679', 'Accepted text', true);
        $accepted->state = SmsState::Accepted;

        $this->entityManager->persist($pending);
        $this->entityManager->persist($accepted);
        $this->entityManager->flush();

        $url = '/admin/newsletter/campaign/'.$campaign->id;
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, '[data-sms-delivery]');
        self::assertSelectorCount(2, '[data-sms-delivery] [data-sms-state]');
        self::assertSelectorTextContains('[data-sms-delivery]', 'Accepted text');
        self::assertSelectorExists('[data-sms-delivery] time[datetime="2030-06-01T09:30:00+00:00"]');
        self::assertSelectorTextContains('[data-campaign-sms]', 'Awaiting retry');
        self::assertSelectorNotExists('[data-sms-campaign-counts]');
        self::assertSelectorExists('form[action$="/sms/refresh"]');
        self::assertSelectorExists('details form[action$="/sms/cancel"]');

        $http->setResponseFactory(static fn (): MockResponse => new MockResponse('{}', ['http_code' => 503]));
        $this->client->submit($crawler->filter('form[action$="/sms/refresh"]')->form());
        self::assertResponseRedirects($url);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'The SMS operation failed');
        self::assertAnySelectorTextContains('[data-sms-state]', 'Accepted by gateway');

        $http->setResponseFactory(static fn (): MockResponse => new MockResponse('{"state":"Delivered"}'));
        $this->client->submit($crawler->filter('form[action$="/sms/refresh"]')->form());
        self::assertResponseRedirects($url);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'SMS delivery status refreshed');
        self::assertAnySelectorTextContains('[data-sms-state]', 'Delivery reported');

        $pending = $this->entityManager->getRepository(SmsSend::class)->findOneBy(['reference' => $pending->reference]);
        self::assertNotNull($pending);
        $pending->state = SmsState::Skipped;
        $pending->nextAttemptAt = null;

        $campaignSend = new SmsSend($pending->campaign, null, '+33612345670', 'Campaign text');
        $campaignSend->state = SmsState::Delivered;

        $this->entityManager->persist($campaignSend);
        $this->entityManager->flush();

        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, '[data-sms-delivery]');
        self::assertSelectorTextContains('[data-sms-campaign-counts]', 'Delivery reported');
        self::assertSelectorNotExists('form[action$="/sms/refresh"]');
        self::assertSelectorNotExists('form[action$="/sms/cancel"]');
        self::assertSelectorTextNotContains('[data-campaign-sms]', 'Awaiting retry');
    }

    public function testInvalidTestNumberReturnsUsefulFeedbackWithoutQueuing(): void
    {
        $this->configure(true);
        $this->login();
        $campaign = $this->createCampaign($this->createAudience());
        $campaign->smsContent = 'A saved SMS';

        $this->entityManager->flush();
        $crawler = $this->client->request('GET', '/admin/newsletter/campaign/'.$campaign->id);
        $form = $crawler->filter('form[action$="/sms/test"]')->form();
        $form['phone'] = '0612345678';
        $this->client->submit($form);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-danger', 'international test number');
        self::assertSame(0, $this->entityManager->getRepository(SmsSend::class)->count(['campaign' => $campaign]));
    }

    /** @return iterable<string, array{bool}> */
    public static function connectorConfigurations(): iterable
    {
        yield 'absent' => [false];
        yield 'configured but paused' => [true];
    }

    public function testFreshAdminTestsReachTheGatewayImmediatelyOnTheSameNumber(): void
    {
        $http = $this->configure(true);
        $this->login();
        $campaign = $this->createCampaign($this->createAudience());
        $campaign->smsContent = 'A saved SMS';

        $this->entityManager->flush();

        $url = '/admin/newsletter/campaign/'.$campaign->id;
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $http->getRequestsCount());
        $form = $crawler->filter('form[action$="/sms/test"]')->form();
        $form['phone'] = '+33612345678';
        $this->client->submit($form);
        self::assertResponseRedirects($url);
        self::assertSame(2, $http->getRequestsCount());

        $this->client->submit($form);
        self::assertResponseRedirects($url);
        $crawler = $this->client->followRedirect();
        self::assertSame(2, $http->getRequestsCount());
        self::assertSelectorTextContains('.alert-success', 'SMS test submitted to SMSGate');

        $nextForm = $crawler->filter('form[action$="/sms/test"]')->form();
        $nextForm['phone'] = '+33612345678';
        $this->client->submit($nextForm);
        self::assertResponseRedirects($url);
        self::assertSame(4, $http->getRequestsCount());

        $sends = $this->entityManager->getRepository(SmsSend::class)->findBy(['campaign' => $campaign]);
        self::assertCount(2, $sends);
        foreach ($sends as $send) {
            self::assertTrue($send->isTest);
            self::assertSame('+33612345678', $send->phone);
            self::assertSame('A saved SMS', $send->content);
            self::assertSame(SmsState::Accepted, $send->state);
            self::assertNotNull($send->quotaReservedAt);
            self::assertNull($send->nextAttemptAt);
        }
    }

    public function testAnUncertainAdminTestCanBeCancelledWithCsrfProtection(): void
    {
        $http = $this->configure(true);
        $http->setResponseFactory(static function (string $method): MockResponse {
            if ('POST' === $method) {
                throw new TransportException('Uncertain response');
            }

            return new MockResponse('{}', ['http_code' => 404]);
        });
        $this->login();
        $campaign = $this->createCampaign($this->createAudience());
        $campaign->smsContent = 'A saved SMS';

        $this->entityManager->flush();

        $url = '/admin/newsletter/campaign/'.$campaign->id;
        $this->client->request('POST', $url.'/sms/test', ['_token' => 'invalid', 'phone' => '+33612345678']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, $http->getRequestsCount());

        $crawler = $this->client->request('GET', $url);
        $form = $crawler->filter('form[action$="/sms/test"]')->form();
        $form['phone'] = '+33612345678';
        $this->client->submit($form);
        self::assertResponseRedirects($url);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-warning', 'SMS test awaiting retry');
        $repository = $this->entityManager->getRepository(SmsSend::class);
        $send = $repository->findOneBy(['campaign' => $campaign]);
        self::assertNotNull($send);
        self::assertSame(SmsState::Pending, $send->state);
        self::assertSame(TransportException::class, $send->error);
        self::assertNotNull($send->quotaReservedAt);
        self::assertEquals($send->quotaReservedAt->modify('+5 minutes'), $send->nextAttemptAt);

        $this->client->request('POST', $url.'/sms/cancel', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(SmsState::Pending, $send->state);
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->filter('form[action$="/sms/cancel"]')->form());
        self::assertResponseRedirects($url);
        self::assertSame(SmsState::Skipped, $repository->findOneBy(['reference' => $send->reference])?->state);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Pending SMS have been cancelled');
    }

    #[DataProvider('gatewayRejections')]
    public function testAdminTestReportsGatewayRejection(string $gatewayState, SmsState $expectedState): void
    {
        $http = $this->configure(true);
        $http->setResponseFactory(static fn (string $method): MockResponse => 'GET' === $method
            ? new MockResponse('{}', ['http_code' => 404])
            : new MockResponse(json_encode(['state' => $gatewayState], \JSON_THROW_ON_ERROR), ['http_code' => 201]));
        $this->login();
        $campaign = $this->createCampaign($this->createAudience());
        $campaign->smsContent = 'A saved SMS';

        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/admin/newsletter/campaign/'.$campaign->id);
        $form = $crawler->filter('form[action$="/sms/test"]')->form();
        $form['phone'] = '+33612345678';
        $this->client->submit($form);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'The SMS operation failed');
        $send = $this->entityManager->getRepository(SmsSend::class)->findOneBy(['campaign' => $campaign]);
        self::assertNotNull($send);
        self::assertSame($expectedState, $send->state);
        self::assertNull($send->nextAttemptAt);
    }

    /** @return iterable<string, array{string, SmsState}> */
    public static function gatewayRejections(): iterable
    {
        yield 'failed' => ['Failed', SmsState::Failed];
        yield 'cancelled' => ['Cancelled', SmsState::Skipped];
    }

    private function configure(bool $enabled): MockHttpClient
    {
        $settings = new SmsSettings('https://sms.example.test/api', 'test-user', 'test-password', 'test-device', 1, $enabled, 1, 30, 0, 24, 'UTC');
        $http = new MockHttpClient(static fn (string $method): MockResponse => 'GET' === $method
            ? new MockResponse('{}', ['http_code' => 404])
            : new MockResponse('{"state":"Pending"}', ['http_code' => 201]));
        self::getContainer()->set(SmsSettings::class, $settings);
        self::getContainer()->set(SmsGateClient::class, new SmsGateClient($http, $settings));

        return $http;
    }

    private function login(): void
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@example.tld']);
        self::assertInstanceOf(User::class, $user);
        $this->client->loginUser($user);
    }
}
