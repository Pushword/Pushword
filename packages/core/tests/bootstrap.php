<?php

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

$monoRepoBase = __DIR__.'/../../..';
$file = $monoRepoBase.'/vendor/autoload.php';

if (! file_exists($file)) {
    throw new RuntimeException('Install dependencies using Composer to run the test suite.');
}
$autoload = require $file;

(new Dotenv())->loadEnv(__DIR__.'/.env');

// Some reset here
$fs = new Filesystem();
$fs->remove('/tmp/com.github.pushword.pushword/tests/var/dev/cache');
@$fs->remove('/tmp/com.github.pushword.pushword/tests/var/test/cache');
@$fs->remove('/tmp/com.github.pushword.pushword/tests/var/dev/log');
@$fs->remove('/tmp/com.github.pushword.pushword/tests/var/test/log');
@$fs->remove($monoRepoBase.'/packages/skeleton/var/app.db');
@$fs->remove($monoRepoBase.'/packages/skeleton/var/page-scan');
@$fs->remove($monoRepoBase.'/packages/skeleton/var/PageUpdateNotifier');
@$fs->remove($monoRepoBase.'/packages/skeleton/media');
@$fs->mirror($monoRepoBase.'/packages/skeleton/media~', $monoRepoBase.'/packages/skeleton/media');

$kernel = new Kernel('test', true);
$kernel->boot();
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

unset($input, $application);
