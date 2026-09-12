<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Command;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Command\UserTokenCommand;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\UserRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;

final class UserTokenCommandTest extends TestCase
{
    public function testOutputsRawToken(): void
    {
        $user = new User();
        $user->email = 'robin@example.tld';
        $user->apiToken = 'abc123deadbeef';

        $command = $this->makeCommand($user);
        $output = new BufferedOutput();

        $result = $command('robin@example.tld', $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertSame('abc123deadbeef', $output->fetch());
    }

    public function testUserNotFound(): void
    {
        $command = $this->makeCommand(null);

        $result = $command('missing@example.tld', new BufferedOutput());

        self::assertSame(Command::FAILURE, $result);
    }

    public function testUserWithoutToken(): void
    {
        $user = new User();
        $user->email = 'noadmin@example.tld';
        $user->apiToken = null;

        $command = $this->makeCommand($user);

        $result = $command('noadmin@example.tld', new BufferedOutput());

        self::assertSame(Command::FAILURE, $result);
    }

    private function makeCommand(?User $user): UserTokenCommand
    {
        $repo = self::createStub(UserRepository::class);
        $repo->method('findOneBy')->willReturn($user);

        return new UserTokenCommand($repo);
    }
}
