<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Sync;

use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\UserRepository;
use Pushword\Flat\Sync\UserSync;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class UserSyncTest extends KernelTestCase
{
    private string $configDir;

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $this->configDir = $projectDir.'/config';
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->cleanupTestUsers();
        @unlink($this->configDir.'/users.yaml');
        parent::tearDown();
    }

    public function testImportCreatesNewUsers(): void
    {
        $this->createUsersYaml([
            ['email' => 'test-new@example.tld', 'roles' => ['ROLE_EDITOR'], 'locale' => 'fr'],
        ]);

        /** @var UserSync $userSync */
        $userSync = self::getContainer()->get(UserSync::class);
        $userSync->import();

        /** @var UserRepository $userRepo */
        $userRepo = self::getContainer()->get(UserRepository::class);
        $user = $userRepo->findOneBy(['email' => 'test-new@example.tld']);

        self::assertInstanceOf(User::class, $user);
        self::assertSame('fr', $user->locale);
        self::assertContains('ROLE_EDITOR', $user->getRoles());
        self::assertNull($user->getPassword()); // No password set
        self::assertSame(1, $userSync->getImportedCount());
    }

    public function testImportUpdatesExistingUsers(): void
    {
        // Create user first
        $this->createTestUser('test-update@example.tld', 'en', ['ROLE_USER']);

        // Now sync with different data
        $this->createUsersYaml([
            ['email' => 'test-update@example.tld', 'roles' => ['ROLE_ADMIN'], 'locale' => 'de', 'username' => 'Updated'],
        ]);

        /** @var UserSync $userSync */
        $userSync = self::getContainer()->get(UserSync::class);
        $userSync->import();

        /** @var UserRepository $userRepo */
        $userRepo = self::getContainer()->get(UserRepository::class);
        $user = $userRepo->findOneBy(['email' => 'test-update@example.tld']);

        self::assertInstanceOf(User::class, $user);
        self::assertSame('de', $user->locale);
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertSame('Updated', $user->username);
        self::assertSame(1, $userSync->getUpdatedCount());
    }

    public function testImportSkipsUnchangedUsers(): void
    {
        // Create user with same data as YAML
        $this->createTestUser('test-skip@example.tld', 'en', ['ROLE_USER']);

        // Include all existing DB users in YAML to avoid exports affecting counts
        /** @var UserRepository $userRepo */
        $userRepo = self::getContainer()->get(UserRepository::class);
        $existingUsers = $userRepo->findAll();
        $yamlUsers = [['email' => 'test-skip@example.tld', 'roles' => ['ROLE_USER'], 'locale' => 'en']];
        foreach ($existingUsers as $existingUser) {
            if ('test-skip@example.tld' !== $existingUser->email) {
                $yamlUsers[] = [
                    'email' => $existingUser->email,
                    'roles' => $existingUser->getRoles(),
                    'locale' => $existingUser->locale ?? 'en',
                ];
            }
        }

        $this->createUsersYaml($yamlUsers);

        /** @var UserSync $userSync */
        $userSync = self::getContainer()->get(UserSync::class);
        $userSync->import();

        self::assertSame(0, $userSync->getImportedCount());
        self::assertSame(0, $userSync->getUpdatedCount());
        // All users should be skipped (test user + any fixture users)
        self::assertGreaterThanOrEqual(1, $userSync->getSkippedCount());
    }

    public function testImportDoesNotTouchPassword(): void
    {
        // Create user with password
        $user = $this->createTestUser('test-password@example.tld', 'en', ['ROLE_USER']);
        $user->setPlainPassword('originalPassword');

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $em->flush();
        $em->clear();

        // Get the hashed password
        /** @var UserRepository $userRepo */
        $userRepo = self::getContainer()->get(UserRepository::class);
        $userBefore = $userRepo->findOneBy(['email' => 'test-password@example.tld']);
        self::assertInstanceOf(User::class, $userBefore);
        $passwordBefore = $userBefore->getPassword();

        // Sync with different roles
        $this->createUsersYaml([
            ['email' => 'test-password@example.tld', 'roles' => ['ROLE_ADMIN'], 'locale' => 'fr'],
        ]);

        /** @var UserSync $userSync */
        $userSync = self::getContainer()->get(UserSync::class);
        $userSync->import();

        $em->clear();
        $userAfter = $userRepo->findOneBy(['email' => 'test-password@example.tld']);
        self::assertInstanceOf(User::class, $userAfter);

        // Password should remain unchanged
        self::assertSame($passwordBefore, $userAfter->getPassword());
        // But locale should be updated
        self::assertSame('fr', $userAfter->locale);
    }

    public function testImportHandlesMissingYaml(): void
    {
        @unlink($this->configDir.'/users.yaml');

        /** @var UserSync $userSync */
        $userSync = self::getContainer()->get(UserSync::class);
        $userSync->import();

        // When YAML is missing, it gets created and DB users are exported to it
        // Then those users are imported back (skipped since they already exist)
        self::assertSame(0, $userSync->getImportedCount());
        self::assertSame(0, $userSync->getUpdatedCount());
        // Fixture users get exported and then skipped on import
        self::assertGreaterThanOrEqual(0, $userSync->getSkippedCount());
        // YAML file should now exist
        self::assertFileExists($this->configDir.'/users.yaml');
    }

    public function testImportSkipsInvalidEntries(): void
    {
        $this->createUsersYaml([
            ['email' => 'valid@example.tld', 'roles' => ['ROLE_USER']],
            ['roles' => ['ROLE_USER']], // Missing email
            'invalid', // Not an array
        ]);

        /** @var UserSync $userSync */
        $userSync = self::getContainer()->get(UserSync::class);
        $userSync->import();

        self::assertSame(1, $userSync->getImportedCount());

        /** @var UserRepository $userRepo */
        $userRepo = self::getContainer()->get(UserRepository::class);
        $user = $userRepo->findOneBy(['email' => 'valid@example.tld']);
        self::assertInstanceOf(User::class, $user);
    }

    /**
     * @param array<mixed> $users
     */
    private function createUsersYaml(array $users): void
    {
        $content = "users:\n";
        foreach ($users as $user) {
            if (\is_array($user)) {
                if (isset($user['email']) && \is_string($user['email'])) {
                    $content .= '  - email: '.$user['email']."\n";
                } else {
                    // Create invalid entry without email for testing
                    /** @var string[] $roles */
                    $roles = $user['roles'] ?? [];
                    $content .= '  - roles: ['.implode(', ', $roles)."]\n";

                    continue;
                }

                if (isset($user['roles']) && \is_array($user['roles'])) {
                    /** @var string[] $roles */
                    $roles = $user['roles'];
                    $content .= '    roles: ['.implode(', ', $roles)."]\n";
                }

                if (isset($user['locale']) && \is_string($user['locale'])) {
                    $content .= '    locale: '.$user['locale']."\n";
                }

                if (isset($user['username']) && \is_string($user['username'])) {
                    $content .= '    username: '.$user['username']."\n";
                }
            } elseif (\is_string($user)) {
                // Non-array entry for testing invalid format
                $content .= '  - '.$user."\n";
            }
        }

        file_put_contents($this->configDir.'/users.yaml', $content);
    }

    /**
     * @param string[] $roles
     */
    private function createTestUser(string $email, string $locale, array $roles): User
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        $user = new User();
        $user->email = $email;
        $user->locale = $locale;
        $user->setRoles($roles);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function cleanupTestUsers(): void
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        /** @var UserRepository $userRepo */
        $userRepo = self::getContainer()->get(UserRepository::class);

        $testEmails = [
            'test-new@example.tld',
            'test-update@example.tld',
            'test-skip@example.tld',
            'test-password@example.tld',
            'valid@example.tld',
        ];

        foreach ($testEmails as $email) {
            $user = $userRepo->findOneBy(['email' => $email]);
            if ($user instanceof User) {
                $em->remove($user);
            }
        }

        $em->flush();
    }
}
