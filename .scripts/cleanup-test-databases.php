<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;

require dirname(__DIR__).'/vendor/autoload.php';

$baseUrl = getenv('PUSHWORD_TEST_DATABASE_BASE_URL') ?: getenv('PUSHWORD_TEST_MYSQL_URL');
$runId = getenv('TEST_RUN_ID');
if (false === $baseUrl || false === $runId || '' === $runId) {
    return;
}

$params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($baseUrl);
$prefix = $params['dbname'].'_r'.substr(hash('sha256', $runId), 0, 12);
$postgresql = 'pdo_pgsql' === $params['driver'];
if ($postgresql) {
    $params['dbname'] = 'postgres';
} else {
    unset($params['dbname']);
}

$connection = DriverManager::getConnection($params);
$names = $connection->fetchFirstColumn($postgresql ? 'SELECT datname FROM pg_database' : 'SHOW DATABASES');
foreach ($names as $name) {
    if ($name !== $prefix && ! preg_match('/^'.preg_quote($prefix, '/').'_w[0-9]+$/', $name)) {
        continue;
    }

    $connection->executeStatement('DROP DATABASE '.$connection->getDatabasePlatform()->quoteIdentifier($name));
}
