<?php

use Pushword\Api\Controller\ApiControllerInterface;
use Pushword\Core\PushwordCoreBundle;
use Pushword\PageScanner\Controller\Api\LinkGraphApiController;
use Pushword\PageScanner\Controller\Api\PageScanApiController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$publicDir', '%pw.public_dir%')
        ->bind('$varDir', '%pw.var_dir%')
        ->bind('$pageScanInterval', '%pw.pushword_page_scanner.min_interval_between_scan%')
        ->bind('$linksToIgnore', '%pw.pushword_page_scanner.links_to_ignore%')
        ->bind('$externalUrlCache', service('cache.page_scanner'))
        ->bind('$externalUrlCacheTtl', '%pw.pushword_page_scanner.external_url_cache_ttl%')
        ->bind('$parallelBatchSize', '%pw.pushword_page_scanner.parallel_batch_size%')
        ->bind('$urlCheckTimeoutMs', '%pw.pushword_page_scanner.url_check_timeout_ms%')
        ->bind('$skipExternalUrlCheck', '%pw.pushword_page_scanner.skip_external_url_check%')
        ->bind('$errorsToIgnore', '%pw.pushword_page_scanner.errors_to_ignore%');

    // The JSON API controller requires the optional pushword/api package.
    $apiAvailable = interface_exists(ApiControllerInterface::class);
    $apiExclude = $apiAvailable ? [] : [__DIR__.'/../Controller/Api/'];

    $services->load('Pushword\PageScanner\\', __DIR__.'/../*')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
            ...$apiExclude,
        ]);

    if ($apiAvailable) {
        foreach ([PageScanApiController::class, LinkGraphApiController::class] as $apiController) {
            $services->set($apiController)
                ->autowire()
                ->tag('controller.service_arguments')
                ->tag('pushword.api.controller');
        }
    }
};
