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

// Suppress fsockopen warnings from Panther checking if server is already running
set_error_handler(static function ($errno, $errstr, $errfile, $errline): bool {
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
    putenv('TEST_RUN_ID='.$runId);
    $_ENV['TEST_RUN_ID'] = $runId;
    $_SERVER['TEST_RUN_ID'] = $runId;
}

$segment = '' !== $runId ? '/'.$runId : '';
$testBaseDir = sys_get_temp_dir().'/com.github.pushword.pushword/tests'.$segment;

// Ensure each worker has its own lock directory and chrome data dir
if ('' !== $runId) {
    $lockDsn = 'flock://'.$testBaseDir.'/locks';
    putenv('LOCK_DSN='.$lockDsn);
    $_ENV['LOCK_DSN'] = $lockDsn;
    $_SERVER['LOCK_DSN'] = $lockDsn;

    $chromeArgs = '--headless --disable-gpu --disable-dev-shm-usage --no-sandbox --user-data-dir=/tmp/panther-chrome-'.$runId;
    putenv('PANTHER_CHROME_ARGUMENTS='.$chromeArgs);
    $_ENV['PANTHER_CHROME_ARGUMENTS'] = $chromeArgs;
    $_SERVER['PANTHER_CHROME_ARGUMENTS'] = $chromeArgs;
}

// Compute DB cache hash before wiping anything
$dbCacheDir = sys_get_temp_dir().'/com.github.pushword.pushword/test-db-cache';
$dbCacheHash = (static function () use ($monoRepoBase): string {
    $hashFiles = [];

    // Entity directories that affect DB schema
    $entityDirs = [
        $monoRepoBase.'/packages/core/src/Entity',
        $monoRepoBase.'/packages/conversation/src/Entity',
        $monoRepoBase.'/packages/flat/src/Entity',
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
        $monoRepoBase.'/packages/skeleton/src/DataFixtures/AppFixtures.php',
        $monoRepoBase.'/packages/skeleton/src/DataFixtures/WelcomePage.md',
        $monoRepoBase.'/packages/skeleton/src/DataFixtures/KitchenSink.md',
        $monoRepoBase.'/packages/skeleton/src/DataFixtures/reviews.yaml',
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
})();

$fs->remove($testBaseDir.'/var/dev/cache');
$fs->remove($testBaseDir.'/var/test/cache');
$fs->remove($testBaseDir.'/var/dev/log');
$fs->remove($testBaseDir.'/var/test/log');

// Media isolation: mirror backup into the run-specific tmp dir (or skeleton/media if no run ID)
$mediaDir = '' !== $runId
    ? $testBaseDir.'/media'
    : $monoRepoBase.'/packages/skeleton/media';
$fs->remove($mediaDir);
$fs->mirror($monoRepoBase.'/packages/skeleton/media~', $mediaDir);

// Content isolation: ensure clean content directory in tmp
if ('' !== $runId) {
    $fs->remove($testBaseDir.'/content');
    $fs->mkdir($testBaseDir.'/content');
}

$cachedDbFile = $dbCacheDir.'/'.$dbCacheHash.'.sqlite';
$dbTargetPath = $testBaseDir.'/test.db';
$cacheHit = file_exists($cachedDbFile);

if ($cacheHit) {
    // Cache hit: copy pristine DB, skip Doctrine commands
    $fs->mkdir(\dirname($dbTargetPath));
    $fs->copy($cachedDbFile, $dbTargetPath, true);
}

$kernel = new Kernel('test', true);
$kernel->boot();

if (! $cacheHit) {
    // Cache miss: run Doctrine commands to build DB
    $application = new Application($kernel);
    $application->setAutoExit(false);

    $input = new ArrayInput(['command' => 'doctrine:database:drop', '--no-interaction' => true, '--force' => true]);
    $application->run($input, new ConsoleOutput());

    $input = new ArrayInput(['command' => 'doctrine:database:create', '--no-interaction' => true, '--quiet' => true]);
    $application->run($input, new ConsoleOutput());

    $input = new ArrayInput(['command' => 'doctrine:schema:create', '--quiet' => true]);
    $application->run($input, new ConsoleOutput());

    $input = new ArrayInput(['command' => 'doctrine:fixtures:load', '--no-interaction' => true, '--append' => false]);
    $application->run($input, new ConsoleOutput());

    $input = new ArrayInput([
        'command' => 'pw:user:create',
        'email' => 'admin@example.tld',
        'password' => 'mySecr3tpAssword',
        'role' => 'ROLE_SUPER_ADMIN',
    ]);
    $application->run($input, new ConsoleOutput());

    unset($input, $application);

    // Save pristine DB to cache
    if (file_exists($dbTargetPath)) {
        $fs->mkdir($dbCacheDir);
        $fs->copy($dbTargetPath, $cachedDbFile, true);
    }
}
