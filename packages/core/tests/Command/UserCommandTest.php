<?php

namespace Pushword\Core\Tests\Command;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class UserCommandTest extends KernelTestCase
{
    public function testExistsReturnsSuccessWhenTheDatabaseHasAnAccount(): void
    {
        $application = new Application(self::createKernel());
        $commandTester = new CommandTester($application->find('pw:user:exists'));

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
    }

    public function testExecute(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pw:user:create');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'email' => 'user@example.tld',
            'password' => 'mySecr3tpAssword',
            'role' => 'ROLE_USER',
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('success', $output);
    }

    /**
     * A missing argument used to be asked through QuestionHelper, which hands back its
     * (empty) default as soon as no terminal is attached — and the trait asked again,
     * forever. Composer hooks, CI and AI agents all hit that.
     */
    public function testMissingArgumentFailsInsteadOfLoopingWithoutATerminal(): void
    {
        $application = new Application(self::createKernel());
        $commandTester = new CommandTester($application->find('pw:user:create'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('email is required');

        $commandTester->execute([], ['interactive' => false]);
    }

    /** The other half of that guard: an argument with a default takes it rather than failing. */
    public function testAnOmittedArgumentFallsBackToItsDefaultWithoutATerminal(): void
    {
        $application = new Application(self::createKernel());
        $commandTester = new CommandTester($application->find('pw:user:create'));

        $commandTester->execute(
            ['email' => 'defaulted-role@example.tld', 'password' => 'mySecr3tpAssword'],
            ['interactive' => false]
        );

        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'defaulted-role@example.tld']);
        self::assertNotNull($user);
        self::assertContains('ROLE_SUPER_ADMIN', $user->getRoles());
    }

    public function testTemporaryPasswordOptionRestrictsCreatedAccount(): void
    {
        $application = new Application(self::createKernel());
        $commandTester = new CommandTester($application->find('pw:user:create'));

        $commandTester->execute([
            'email' => 'temporary-password@example.tld',
            'password' => 'temporarySecret123',
            'role' => 'ROLE_SUPER_ADMIN',
            '--require-password-change' => true,
        ]);

        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'temporary-password@example.tld']);
        self::assertNotNull($user);
        self::assertTrue($user->requiresPasswordChange());
    }
}
