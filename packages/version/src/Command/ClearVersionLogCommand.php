<?php

namespace Pushword\Version\Command;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purge the activity journal (version_log). Snapshots on disk are left intact;
 * this only clears the queryable "who did what" index.
 */
#[AsCommand(name: 'pw:version:log:clear', description: 'Clear the version activity log')]
final readonly class ClearVersionLogCommand
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Only delete entries older than this many days')]
        ?int $days = null,
    ): int {
        if (null !== $days) {
            $before = new DateTimeImmutable('-'.$days.' days');
            $deleted = (int) $this->connection->executeStatement(
                'DELETE FROM version_log WHERE created_at < :before',
                ['before' => $before],
                ['before' => Types::DATETIME_IMMUTABLE],
            );
            $io->success($deleted.' activity log entries older than '.$days.' days deleted.');

            return Command::SUCCESS;
        }

        if (! $io->confirm('Delete the entire activity log?', false)) {
            $io->warning('Aborted.');

            return Command::SUCCESS;
        }

        $deleted = (int) $this->connection->executeStatement('DELETE FROM version_log');
        $io->success($deleted.' activity log entries deleted.');

        return Command::SUCCESS;
    }
}
