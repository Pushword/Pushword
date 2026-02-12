<?php

declare(strict_types=1);

namespace Pushword\Flat\Sync;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\UserRepository;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

final class UserSync
{
    private ?OutputInterface $output = null;

    private int $importedCount = 0;

    private int $updatedCount = 0;

    private int $skippedCount = 0;

    private int $deletedCount = 0;

    /** @param class-string<User> $userClass */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%pw.entity_user%')]
        private readonly string $userClass = User::class,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function setOutput(?OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function import(): void
    {
        $this->importedCount = 0;
        $this->updatedCount = 0;
        $this->skippedCount = 0;
        $this->deletedCount = 0;

        $configPath = $this->projectDir.'/config/users.yaml';
        if (! file_exists($configPath)) {
            $this->output?->writeln('<comment>No config/users.yaml found — skipping user sync</comment>');

            return;
        }

        $config = Yaml::parseFile($configPath);
        if (! \is_array($config)) {
            $this->logger?->warning('Invalid users.yaml format');
            $this->output?->writeln('<error>Invalid users.yaml format</error>');

            return;
        }

        $users = $config['users'] ?? [];

        if (! \is_array($users)) {
            $users = [];
        }

        // Import from YAML to DB (create/update)
        /** @var string[] $yamlEmails */
        $yamlEmails = [];
        foreach ($users as $userData) {
            if (! \is_array($userData) || ! isset($userData['email']) || ! \is_string($userData['email'])) {
                $this->logger?->warning('Skipping user entry without valid email');

                continue;
            }

            /** @var array{email: string, roles?: string[], locale?: string, username?: string} $userData */
            $email = $userData['email'];
            $yamlEmails[] = $email;
            $existingUser = $this->userRepository->findOneBy(['email' => $email]);

            if ($existingUser instanceof User) {
                $this->updateUser($existingUser, $userData);
            } else {
                $this->createUser($userData);
            }
        }

        // Delete DB users not in YAML
        $dbUsers = $this->userRepository->findAll();
        foreach ($dbUsers as $dbUser) {
            if (! \in_array($dbUser->email, $yamlEmails, true)) {
                $this->logger?->info('Deleted user: '.$dbUser->email);
                $this->output?->writeln('Deleted user: '.$dbUser->email);
                $this->em->remove($dbUser);
                ++$this->deletedCount;
            }
        }

        $this->em->flush();

        $this->output?->writeln(\sprintf(
            '<info>Users: %d created, %d updated, %d deleted, %d skipped</info>',
            $this->importedCount,
            $this->updatedCount,
            $this->deletedCount,
            $this->skippedCount
        ));
    }

    /**
     * @param array{email: string, roles?: string[], locale?: string, username?: string} $userData
     */
    private function createUser(array $userData): void
    {
        $user = new ($this->userClass)();
        $user->email = $userData['email'];
        $user->setRoles($userData['roles'] ?? [User::ROLE_DEFAULT]);
        $user->locale = $userData['locale'] ?? 'en';
        $user->username = $userData['username'] ?? null;

        // Important: No password is set - user will use magic link

        $this->em->persist($user);
        ++$this->importedCount;

        $this->logger?->info('Created user: '.$userData['email']);
        $this->output?->writeln('Created user: '.$userData['email']);
    }

    /**
     * @param array{email: string, roles?: string[], locale?: string, username?: string} $userData
     */
    private function updateUser(User $user, array $userData): void
    {
        $changed = false;

        // Update roles if different (normalize by removing ROLE_USER for comparison)
        $newRoles = array_values(array_diff($userData['roles'] ?? [], [User::ROLE_DEFAULT]));
        $currentRoles = array_values(array_diff($user->getRoles(), [User::ROLE_DEFAULT]));
        sort($newRoles);
        sort($currentRoles);
        if ($currentRoles !== $newRoles) {
            $user->setRoles($newRoles);
            $changed = true;
        }

        // Update locale if different
        $newLocale = $userData['locale'] ?? 'en';
        if ($user->locale !== $newLocale) {
            $user->locale = $newLocale;
            $changed = true;
        }

        // Update username if provided and different
        $newUsername = $userData['username'] ?? null;
        if (null !== $newUsername && $user->username !== $newUsername) {
            $user->username = $newUsername;
            $changed = true;
        }

        // Never touch password - that stays in DB only

        if ($changed) {
            ++$this->updatedCount;
            $this->logger?->info('Updated user: '.$userData['email']);
            $this->output?->writeln('Updated user: '.$userData['email']);
        } else {
            ++$this->skippedCount;
        }
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function getDeletedCount(): int
    {
        return $this->deletedCount;
    }
}
