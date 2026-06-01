<?php

use Pushword\Api\Workflow\WorkflowGateInterface;
use Pushword\Core\PushwordCoreBundle;
use Pushword\PageWorkflow\Controller\Api\PageWorkflowApiController;
use Pushword\PageWorkflow\Pending\FilePendingModificationStorage;
use Pushword\PageWorkflow\Pending\PendingModificationStorageInterface;
use Pushword\PageWorkflow\Workflow\ApiWorkflowGate;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $apiInstalled = interface_exists(WorkflowGateInterface::class);

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$requireApprovalBeforePublish', '%pushword_page_workflow.require_approval_before_publish%')
        ->bind('$varDir', '%kernel.project_dir%/var');

    $apiExcludes = $apiInstalled ? [] : [
        __DIR__.'/../Controller/Api',
        __DIR__.'/../Workflow/ApiWorkflowGate.php',
    ];

    $services->load('Pushword\PageWorkflow\\', __DIR__.'/../*')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
            __DIR__.'/../Pending/PendingModification.php',
            ...$apiExcludes,
        ]);

    $services->alias(PendingModificationStorageInterface::class, FilePendingModificationStorage::class);

    $services->load('Pushword\PageWorkflow\Controller\\', __DIR__.'/../Controller')
        ->exclude($apiExcludes)
        ->tag('controller.service_arguments');

    if ($apiInstalled) {
        $services->alias(WorkflowGateInterface::class, ApiWorkflowGate::class);
        $services->set(PageWorkflowApiController::class)
            ->autowire()
            ->tag('controller.service_arguments')
            ->tag('pushword.api.controller');
    }
};
