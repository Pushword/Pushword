<?php

declare(strict_types=1);

namespace Pushword\Flat\Command;

use Pushword\Flat\Service\FlatLockManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'pw:flat:lock',
    description: 'Acquire an editorial lock to prevent concurrent admin modifications.'
)]
final readonly class FlatLockCommand
{
    public function __construct(
        private FlatLockManager $lockManager,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'The host to lock (optional)', name: 'host')]
        ?string $host = null,
        #[Option(description: 'Lock duration in seconds', name: 'ttl', shortcut: 't')]
        int $ttl = 1800,
        #[Option(description: 'Reason for the lock', name: 'reason', shortcut: 'r')]
        string $reason = 'manual',
    ): int {
        // Check if already locked
        if ($this->lockManager->isLocked($host)) {
            $lockInfo = $this->lockManager->getLockInfo($host);
            $remaining = $this->lockManager->getRemainingTime($host);

            $output->writeln(\sprintf(
                '<comment>Already locked by %s. Remaining: %d seconds. Reason: %s</comment>',
                $lockInfo['lockedBy'] ?? 'unknown',
                $remaining,
                $lockInfo['reason'] ?? 'unknown',
            ));

            // Manual lock can override auto lock
            if (FlatLockManager::LOCK_TYPE_MANUAL !== ($lockInfo['lockedBy'] ?? '')) {
                $output->writeln('<info>Overriding auto-lock with manual lock...</info>');
            } else {
                $output->writeln('<error>Cannot override existing manual lock.</error>');

                return Command::FAILURE;
            }
        }

        $acquired = $this->lockManager->acquireLock($host, $reason, $ttl);

        if (! $acquired) {
            $output->writeln('<error>Failed to acquire lock.</error>');

            return Command::FAILURE;
        }

        $hostDisplay = $host ?? 'default';
        $output->writeln(\sprintf('<info>Lock acquired for host "%s" (TTL: %d seconds).</info>', $hostDisplay, $ttl));

        return Command::SUCCESS;
    }
}
