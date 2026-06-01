<?php

use Pushword\Api\Controller\ApiControllerInterface;
use Pushword\Api\Controller\DocsApiController;
use Pushword\Api\Controller\MediaApiController;
use Pushword\Api\Controller\PageApiController;
use Pushword\Api\Controller\PageRedirectionApiController;
use Pushword\Api\Routing\ApiControllerRouteLoader;
use Pushword\Api\Service\OpenApiBuilder;
use Pushword\Api\Workflow\WorkflowGateInterface;
use Pushword\Core\PushwordCoreBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->instanceof(ApiControllerInterface::class)
        ->tag('pushword.api.controller');

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Pushword\Api\\', __DIR__.'/../*')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
            __DIR__.'/../Workflow/WorkflowGateInterface.php',
        ]);

    foreach ([MediaApiController::class, PageRedirectionApiController::class, DocsApiController::class] as $controllerClass) {
        $services->set($controllerClass)
            ->autowire()
            ->tag('controller.service_arguments')
            ->tag('pushword.api.controller');
    }

    $services->set(PageApiController::class)
        ->autowire()
        ->arg('$deleteStrategy', '%pw.api.delete_strategy%')
        ->arg('$workflowGate', service(WorkflowGateInterface::class)->nullOnInvalid())
        ->tag('controller.service_arguments')
        ->tag('pushword.api.controller');

    $services->set(OpenApiBuilder::class)
        ->arg('$controllers', tagged_iterator('pushword.api.controller'));

    // Single loader that registers the routes of every tagged API controller,
    // including those contributed by optional bundles.
    $services->set(ApiControllerRouteLoader::class)
        ->arg('$controllers', tagged_iterator('pushword.api.controller'))
        ->tag('routing.loader');
};
