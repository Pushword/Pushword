<?php

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

$monoRepoBase = __DIR__.'/../../..';
$file = $monoRepoBase.'/vendor/autoload.php';

if (! file_exists($file)) {
    throw new RuntimeException('Install dependencies using Composer to run the test suite.');
}

$autoload = require $file;

function setTestEnv(string $key, string $value): void
{
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

function computeDbCacheHash(string $monoRepoBase): string
{
    $hashFiles = [];

    // Entity directories that affect DB schema
    $entityDirs = [
        $monoRepoBase.'/packages/core/src/Entity',
        $monoRepoBase.'/packages/conversation/src/Entity',
        $monoRepoBase.'/packages/flat/src/Entity',
        $monoRepoBase.'/packages/quiz/src/Entity',
        $monoRepoBase.'/packages/repurpose/src/Entity',
        $monoRepoBase.'/packages/snippet/src/Entity',
        $monoRepoBase.'/packages/version/src/Entity',
    ];

    foreach ($entityDirs as $dir) {
        if (! is_dir($dir)) {
            continue;
        }

        $finder = new Finder();
        $finder->files()->in($dir)->name('*.php')->sortByName();
        foreach ($finder as $f) {
            $hashFiles[] = $f->getRealPath();
        }
    }

    // Fixture and config files
    $extraFiles = [
        $monoRepoBase.'/packages/dev-app/src/DataFixtures/AppFixtures.php',
        $monoRepoBase.'/packages/dev-app/src/DataFixtures/WelcomePage.md',
        $monoRepoBase.'/packages/dev-app/src/DataFixtures/KitchenSink.md',
        $monoRepoBase.'/packages/dev-app/src/DataFixtures/reviews.yaml',
        $monoRepoBase.'/packages/core/src/Resources/config/packages/doctrine.php',
        __FILE__,
    ];

    foreach ($extraFiles as $f) {
        if (file_exists($f)) {
            $hashFiles[] = realpath($f);
        }
    }

    $ctx = hash_init('sha256');
    foreach ($hashFiles as $path) {
        hash_update($ctx, $path."\0".hash_file('sha256', $path)."\0");
    }

    return hash_final($ctx);
}

// Suppress fsockopen warnings from Panther checking if server is already running
set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
    // Suppress fsockopen connection refused warnings from Panther
    if (\E_WARNING === $errno && str_contains($errstr, 'fsockopen()') && str_contains($errstr, 'Connection refused')) {
        return true; // Suppress this warning
    }

    // Let other errors pass through
    return false;
}, \E_WARNING);
// ----------------------------------

new Dotenv()->loadEnv(__DIR__.'/.env');

// Some reset here
$fs = new Filesystem();
$runId = getenv('TEST_RUN_ID') ?: '';
// Paratest sets TEST_TOKEN per worker — append it for isolation
$testToken = getenv('TEST_TOKEN');
if (false !== $testToken && '' !== $testToken) {
    $runId = ('' !== $runId ? $runId.'-' : '').'w'.$testToken;
    setTestEnv('TEST_RUN_ID', $runId);
}

$segment = '' !== $runId ? '/'.$runId : '';
$testBaseDir = sys_get_temp_dir().'/com.github.pushword.pushword/tests'.$segment;

// Optionally run the suite against MariaDB/MySQL instead of SQLite.
// PUSHWORD_TEST_MYSQL_URL is a base DSN (e.g. mysql://user:pass@127.0.0.1:3306/pushword_test);
// each parallel worker gets its own database via a "_w<token>" suffix on the database name.
$mysqlBaseUrl = getenv('PUSHWORD_TEST_MYSQL_URL');
$useMysql = false !== $mysqlBaseUrl && '' !== $mysqlBaseUrl;
if ($useMysql) {
    $dbSuffix = (false !== $testToken && '' !== $testToken) ? '_w'.$testToken : '';
    $databaseUrl = str_contains($mysqlBaseUrl, '?')
        ? preg_replace('/\?/', $dbSuffix.'?', $mysqlBaseUrl, 1) ?? $mysqlBaseUrl
        : $mysqlBaseUrl.$dbSuffix;
} else {
    $databaseUrl = 'sqlite:///'.$testBaseDir.'/test.db';
}

// Export env vars used by the compiled container (pushword.php test config uses %env(...)%)
$envVars = [
    'PUSHWORD_TEST_MEDIA_DIR' => '' !== $runId ? $testBaseDir.'/media' : $monoRepoBase.'/packages/dev-app/media',
    'PUSHWORD_TEST_DATABASE_URL' => $databaseUrl,
    'PUSHWORD_TEST_FLAT_CONTENT_DIR' => '' !== $runId ? $testBaseDir.'/content/_host_' : $monoRepoBase.'/packages/dev-app/content/_host_',
    'PUSHWORD_TEST_VAR_DIR' => $testBaseDir.'/var',
];
$fs->mkdir($testBaseDir.'/var');
$fs->mkdir($testBaseDir.'/var/sessions');
foreach ($envVars as $key => $value) {
    setTestEnv($key, $value);
}

// Ensure each worker has its own lock directory and chrome data dir
if ('' !== $runId) {
    setTestEnv('LOCK_DSN', 'flock://'.$testBaseDir.'/locks');
    setTestEnv('PANTHER_CHROME_ARGUMENTS', '--headless --disable-gpu --disable-dev-shm-usage --no-sandbox --user-data-dir=/tmp/panther-chrome-'.$runId);
}

// Compute DB cache hash before wiping anything
$dbCacheDir = sys_get_temp_dir().'/com.github.pushword.pushword/test-db-cache';
$dbCacheHash = computeDbCacheHash($monoRepoBase);

$fs->remove($testBaseDir.'/var/dev/cache');
$fs->remove($testBaseDir.'/var/dev/log');
$fs->remove($testBaseDir.'/var/test/log');

// Media isolation: mirror backup into the run-specific tmp dir (or dev-app/media if no run ID)
$mediaDir = '' !== $runId
    ? $testBaseDir.'/media'
    : $monoRepoBase.'/packages/dev-app/media';
$fs->remove($mediaDir);
$fs->mirror($monoRepoBase.'/packages/dev-app/media~', $mediaDir);

// Content isolation: ensure clean content directory in tmp
if ('' !== $runId) {
    $fs->remove($testBaseDir.'/content');
    $fs->mkdir($testBaseDir.'/content');
}

// Mirror pushword.piedweb.com docs content so flat sync tests exercise real pages
$piedwebContentDir = ('' !== $runId ? $testBaseDir.'/content' : $monoRepoBase.'/packages/dev-app/content').'/pushword.piedweb.com';
$fs->remove($piedwebContentDir);
$fs->mirror($monoRepoBase.'/packages/docs/content', $piedwebContentDir);

$cachedDbFile = $dbCacheDir.'/'.$dbCacheHash.'.sqlite';
$dbTargetPath = $testBaseDir.'/test.db';
$lockFile = $dbCacheDir.'/'.$dbCacheHash.'.lock';

// Export cached DB path so tests can restore pristine state if needed
setTestEnv('PUSHWORD_TEST_DB_CACHE_FILE', $cachedDbFile);

// Use file locking to prevent race conditions between ParaTest workers
// Only the DB cache check/creation is locked; kernel boot happens outside the lock
$fs->mkdir($dbCacheDir);
$fs->mkdir(\dirname($dbTargetPath));

// MariaDB/MySQL can't be served from the sqlite file cache; each run rebuilds its schema.
$lockHandle = false;
if (! $useMysql) {
    $lockHandle = fopen($lockFile, 'c+');
    if (false !== $lockHandle) {
        flock($lockHandle, \LOCK_EX);
    }
}

$cacheHit = ! $useMysql && file_exists($cachedDbFile);

if ($cacheHit) {
    // Cache hit: copy pristine DB, skip Doctrine commands
    $fs->copy($cachedDbFile, $dbTargetPath, true);

    // Release lock immediately — other workers can copy the cache in parallel
    if (false !== $lockHandle) {
        flock($lockHandle, \LOCK_UN);
        fclose($lockHandle);
        $lockHandle = false;
    }
}

$kernel = new Kernel('test', true);
$kernel->boot();

if (! $cacheHit) {
    // Cache miss: run Doctrine commands to build DB (lock still held)
    $application = new Application($kernel);
    $application->setAutoExit(false);

    $runCommand = static function (string $command, array $args = []) use ($application): void {
        $application->run(new ArrayInput(['command' => $command] + $args), new ConsoleOutput());
    };

    $runCommand('doctrine:database:drop', ['--no-interaction' => true, '--force' => true, '--if-exists' => true]);
    $runCommand('doctrine:database:create', ['--no-interaction' => true, '--quiet' => true]);
    $runCommand('doctrine:schema:create', ['--quiet' => true]);
    $runCommand('doctrine:fixtures:load', ['--no-interaction' => true, '--append' => false]);
    $runCommand('pw:user:create', ['email' => 'admin@example.tld', 'password' => 'mySecr3tpAssword', 'role' => 'ROLE_SUPER_ADMIN']);

    unset($runCommand, $application);

    // Save pristine DB to cache (sqlite only)
    if (! $useMysql && file_exists($dbTargetPath)) {
        $fs->copy($dbTargetPath, $cachedDbFile, true);
    }

    // Release lock after cache is written
    if (false !== $lockHandle) {
        flock($lockHandle, \LOCK_UN);
        fclose($lockHandle);
    }
}
