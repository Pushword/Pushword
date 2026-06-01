<?php

use Pushword\Api\Controller\ApiControllerInterface;
use Pushword\Conversation\Controller\Api\ConversationApiController;
use Pushword\Conversation\Controller\Api\ReviewApiController;
use Pushword\Conversation\Flat\ConversationSync;
use Pushword\Core\PushwordCoreBundle;
use Pushword\Flat\Sync\ConversationSyncInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $apiInstalled = interface_exists(ApiControllerInterface::class);

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $apiExcludes = $apiInstalled ? [] : [__DIR__.'/../../../src/Controller/Api/'];

    $services->load('Pushword\Conversation\\', __DIR__.'/../../../src/*')
        ->exclude([
            __DIR__.'/../../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
            __DIR__.'/../../../src/Flat/',
            ...$apiExcludes,
        ]);

    $services->load('Pushword\Conversation\Flat\\', __DIR__.'/../../../src/Flat/');

    // Register ConversationSync as the implementation of ConversationSyncInterface
    $services->alias(ConversationSyncInterface::class, ConversationSync::class);
    $services->alias('pushword.flat.conversation_sync', ConversationSync::class);

    if ($apiInstalled) {
        $services->set(ConversationApiController::class)
            ->autowire()
            ->tag('controller.service_arguments')
            ->tag('pushword.api.controller');
        $services->set(ReviewApiController::class)
            ->autowire()
            ->tag('controller.service_arguments')
            ->tag('pushword.api.controller');
    }
};
