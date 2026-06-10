<?php

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Pushword\AdminBlockEditor\Editor\EditorJsToolProviderInterface;
use Pushword\Api\Controller\ApiControllerInterface;
use Pushword\Core\PushwordCoreBundle;
use Pushword\Quiz\Controller\Api\QuizApiController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    // The EditorJS block provider requires the optional block editor bundle.
    $editorExclude = interface_exists(EditorJsToolProviderInterface::class) ? [] : [__DIR__.'/../Editor'];

    $apiAvailable = interface_exists(ApiControllerInterface::class);
    $apiExclude = $apiAvailable ? [] : [__DIR__.'/../Controller/Api'];

    $services->load('Pushword\Quiz\\', __DIR__.'/../*')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
            __DIR__.'/../Model', // plain value objects, not services
            __DIR__.'/../Admin', // loaded only when EasyAdmin is installed (below)
            ...$editorExclude,
            ...$apiExclude,
        ]);

    // Admin integration is optional: only wire it when EasyAdmin is installed.
    if (class_exists(AbstractCrudController::class)) {
        $services->load('Pushword\Quiz\Admin\\', __DIR__.'/../Admin');
    }

    // The validation endpoint is only wired when the optional API package is installed.
    if ($apiAvailable) {
        $services->set(QuizApiController::class)
            ->autowire()
            ->tag('controller.service_arguments')
            ->tag('pushword.api.controller');
    }
};
