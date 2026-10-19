<?php

declare(strict_types=1);

use App\Kernel;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

$runId = getenv('TEST_RUN_ID');
if (false === $runId || ! str_starts_with($runId, 'editor-')) {
    throw new RuntimeException('Start this server through the Playwright configuration.');
}

require __DIR__.'/../../../core/tests/bootstrap.php';

$kernel = new Kernel('test', true);
$kernel->boot();
$entityManager = $kernel->getContainer()->get('doctrine.orm.default_entity_manager');
$pages = [];
foreach (['chrome', 'msedge', 'firefox'] as $browser) {
    $page = new Page();
    $page->host = 'admin-block-editor.test';
    $page->locale = 'en';
    $page->slug = 'browser-'.$browser;
    $page->h1 = 'Browser '.$browser;
    $page->mainContent = '{"blocks":[{"type":"paragraph","data":{"text":"Baseline"}}]}';
    $entityManager->persist($page);
    $entityManager->flush();
    $pages[$browser] = '/admin/page/'.$page->id.'/edit';
}

$application = new Application($kernel);
$application->setAutoExit(false);
$status = $application->run(new ArrayInput([
    'command' => 'assets:install', '--symlink' => true, '--relative' => true,
]), new ConsoleOutput());
if (0 !== $status) {
    exit($status);
}

$directory = sys_get_temp_dir().'/com.github.pushword.pushword/tests/'.$runId;
$filesystem = new Filesystem();
$filesystem->dumpFile($directory.'/browser-pages.json', json_encode($pages, \JSON_THROW_ON_ERROR));
$kernel->shutdown();

$publicDirectory = realpath(__DIR__.'/../../../dev-app/public');
if (false === $publicDirectory) {
    throw new RuntimeException('The dev app public directory is missing.');
}

$server = new Process([\PHP_BINARY, '-dvariables_order=EGPCS', '-S', '127.0.0.1:9079', '-t', $publicDirectory], $publicDirectory, timeout: null);
register_shutdown_function(static function () use ($server, $filesystem, $directory): void {
    $server->stop();
    $filesystem->remove($directory);
});
pcntl_async_signals(true);
pcntl_signal(\SIGTERM, static function (): never { exit; });
pcntl_signal(\SIGINT, static function (): never { exit; });
exit($server->run(static function (string $type, string $output) use ($filesystem): void {
    $filesystem->appendToFile(__DIR__.'/../../test-results/server.log', $output);
}));
