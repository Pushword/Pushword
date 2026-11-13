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
use Pushword\Newsletter\Sms\SmsSettings;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;

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
        $this->configure(true);
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

        $this->client->request('GET', '/admin/newsletter/campaign/'.$campaign->id);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, '[data-sms-delivery]');
        self::assertSelectorCount(2, '[data-sms-delivery] [data-sms-state]');
        self::assertSelectorTextContains('[data-sms-delivery]', 'Accepted text');
        self::assertSelectorExists('[data-sms-delivery] time[datetime="2030-06-01T09:30:00+00:00"]');
        self::assertSelectorTextContains('[data-campaign-sms]', 'Awaiting retry');
        self::assertSelectorNotExists('[data-sms-campaign-counts]');
        self::assertSelectorExists('form[action$="/sms/refresh"]');
        self::assertSelectorExists('details form[action$="/sms/cancel"]');

        $pending->state = SmsState::Skipped;
        $pending->nextAttemptAt = null;

        $accepted->state = SmsState::Delivered;
        $campaignSend = new SmsSend($campaign, null, '+33612345670', 'Campaign text');
        $campaignSend->state = SmsState::Delivered;

        $this->entityManager->persist($campaignSend);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/newsletter/campaign/'.$campaign->id);
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

    public function testSmsTestAndCancellationAreCsrfProtectedAndNeverSendOnGet(): void
    {
        $this->configure(true);
        $this->login();
        $campaign = $this->createCampaign($this->createAudience());
        $campaign->smsContent = 'A saved SMS';

        $this->entityManager->flush();
        $url = '/admin/newsletter/campaign/'.$campaign->id;
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->entityManager->getRepository(SmsSend::class)->count(['campaign' => $campaign]));
        $form = $crawler->filter('form[action$="/sms/test"]')->form();
        $form['phone'] = '+33612345678';
        $this->client->submit($form);
        self::assertResponseRedirects($url);
        $this->client->submit($form);
        self::assertResponseRedirects($url);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'SMS test queued');
        $sends = $this->entityManager->getRepository(SmsSend::class)->findBy(['campaign' => $campaign]);
        self::assertCount(1, $sends);
        self::assertTrue($sends[0]->isTest);
        self::assertSame('A saved SMS', $sends[0]->content);
        $this->client->request('POST', $url.'/sms/cancel', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(SmsState::Pending, $sends[0]->state);
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->filter('form[action$="/sms/cancel"]')->form());
        self::assertResponseRedirects($url);
        self::assertSame(SmsState::Skipped, $this->entityManager->getRepository(SmsSend::class)->findOneBy(['reference' => $sends[0]->reference])?->state);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Pending SMS have been cancelled');
    }

    private function configure(bool $enabled): void
    {
        self::getContainer()->set(SmsSettings::class, new SmsSettings('https://sms.example.test/api', 'test-user', 'test-password', 'test-device', 1, $enabled, 24, 30, 0, 24, 'UTC'));
    }

    private function login(): void
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@example.tld']);
        self::assertInstanceOf(User::class, $user);
        $this->client->loginUser($user);
    }
}
