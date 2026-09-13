<?php

declare(strict_types=1);

use App\Kernel;
use Pushword\Core\Component\EntityFilter\Filter\LinkCollector;
use Pushword\Core\Component\EntityFilter\Filter\Markdown;
use Pushword\Core\Component\EntityFilter\Filter\ShowMore;
use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;
use Symfony\Component\Dotenv\Dotenv;
use Twig\Environment;

if (3 !== $argc) {
    throw new InvalidArgumentException('Usage: render-markdown-downstream.php <site-root> <private-output.ndjson>');
}

$siteRoot = realpath($argv[1]);
if (false === $siteRoot || ! is_file($siteRoot.'/vendor/autoload.php')) {
    throw new InvalidArgumentException('The site needs an installed vendor/autoload.php.');
}

$output = $argv[2];
if (! str_starts_with($output, '/')) {
    $output = getcwd().'/'.$output;
}

chdir($siteRoot);
require $siteRoot.'/vendor/autoload.php';
new Dotenv()->bootEnv('.env');
$kernel = new Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$pages = $container->get('doctrine')->getRepository(Page::class)->findAll();
$sites = $container->get(SiteRegistry::class);
$factory = $container->get(ContentPipelineFactory::class);
$parser = new class($container->get(LinkProvider::class), $container->get(MediaExtension::class), $sites, $container->get(Environment::class)) extends MarkdownParser {
    /** @var list<array{string, string}> */
    public array $blocks = [];

    public function transform(string $text): string
    {
        $html = parent::transform($text);
        $this->blocks[] = [$text, $html];

        return $html;
    }
};
$filter = new Markdown($parser);
$stream = fopen($output, 'x');
if (false === $stream) {
    throw new RuntimeException('Create a new private output path.');
}

chmod($output, 0600);
$counts = ['pages' => count($pages), 'blocks' => 0, 'errors' => []];
foreach ($pages as $page) {
    $parser->blocks = [];

    try {
        $sites->switchSite($page->host);
        $manager = $factory->get($page)->getLegacyManager();
        $before = $manager->applyFilters($page->mainContent, [ShowMore::class, LinkCollector::class]);
        $filter->apply($before, $page, $manager, 'MainContent');
        foreach ($parser->blocks as $index => [$source, $html]) {
            fwrite($stream, json_encode([
                'page' => $page->host.'/'.$page->slug,
                'block' => $index,
                'markdown' => $source,
                'php' => $html,
                'pre_class' => $sites->get()->getStr(SiteConfig::FENCED_CODE_PRE_CLASS),
            ], \JSON_THROW_ON_ERROR)."\n");
            ++$counts['blocks'];
        }
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
