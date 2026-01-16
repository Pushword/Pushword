<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Pushword\Core\PushwordCoreBundle;
use Pushword\Flat\Admin\FlatSyncNotifier;
use Pushword\Flat\Converter\FlatPropertyConverterInterface;
use Pushword\Flat\FlatFileSync;
use Pushword\Flat\Service\DeferredExportProcessor;
use Pushword\Flat\Service\FlatChangeDetector;
use Pushword\Flat\Service\FlatLockManager;
use Pushword\Flat\Sync\ConversationSyncInterface;
use Pushword\Flat\Sync\SyncStateManager;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->bind('$projectDir', '%kernel.project_dir%')
            ->bind('$mediaDir', '%pw.media_dir%')
    ;

    // Tag all implementations of FlatPropertyConverterInterface
    $services->instanceof(FlatPropertyConverterInterface::class)
        ->tag('pushword.flat.property_converter');

    $services->load('Pushword\Flat\\', __DIR__.'/../../src/')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
        ]);

    // SyncStateManager configuration
    $services->set(SyncStateManager::class)
        ->arg('$varDir', '%kernel.project_dir%/var');

    // FlatLockManager configuration
    $services->set(FlatLockManager::class)
        ->arg('$varDir', '%kernel.project_dir%/var')
        ->arg('$defaultTtl', '%pw.pushword_flat.lock_ttl%');

    // FlatChangeDetector configuration
    $services->set(FlatChangeDetector::class)
        ->arg('$cacheTtl', '%pw.pushword_flat.change_detection_cache_ttl%')
        ->arg('$autoLockOnChanges', '%pw.pushword_flat.auto_lock_on_flat_changes%');

    // DeferredExportProcessor configuration
    $services->set(DeferredExportProcessor::class)
        ->arg('$useBackgroundProcess', '%pw.pushword_flat.use_background_export%')
        ->arg('$autoExportEnabled', '%pw.pushword_flat.auto_export_enabled%');

    // FlatSyncNotifier - make it optional (only works if admin bundle is present)
    $services->set(FlatSyncNotifier::class)
        ->autowire()
        ->autoconfigure();

    // FlatFileSync - inject optional ConversationSync (provided by conversation bundle)
    $services->set(FlatFileSync::class)
        ->arg('$conversationSync', service(ConversationSyncInterface::class)->nullOnInvalid());
};
