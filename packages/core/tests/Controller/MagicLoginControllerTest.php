<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Controller;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\LoginToken;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\LoginTokenRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\DomCrawler\Crawler;

#[Group('integration')]
final class MagicLoginControllerTest extends WebTestCase
{
    use MailerAssertionsTrait;

    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private LoginToken $token;

    private string $url;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        self::getContainer()->get('limiter.login_email')->create('127.0.0.1')->reset();
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = 'magic-scanner-'.bin2hex(random_bytes(8)).'@example.tld';
        $user->setRoles([User::ROLE_SUPER_ADMIN]);

        $raw = bin2hex(random_bytes(32));
        $this->token = new LoginToken($user)->setToken($raw);
        $this->em->persist($user);
        $this->em->persist($this->token);
        $this->em->flush();

        $this->url = '/login/magic/'.base64_encode($user->id.':'.$raw);
    }

    protected function tearDown(): void
    {
        $user = $this->em->find($this->token->user::class, $this->token->user->id);
        if ($user instanceof User) {
            $this->em->remove($user);
            $this->em->flush();
        }

        parent::tearDown();
    }

    public function testHeadAndRepeatedGetsNeverConsumeAuthenticateOrSendCodes(): void
    {
        $this->client->request('HEAD', $this->url);
        self::assertFalse($this->reload()->used);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $this->client->getCookieJar()->all());

        for ($i = 0; $i < 2; ++$i) {
            $this->client->request('GET', $this->url);
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
            self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
            self::assertSelectorExists('form[data-magic-login]');
            self::assertEmailCount(0);
            self::assertFalse($this->reload()->used);
            self::assertNull($this->token->loginCodeHash);
        }

        $this->client->request('GET', '/_pushword/auth-check');
        self::assertResponseStatusCodeSame(401);
    }

    public function testPostNeedsCsrfBeforeConsumingEitherCredential(): void
    {
        foreach (['connect', 'request_code', 'verify_code'] as $action) {
            $this->client->request('POST', $this->url, ['action' => $action, 'code' => '01234567']);
            self::assertResponseIsSuccessful();
            self::assertFalse($this->reload()->used);
            self::assertSame(0, $this->token->loginCodeAttempts);
            self::assertNull($this->token->loginCodeHash);
            self::assertEmailCount(0);
        }
    }

    public function testTheLinkSignsInFromAnotherBrowserAndIsSingleUse(): void
    {
        $this->client->request('GET', '/login');
        $this->client->getCookieJar()->clear();
        $this->connect();
        self::assertTrue($this->reload()->used);
        $this->client->request('GET', '/_pushword/auth-check');
        self::assertResponseStatusCodeSame(204);

        $this->client->getCookieJar()->clear();
        $this->client->request('GET', $this->url);
        self::assertSelectorNotExists('[data-magic-login]');
        self::assertSelectorExists('button[value="request_code"]');
        self::assertEmailCount(0);
        $this->client->request('GET', '/_pushword/auth-check');
        self::assertResponseStatusCodeSame(401);
    }

    public function testASignedInReaderDoesNotNeedOrReceiveACode(): void
    {
        $this->connect();
        $this->client->request('GET', $this->url);
        self::assertResponseRedirects('/admin/dashboard');
        self::assertEmailCount(0);
    }

    public function testCodeIsSentOnlyOnRequestAndRecoversInAnotherSession(): void
    {
        $this->markUsed();
        $code = $this->requestCode();
        self::assertNotSame($code, $this->reload()->loginCodeHash);
        self::assertTrue(password_verify($code, (string) $this->token->loginCodeHash));
        $this->client->getCookieJar()->clear();
        $crawler = $this->client->request('GET', $this->url);
        self::assertEmailCount(0);
        $this->submitCode($crawler, $code);
        self::assertResponseRedirects();
        self::assertNull($this->reload()->loginCodeHash);

        $this->client->getCookieJar()->clear();
        $crawler = $this->client->request('GET', $this->url);
        $this->client->request('POST', $this->url, [
            '_csrf_token' => $crawler->filter('input[name="_csrf_token"]')->attr('value'),
            'action' => 'verify_code', 'code' => $code,
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[role="alert"]');
        $this->client->request('GET', '/_pushword/auth-check');
        self::assertResponseStatusCodeSame(401);
    }

    public function testExpiredLinkStartsAFreshCodeChallenge(): void
    {
        $this->token->expiresAt = new DateTimeImmutable('-1 minute');
        $this->em->flush();
        $code = $this->requestCode();
        self::assertGreaterThan(new DateTimeImmutable('+9 minutes'), $this->reload()->loginCodeExpiresAt);
        $this->submitCode($this->client->getCrawler(), $code);
        self::assertResponseRedirects();
    }

    public function testAnExpiredCodeCannotAuthenticate(): void
    {
        $this->markUsed();
        $code = $this->requestCode();
        $csrf = $this->client->getCrawler()->filter('input[name="_csrf_token"]')->attr('value');
        $this->reload()->loginCodeExpiresAt = new DateTimeImmutable('-1 second');
        $this->em->flush();
        $this->client->request('POST', $this->url, ['action' => 'verify_code', 'code' => $code, '_csrf_token' => $csrf]);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->reload()->sessionVersion);
    }

    public function testFiveWrongAttemptsCannotBeResetByChangingBrowser(): void
    {
        $this->markUsed();
        $code = $this->requestCode();
        $wrong = '00000000' === $code ? '11111111' : '00000000';
        for ($i = 0; $i < LoginToken::CODE_MAX_ATTEMPTS; ++$i) {
            $this->client->getCookieJar()->clear();
            $crawler = $this->client->request('GET', $this->url);
            $this->submitCode($crawler, $wrong);
            self::assertResponseIsSuccessful();
            self::assertSame($i + 1, $this->reload()->loginCodeAttempts);
        }

        $csrf = $this->client->getCrawler()->filter('input[name="_csrf_token"]')->attr('value');
        $this->client->request('POST', $this->url, ['action' => 'verify_code', 'code' => $code, '_csrf_token' => $csrf]);
        self::assertSame(0, $this->reload()->sessionVersion);
        self::assertSame(5, $this->token->loginCodeAttempts);
    }

    public function testAFailedCsrfCheckDoesNotSpendACodeAttempt(): void
    {
        $this->markUsed();
        $code = $this->requestCode();
        $this->client->request('POST', $this->url, ['action' => 'verify_code', 'code' => $code, '_csrf_token' => 'invalid']);
        self::assertSame(0, $this->reload()->loginCodeAttempts);
        self::assertTrue($this->token->hasLoginCode());
    }

    public function testResendingTooSoonKeepsTheExistingCode(): void
    {
        $this->markUsed();
        $code = $this->requestCode();
        $this->client->submit($this->client->getCrawler()->filter('button[value="request_code"]')->form());
        self::assertEmailCount(0);
        self::assertSelectorExists('[role="alert"]');
        self::assertTrue(password_verify($code, (string) $this->reload()->loginCodeHash));
    }

    public function testCodeRequestsUseTheAccountLimitAcrossSessions(): void
    {
        $this->markUsed();
        self::getContainer()->get('limiter.login_address')->create(hash('sha256', $this->token->user->email))->consume(5);
        $this->client->getCookieJar()->clear();
        $crawler = $this->client->request('GET', $this->url);
        $this->client->submit($crawler->filter('button[value="request_code"]')->form());
        self::assertResponseStatusCodeSame(429);
        self::assertEmailCount(0);
        self::assertNull($this->reload()->loginCodeHash);
    }

    public function testCodeRequestsAlsoUseTheIpLimit(): void
    {
        $this->markUsed();
        self::getContainer()->get('limiter.login_email')->create('127.0.0.1')->consume(20);
        $crawler = $this->client->request('GET', $this->url);
        $this->client->submit($crawler->filter('button[value="request_code"]')->form());
        self::assertResponseStatusCodeSame(429);
        self::assertEmailCount(0);
        self::assertNull($this->reload()->loginCodeHash);
    }

    public function testTheRecoveryEmailUsesTheAccountLocale(): void
    {
        $this->token->user->locale = 'fr';
        $this->em->flush();
        $this->markUsed();
        $this->requestCode();
        $mail = self::getMailerMessage();
        self::assertInstanceOf(TemplatedEmail::class, $mail);
        self::assertStringStartsWith('Votre code de connexion', (string) $mail->getSubject());
        self::assertStringContainsString('Saisissez ce code', (string) $mail->getHtmlBody());
    }

    public function testRecoveryRevokesTheScannerSessionButPreservesOtherLogins(): void
    {
        $raw = bin2hex(random_bytes(32));
        $otherToken = new LoginToken($this->token->user)->setToken($raw);
        $this->em->persist($otherToken);
        $this->em->flush();

        $otherUrl = '/login/magic/'.base64_encode($this->token->user->id.':'.$raw);
        $this->client->loginUser($this->token->user);
        $otherLogin = clone $this->client->getCookieJar();
        $this->client->getCookieJar()->clear();
        $this->connect($otherUrl);
        $otherLink = clone $this->client->getCookieJar();
        $this->client->getCookieJar()->clear();
        $this->connect();
        $scanner = clone $this->client->getCookieJar();
        $this->client->getCookieJar()->clear();
        $code = $this->requestCode();
        $this->submitCode($this->client->getCrawler(), $code);
        self::assertResponseRedirects();
        $recipient = clone $this->client->getCookieJar();

        $this->useCookies($scanner);
        $this->client->request('POST', '/admin');
        self::assertResponseRedirects('/login');
        $this->client->request('GET', '/_pushword/auth-check');
        self::assertResponseStatusCodeSame(401);

        foreach ([$recipient, $otherLogin, $otherLink] as $cookies) {
            $this->useCookies($cookies);
            $this->client->request('GET', '/_pushword/auth-check');
            self::assertResponseStatusCodeSame(204);
        }
    }

    public function testUnknownAndWrongPurposeTokensCannotSendCodes(): void
    {
        $this->token->type = LoginToken::TYPE_SET_PASSWORD;
        $this->em->flush();
        foreach ([$this->url, '/login/magic/'.base64_encode($this->token->user->id.':wrong')] as $url) {
            $this->client->request('POST', $url, ['action' => 'request_code']);
            self::assertResponseRedirects('/login');
            self::assertEmailCount(0);
        }
    }

    private function reload(): LoginToken
    {
        $token = $this->em->find(LoginToken::class, $this->token->id);
        self::assertInstanceOf(LoginToken::class, $token);
        $this->em->refresh($token);

        return $this->token = $token;
    }

    private function markUsed(): void
    {
        self::assertTrue(self::getContainer()->get(LoginTokenRepository::class)->consumeLoginLink($this->token));
    }

    private function connect(?string $url = null): void
    {
        $crawler = $this->client->request('GET', $url ?? $this->url);
        $this->client->submit($crawler->filter('form[data-magic-login]')->form());
        self::assertResponseRedirects();
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
    }

    private function requestCode(): string
    {
        $crawler = $this->client->request('GET', $this->url);
        $this->client->submit($crawler->filter('button[value="request_code"]')->form());
        self::assertResponseIsSuccessful();
        self::assertEmailCount(1);
        $mail = self::getMailerMessage();
        self::assertInstanceOf(TemplatedEmail::class, $mail);
        self::assertSame($this->token->user->email, $mail->getTo()[0]->getAddress());
        self::assertSame(1, preg_match('/>\s*([0-9]{8})\s*</', (string) $mail->getHtmlBody(), $matches));
        self::assertStringNotContainsString('/login/magic/', (string) $mail->getHtmlBody());
        self::assertSelectorExists('input[autocomplete="one-time-code"]');

        return $matches[1];
    }

    private function submitCode(Crawler $crawler, string $code): void
    {
        $form = $crawler->filter('button[value="verify_code"]')->form();
        $form['code'] = $code;
        $this->client->submit($form);
    }

    private function useCookies(CookieJar $cookies): void
    {
        $this->client->getCookieJar()->clear();
        foreach ($cookies->all() as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }
    }
}
