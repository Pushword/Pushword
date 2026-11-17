<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\LoginToken;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\LoginTokenRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class LoginTokenRecoveryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private LoginTokenRepository $tokens;

    private LoginToken $token;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->tokens = self::getContainer()->get(LoginTokenRepository::class);
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = 'code-repository-'.bin2hex(random_bytes(8)).'@example.tld';

        $this->token = new LoginToken($user)->setToken(bin2hex(random_bytes(32)));
        $this->em->persist($user);
        $this->em->persist($this->token);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->remove($this->token->user);
        $this->em->flush();
        parent::tearDown();
    }

    public function testLinkCanOnlyBeClaimedOnceIncludingWithAStaleEntity(): void
    {
        self::assertTrue($this->tokens->consumeLoginLink($this->token));
        $this->token->used = false;
        self::assertFalse($this->tokens->consumeLoginLink($this->token));
        self::assertTrue($this->token->used);
    }

    public function testExpiredAndWrongPurposeLinksCannotBeClaimed(): void
    {
        $this->token->expiresAt = new DateTimeImmutable('-1 second');
        $this->em->flush();
        self::assertFalse($this->tokens->consumeLoginLink($this->token));
        $this->token->expiresAt = new DateTimeImmutable('+1 hour');
        $this->token->type = LoginToken::TYPE_PASSWORD_RESET;

        $this->em->flush();
        self::assertFalse($this->tokens->consumeLoginLink($this->token));
        self::assertFalse($this->tokens->issueLoginCode($this->token, password_hash('01234567', \PASSWORD_DEFAULT)));
    }

    public function testTheFifthCodeAttemptMaySucceedButNoReplayCan(): void
    {
        $this->issue();
        for ($i = 0; $i < 4; ++$i) {
            self::assertFalse($this->tokens->consumeLoginCode($this->token, '99999999'));
        }

        self::assertTrue($this->tokens->consumeLoginCode($this->token, '01234567'));
        self::assertSame(5, $this->token->loginCodeAttempts);
        self::assertFalse($this->tokens->consumeLoginCode($this->token, '01234567'));
        self::assertSame(1, $this->token->sessionVersion);
        self::assertTrue($this->token->used);
    }

    public function testAnInvalidFormatSpendsAnAttemptAndTheSixthCannotSucceed(): void
    {
        $this->issue();
        for ($i = 0; $i < 5; ++$i) {
            self::assertFalse($this->tokens->consumeLoginCode($this->token, ''));
        }

        self::assertFalse($this->tokens->consumeLoginCode($this->token, '01234567'));
        self::assertSame(5, $this->token->loginCodeAttempts);
        self::assertSame(0, $this->token->sessionVersion);
    }

    public function testResendingReplacesTheCodeAndOnlyAfterTheCooldown(): void
    {
        $this->issue();
        self::assertFalse($this->tokens->issueLoginCode($this->token, password_hash('87654321', \PASSWORD_DEFAULT)));
        $this->token->loginCodeExpiresAt = new DateTimeImmutable('+8 minutes');
        $this->em->flush();
        self::assertTrue($this->tokens->issueLoginCode($this->token, password_hash('87654321', \PASSWORD_DEFAULT)));
        self::assertFalse($this->tokens->consumeLoginCode($this->token, '01234567'));
        self::assertTrue($this->tokens->consumeLoginCode($this->token, '87654321'));
    }

    public function testRecoveryRevokesOnlySessionsWithTheOldLinkVersion(): void
    {
        $this->issue();
        self::assertTrue($this->tokens->isSessionCurrent((int) $this->token->id, 0, $this->token->user));
        self::assertTrue($this->tokens->consumeLoginCode($this->token, '01234567'));
        self::assertFalse($this->tokens->isSessionCurrent((int) $this->token->id, 0, $this->token->user));
        self::assertTrue($this->tokens->isSessionCurrent((int) $this->token->id, 1, $this->token->user));
    }

    private function issue(): void
    {
        self::assertTrue($this->tokens->issueLoginCode($this->token, password_hash('01234567', \PASSWORD_DEFAULT)));
    }
}
