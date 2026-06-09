<?php

namespace Pushword\Core\Tests\Perf;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use ReflectionProperty;

/**
 * Counts the SQL statements a connection issues by wrapping its live DBAL driver
 * with a logging middleware. The existing Connection instance is reused (by
 * Doctrine and by the repositories); we swap its `driver` via reflection and
 * close() so the next connect() goes through the wrapped driver.
 */
trait QueryCountingTrait
{
    private PerfQueryCounter $queryCounter;

    private ?Connection $countedConnection = null;

    private ?DriverInterface $originalDriver = null;

    private function startCountingQueries(Connection $connection): void
    {
        $this->queryCounter = new PerfQueryCounter();

        $driverProp = new ReflectionProperty($connection, 'driver');
        /** @var DriverInterface $original */
        $original = $driverProp->getValue($connection);
        $this->originalDriver = $original;
        $this->countedConnection = $connection;

        $wrapped = new LoggingMiddleware($this->queryCounter)->wrap($original);
        $driverProp->setValue($connection, $wrapped);
        $connection->close();
    }

    private function stopCountingQueries(): void
    {
        if (! $this->countedConnection instanceof Connection || ! $this->originalDriver instanceof DriverInterface) {
            return;
        }

        $driverProp = new ReflectionProperty($this->countedConnection, 'driver');
        $driverProp->setValue($this->countedConnection, $this->originalDriver);

        $this->countedConnection->close();

        $this->countedConnection = null;

        $this->originalDriver = null;
    }

    /**
     * Execute $fn and return the number of SQL statements it issued.
     */
    private function countQueries(callable $fn): int
    {
        $before = $this->queryCounter->count;
        $fn();

        return $this->queryCounter->count - $before;
    }
}
