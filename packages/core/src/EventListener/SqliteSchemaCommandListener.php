<?php

namespace Pushword\Core\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * SQLite has no real ALTER TABLE: DBAL rebuilds the table by copying it aside,
 * dropping it and recreating it. `DROP TABLE` under `foreign_keys=ON` runs an
 * implicit `DELETE FROM` first, so every cascade hanging off that table fires and
 * the child rows are gone. Pushword ships no migrations — `doctrine:schema:update
 * --force` is the upgrade path — so the pragma has to come back off for it.
 *
 * @see \Pushword\Core\Doctrine\SqliteConnectionPragmas which turns it on
 */
#[AsEventListener(event: ConsoleEvents::COMMAND)]
final readonly class SqliteSchemaCommandListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(ConsoleCommandEvent $consoleCommandEvent): void
    {
        $name = $consoleCommandEvent->getCommand()?->getName();
        if (null === $name || ! str_starts_with($name, 'doctrine:schema:')) {
            return;
        }

        if (! $this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }

        $this->connection->executeStatement('PRAGMA foreign_keys=OFF');
    }
}
