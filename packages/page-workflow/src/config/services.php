<?php

use Pushword\Core\PushwordCoreBundle;
use Pushword\PageWorkflow\Pending\FilePendingModificationStorage;
use Pushword\PageWorkflow\Pending\PendingModificationStorageInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$requireApprovalBeforePublish', '%pushword_page_workflow.require_approval_before_publish%')
        ->bind('$varDir', '%kernel.project_dir%/var');

    $services->load('Pushword\PageWorkflow\\', __DIR__.'/../*')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
            __DIR__.'/../Pending/PendingModification.php',
        ]);

    $services->alias(PendingModificationStorageInterface::class, FilePendingModificationStorage::class);

    $services->load('Pushword\PageWorkflow\Controller\\', __DIR__.'/../Controller')
        ->tag('controller.service_arguments');
};
