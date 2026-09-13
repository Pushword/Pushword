<?php

declare(strict_types=1);

use App\Kernel;
use Pushword\Core\Controller\PageController;
use Pushword\Core\PushwordCoreBundle;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Service\ContentSplitter;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Site\RequestContext;
use Pushword\StaticGenerator\Generator\StaticPageRenderer;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

if (6 !== $argc) {
    throw new InvalidArgumentException('Usage: e2e-altimood.php <site-copy> <source-snapshot> <url> <public|preview|static-render> <renders>');
}

[, $site, $root, $url, $kind, $renders] = $argv;
if (! in_array($kind, ['public', 'preview', 'static-render'], true) || (int) $renders < 1) {
    throw new InvalidArgumentException('Invalid benchmark mode or render count.');
}

chdir($site);
require $site.'/vendor/autoload.php';
require $root.'/vendor/autoload.php';

// Override both Composer classmaps with the frozen source and isolated site.
spl_autoload_register(static function (string $class) use ($site, $root): void {
    foreach ([
        'App\\' => $site.'/src/',
        'Pushword\\Core\\' => $root.'/packages/core/src/',
        'Pushword\\StaticGenerator\\' => $root.'/packages/static-generator/src/',
    ] as $prefix => $directory) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $file = $directory.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($file)) {
            require $file;
        }

        return;
    }
}, true, true);

new Dotenv()->bootEnv($site.'/.env');
$kernel = new Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
foreach ([MarkdownParser::class, ContentSplitter::class, PushwordCoreBundle::class] as $class) {
    if (! str_starts_with(new ReflectionClass($class)->getFileName(), $root.'/packages/core/src/')) {
        throw new RuntimeException('The benchmark did not load the current Pushword core.');
    }
}

if (! str_starts_with(new ReflectionClass(StaticPageRenderer::class)->getFileName(), $root.'/packages/static-generator/src/')) {
    throw new RuntimeException('The benchmark did not load the current static generator.');
}

if ('public' !== $kind) {
    $host = parse_url($url, \PHP_URL_HOST);
    $slug = trim((string) parse_url($url, \PHP_URL_PATH), '/');
    $page = $container->get(PageRepository::class)->getPage('' === $slug ? 'homepage' : $slug, $host);
    if (null === $page) {
        throw new RuntimeException('Page not found: '.$url);
    }

    $renderer = $container->get('preview' === $kind ? PageController::class : StaticPageRenderer::class);
}

$results = [];
for ($i = 0; $i < (int) $renders; ++$i) {
    $request = Request::create($url);
    $start = hrtime(true);
    if ('public' === $kind) {
        $response = $kernel->handle($request);
    } elseif ('static-render' === $kind) {
        $response = $renderer->render($request, $page);
    } else {
        $context = $container->get(RequestContext::class);
        $stack = $container->get(RequestStack::class);
        $context->switchSite($page);
        $context->setRequestContext($page->host, 'pushword_page', $slug, 1);
        $stack->push($request);

        try {
            $response = $renderer->showPage($page);
        } finally {
            $stack->pop();
            $context->reset();
        }
    }

    $elapsed = (hrtime(true) - $start) / 1e6;
    $body = $response->getContent();
    $results[] = [
        'status' => $response->getStatusCode(),
        'bytes' => strlen($body),
        'sha256' => hash('sha256', $body),
        'milliseconds' => $elapsed,
        'php_peak_bytes' => memory_get_peak_usage(true),
    ];
    if ('public' === $kind) {
        $kernel->terminate($request, $response);
    }
}

$nativeMarkdown = $kernel->getContainer()->getParameter('pw.native_markdown_renderer');
$nativeSplit = $kernel->getContainer()->getParameter('pw.native_content_analyzer');
$kernel->shutdown();
echo json_encode([
    'native_configured' => $nativeMarkdown === $root.'/pushword-content-analyzer' && $nativeSplit === $nativeMarkdown,
    'results' => $results,
], \JSON_THROW_ON_ERROR)."\n";
