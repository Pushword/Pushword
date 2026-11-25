<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Security;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Security\LoginRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class LoginRateLimiterTest extends TestCase
{
    private LoginRateLimiter $limiter;

    protected function setUp(): void
    {
        $storage = new InMemoryStorage();
        $this->limiter = new LoginRateLimiter(
            new RateLimiterFactory(['id' => 'network', 'policy' => 'fixed_window', 'limit' => 25, 'interval' => '15 minutes'], $storage),
            new RateLimiterFactory(['id' => 'account', 'policy' => 'fixed_window', 'limit' => 5, 'interval' => '15 minutes'], $storage),
            'test-secret',
        );
    }

    public function testAnAccountBudgetIsSharedAcrossAnIpv6Network(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            self::assertTrue($this->tryLogin('Editor@example.tld', '2001:db8:1:2::'.$i));
        }

        self::assertFalse($this->tryLogin('editor@example.tld', '2001:db8:1:2::ff'));
        self::assertTrue($this->tryLogin('editor@example.tld', '2001:db8:1:3::1'), 'another /64 has its own budget');
        self::assertTrue($this->tryLogin('other@example.tld', '2001:db8:1:2::ff'), 'another account has its own budget');
    }

    public function testANetworkBudgetBoundsHowManyAccountsItTries(): void
    {
        for ($i = 1; $i <= 25; ++$i) {
            self::assertTrue($this->tryLogin('user'.$i.'@example.tld', '2001:db8:4:5::'.dechex($i)));
        }

        self::assertFalse($this->tryLogin('user26@example.tld', '2001:db8:4:5::ffff'));
    }

    public function testASuccessfulLoginResetsTheAccountBudgetOnly(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->tryLogin('editor@example.tld', '192.0.2.10');
        }

        for ($i = 1; $i <= 19; ++$i) {
            $this->tryLogin('other'.$i.'@example.tld', '192.0.2.10');
        }

        $this->limiter->reset($this->request('editor@example.tld', '192.0.2.10'));

        self::assertTrue($this->tryLogin('editor@example.tld', '192.0.2.10'), 'the account budget was reset');
        self::assertFalse($this->tryLogin('editor@example.tld', '192.0.2.10'), 'the network budget was kept');
    }

    public function testASuccessfulLoginRefundsOnlyItsOwnNetworkToken(): void
    {
        $ip = '192.0.2.10';
        $email = 'editor@example.tld';
        for ($i = 1; $i <= 4; ++$i) {
            self::assertTrue($this->tryLogin($email, $ip));
        }

        for ($i = 1; $i <= 20; ++$i) {
            self::assertTrue($this->tryLogin('other'.$i.'@example.tld', $ip));
        }

        $successful = $this->request($email, $ip);
        self::assertTrue($this->limiter->consume($successful)->isAccepted());
        $this->limiter->reset($successful);
        $this->limiter->reset($successful);

        self::assertTrue($this->tryLogin($email, $ip), 'the successful attempt refunded one token');
        self::assertFalse($this->tryLogin('other@example.tld', $ip), 'the failed attempts still count');
    }

    private function tryLogin(string $username, string $ip): bool
    {
        return $this->limiter->consume($this->request($username, $ip))->isAccepted();
    }

    private function request(string $username, string $ip): Request
    {
        $request = Request::create('/login', Request::METHOD_POST, server: ['REMOTE_ADDR' => $ip]);
        $request->attributes->set(SecurityRequestAttributes::LAST_USERNAME, $username);

        return $request;
    }
}
