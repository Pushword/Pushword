<?php

declare(strict_types=1);

namespace Pushword\Flat\Command;

use Pushword\Flat\Service\FlatLockManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'pw:flat:unlock',
    description: 'Release the editorial lock to allow admin modifications.'
)]
final readonly class FlatUnlockCommand
{
    public function __construct(
        private FlatLockManager $lockManager,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'The host to unlock (optional)', name: 'host')]
        ?string $host = null,
    ): int {
        if (! $this->lockManager->isLocked($host)) {
            $output->writeln('<comment>No active lock found.</comment>');

            return Command::SUCCESS;
        }

        $lockInfo = $this->lockManager->getLockInfo($host);
        $this->lockManager->releaseLock($host);

        $hostDisplay = $host ?? 'default';
        $output->writeln(\sprintf(
            '<info>Lock released for host "%s" (was locked by: %s).</info>',
            $hostDisplay,
            $lockInfo['lockedBy'] ?? 'unknown',
        ));

        return Command::SUCCESS;
    }
}
