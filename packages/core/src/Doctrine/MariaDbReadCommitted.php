<?php

declare(strict_types=1);

namespace Pushword\Core\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Override;
use SensitiveParameter;

/** MariaDB snapshot isolation can reject a child insert after a concurrent parent update. */
#[AsMiddleware(priority: 30)]
final class MariaDbReadCommitted implements Middleware
{
    #[Override]
    public function wrap(Driver $driver): Driver
    {
        if (! $driver instanceof AbstractMySQLDriver) {
            return $driver;
        }

        return new class($driver) extends AbstractDriverMiddleware {
            #[Override]
            public function connect(
                #[SensitiveParameter]
                array $params,
            ): Connection {
                $connection = parent::connect($params);
                if (str_contains($connection->getServerVersion(), 'MariaDB')) {
                    $connection->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
                }

                return $connection;
            }
        };
    }
}
