<?php

namespace Pushword\Core\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Override;
use SensitiveParameter;

/**
 * The two things SQLite gets wrong by default for a site that writes from more
 * than one process — a request, a background console task, a worker.
 *
 * `foreign_keys` is off, which leaves the `ON DELETE CASCADE` the schema declares
 * inert: deleting a media or a user kept its `media_usage` / `login_token` rows,
 * and since SQLite hands a freed rowid to the next insert, such a row could later
 * re-attach to an unrelated entity. On it, SQLite enforces what MySQL already
 * does — which is also what makes the two backends fail the same way rather than
 * only in CI.
 *
 * `busy_timeout` is zero, so a second writer does not queue behind the first: it
 * gets `database is locked` on the spot, and whatever it was doing fails. Waiting
 * is what one wants of a lock held for the milliseconds a write takes.
 *
 * The driver is wrapped by hand, rather than handing the job to DBAL's own
 * `EnableForeignKeys`, because these statements are SQLite's and a MySQL
 * connection would choke on them. The priority puts this middleware first, hence
 * innermost: every later one hands over an AbstractDriverMiddleware, and the
 * driver would no longer be recognizable as SQLite.
 *
 * @see \Pushword\Core\EventListener\SqliteSchemaCommandListener for the one place
 *      `foreign_keys` has to come back off
 */
#[AsMiddleware(priority: 20)]
final class SqliteConnectionPragmas implements Middleware
{
    /** Long enough to outlast any write this application makes, short enough to still surface a deadlock. */
    private const int BUSY_TIMEOUT_MS = 5000;

    #[Override]
    public function wrap(Driver $driver): Driver
    {
        if (! $driver instanceof AbstractSQLiteDriver) {
            return $driver;
        }

        return new class($driver, self::BUSY_TIMEOUT_MS) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly int $busyTimeoutMs)
            {
                parent::__construct($driver);
            }

            #[Override]
            public function connect(
                #[SensitiveParameter]
                array $params,
            ): Connection {
                $connection = parent::connect($params);

                $connection->exec('PRAGMA foreign_keys=ON');
                $connection->exec('PRAGMA busy_timeout='.$this->busyTimeoutMs);

                return $connection;
            }
        };
    }
}
