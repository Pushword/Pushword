<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Sms;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\UserRepository;
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
