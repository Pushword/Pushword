<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Pushword\Conversation\Flat\ConversationSync;
use Pushword\Core\PushwordCoreBundle;
use Pushword\Flat\Admin\FlatSyncNotifier;
use Pushword\Flat\Controller\Admin\NotificationCrudController;
use Pushword\Flat\Controller\FlatLockApiController;
use Pushword\Flat\Converter\FlatPropertyConverterInterface;
use Pushword\Flat\FlatFileSync;
use Pushword\Flat\Service\AdminNotificationService;
use Pushword\Flat\Service\DeferredExportProcessor;
use Pushword\Flat\Service\FlatApiTokenValidator;
use Pushword\Flat\Service\FlatChangeDetector;
use Pushword\Flat\Service\FlatLockManager;
use Pushword\Flat\Service\GitAutoCommitter;
use Pushword\Flat\Sync\ConflictResolver;
use Pushword\Flat\Sync\ConversationSyncInterface;
use Pushword\Flat\Sync\PageSync;
use Pushword\Flat\Sync\SyncStateManager;
use Pushword\Flat\Twig\FlatLockExtension;
use ReflectionClass;

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

    // PageSync configuration
    $services->set(PageSync::class)
        ->arg('$excludeFiles', '%pw.pushword_flat.exclude_files%');

    // SyncStateManager configuration
    $services->set(SyncStateManager::class)
        ->arg('$varDir', '%kernel.project_dir%/var');

    // FlatLockManager configuration
    $services->set(FlatLockManager::class)
        ->arg('$varDir', '%kernel.project_dir%/var')
        ->arg('$defaultTtl', '%pw.pushword_flat.lock_ttl%')
        ->arg('$webhookDefaultTtl', '%pw.pushword_flat.webhook_lock_default_ttl%');

    // FlatApiTokenValidator - for webhook authentication
    $services->set(FlatApiTokenValidator::class)
        ->autowire();

    // FlatLockApiController - for webhook endpoints
    $services->set(FlatLockApiController::class)
        ->autowire()
        ->autoconfigure()
        ->tag('controller.service_arguments');

    // FlatLockExtension - Twig functions for lock status
    $services->set(FlatLockExtension::class)
        ->autowire()
        ->autoconfigure()
        ->tag('twig.extension');

    // FlatChangeDetector configuration
    $services->set(FlatChangeDetector::class)
        ->arg('$cacheTtl', '%pw.pushword_flat.change_detection_cache_ttl%')
        ->arg('$autoLockOnChanges', '%pw.pushword_flat.auto_lock_on_flat_changes%');

    // DeferredExportProcessor configuration
    $services->set(DeferredExportProcessor::class)
        ->arg('$varDir', '%kernel.project_dir%/var')
        ->arg('$autoExportEnabled', '%pw.pushword_flat.auto_export_enabled%');

    // GitAutoCommitter configuration
    $services->set(GitAutoCommitter::class)
        ->arg('$enabled', '%pw.pushword_flat.auto_git_commit%');

    // FlatSyncNotifier - make it optional (only works if admin bundle is present)
    $services->set(FlatSyncNotifier::class)
        ->autowire()
        ->autoconfigure();

    // FlatFileSync - inject optional ConversationSync (provided by conversation bundle)
    $services->set(FlatFileSync::class)
        ->arg('$conversationSync', service(ConversationSyncInterface::class)->nullOnInvalid());

    // AdminNotificationService - for persistent notifications and email alerts
    // Uses NotificationEmailSender (autowired), with flat-specific config passed via DI
    $services->set(AdminNotificationService::class)
        ->arg('$emailRecipients', '%pw.pushword_flat.notification_email_recipients%')
        ->arg('$emailFrom', '%pw.pushword_flat.notification_email_from%');

    // ConflictResolver - inject optional notification service
    $services->set(ConflictResolver::class)
        ->arg('$notificationService', service(AdminNotificationService::class)->nullOnInvalid());

    // NotificationCrudController - admin UI for notifications
    $services->set(NotificationCrudController::class)
        ->autowire()
        ->autoconfigure()
        ->tag('controller.service_arguments');

    // Register Conversation Flat services when conversation bundle is installed
    // This must be done here (in flat bundle) to ensure FlatFileContentDirFinder is already registered
    if (class_exists(ConversationSync::class)) {
        $conversationFlatDir = \dirname((string) new ReflectionClass(ConversationSync::class)->getFileName());
        $services->load('Pushword\Conversation\Flat\\', $conversationFlatDir.'/');

        $services->alias(ConversationSyncInterface::class, ConversationSync::class);
        $services->alias('pushword.flat.conversation_sync', ConversationSync::class);
    }
};
