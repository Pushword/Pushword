<?php

declare(strict_types=1);

use App\Kernel;
use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\Dotenv\Dotenv;

if (3 !== count($argv)) {
    throw new InvalidArgumentException('Usage: render-downstream.php <site-root> <new-output.ndjson>');
}

[, $project, $output] = $argv;
chdir($project);
require getcwd().'/vendor/autoload.php';

new Dotenv()->bootEnv('.env');
$kernel = new Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$pages = $container->get('doctrine')->getRepository(Page::class)->findAll();
$sites = $container->get(SiteRegistry::class);
$factory = $container->get(ContentPipelineFactory::class);
$stream = fopen($output, 'x');
if (false === $stream) {
    throw new RuntimeException('Could not create corpus file');
}

chmod($output, 0600);

$counts = ['pages' => count($pages), 'rendered' => 0, 'empty' => 0, 'errors' => []];
foreach ($pages as $page) {
    try {
        $sites->switchSite($page->host);
        $html = $factory->get($page)->getMainContent();
        if ('' === $html) {
            ++$counts['empty'];
        } else {
            ++$counts['rendered'];
        }

        fwrite($stream, json_encode([
            'page' => $page->host.'/'.$page->slug,
            'html' => $html,
            'toc' => null !== $page->getCustomProperty('toc') || null !== $page->getCustomProperty('tocTitle'),
        ], \JSON_THROW_ON_ERROR)."\n");
    } catch (Throwable $error) {
        $name = $error::class;
        $counts['errors'][$name] = ($counts['errors'][$name] ?? 0) + 1;
    } finally {
        $factory->reset();
    }
}

fclose($stream);
$kernel->shutdown();
echo json_encode($counts, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR)."\n";
if ([] !== $counts['errors']) {
    exit(1);
}
