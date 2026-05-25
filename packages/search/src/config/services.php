<?php

use Pushword\Core\PushwordCoreBundle;
use Pushword\StaticGenerator\Event\StaticPostGenerateEvent;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\MessageBusInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('string $indexDir', '%pw.search.index_dir%')
        ->bind('int $resultsPerPage', '%pw.search.results_per_page%')
        ->bind('array $searchableAttributes', '%pw.search.searchable_attributes%')
        ->bind('array $filterableAttributes', '%pw.search.filterable_attributes%')
        ->bind('string $staticMode', '%pw.search.static_mode%')
        ->bind('bool $incremental', '%pw.search.incremental%')
        ->bind('bool $indexOnStatic', '%pw.search.index_on_static%');

    // Incremental reindex needs Messenger; the static export needs the static
    // generator. Both are optional when the bundle is used on its own.
    $optionalExcludes = [];
    if (! interface_exists(MessageBusInterface::class)) {
        $optionalExcludes[] = __DIR__.'/../EventSubscriber/PageIndexInvalidator.php';
        $optionalExcludes[] = __DIR__.'/../MessageHandler';
    }

    if (! class_exists(StaticPostGenerateEvent::class)) {
        $optionalExcludes[] = __DIR__.'/../EventSubscriber/StaticSearchSubscriber.php';
    }

    $services->load('Pushword\Search\\', __DIR__.'/../*')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
            __DIR__.'/../Message',
            ...$optionalExcludes,
        ]);
};
