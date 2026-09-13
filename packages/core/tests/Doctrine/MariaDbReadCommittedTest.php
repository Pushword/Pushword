<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver as PgSqlDriver;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as SqliteDriver;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Doctrine\MariaDbReadCommitted;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MariaDbReadCommittedTest extends KernelTestCase
{
    public function testOtherDatabaseDriversAreNotWrapped(): void
    {
        $middleware = new MariaDbReadCommitted();
        foreach ([new SqliteDriver(), new PgSqlDriver()] as $driver) {
            self::assertSame($driver, $middleware->wrap($driver));
        }
    }

    #[Group('integration')]
    public function testMariaDbConnectionUsesReadCommittedIsolation(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertContains($connection->fetchOne('SELECT 1'), [1, '1']);

        if (! $connection->getDatabasePlatform() instanceof MariaDBPlatform) {
            return;
        }

        self::assertSame('READ-COMMITTED', $connection->fetchOne('SELECT @@tx_isolation'));
    }
}
