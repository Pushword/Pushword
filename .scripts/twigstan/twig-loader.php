<?php

declare(strict_types=1);

use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\TwigStan\ResolveViewNodeVisitor;
use Pushword\TwigStanTool\AnalysisKernel;
use Symfony\Component\Dotenv\Dotenv;
use Twig\Loader\FilesystemLoader;

$root = \dirname(__DIR__, 2);
require_once $root.'/vendor/autoload.php';
require_once __DIR__.'/AnalysisKernel.php';

new Dotenv()->bootEnv($root.'/packages/dev-app/.env');

foreach ([
    'APP_ENV' => 'test',
    'PUSHWORD_TEST_VAR_DIR' => __DIR__.'/var/runtime',
    'PUSHWORD_TEST_MEDIA_DIR' => __DIR__.'/var/runtime/media',
    'PUSHWORD_TEST_MEDIA_CACHE_DIR' => __DIR__.'/var/runtime/media-cache',
    'PUSHWORD_TEST_FLAT_CONTENT_DIR' => __DIR__.'/var/runtime/content',
    'PUSHWORD_TEST_DATABASE_URL' => 'sqlite:///'.__DIR__.'/var/runtime/app.db',
] as $name => $value) {
    $_SERVER[$name] = $_ENV[$name] = $value;
    putenv($name.'='.$value);
}

$kernel = new AnalysisKernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$twig = $container->get('twig');
$registry = $container->get(SiteRegistry::class);
$host = getenv('PUSHWORD_TWIGSTAN_HOST') ?: $registry->getDefault()->getMainHost();
$site = $registry->findByHost($host) ?? throw new InvalidArgumentException('Unknown TwigStan host: '.$host);

$loader = $twig->getLoader();
if ($loader instanceof FilesystemLoader) {
    $loader->addPath($root.'/packages/docs/templates', 'PushwordDocs');
}

$twig->setCache(false);
$twig->addNodeVisitor(new ResolveViewNodeVisitor($site));

return $kernel;
