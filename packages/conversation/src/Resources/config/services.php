<?php

declare(strict_types=1);

use Pushword\Conversation\Flat\ConversationSync;
use Pushword\Core\PushwordCoreBundle;
use Pushword\Flat\Sync\ConversationSyncInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Pushword\Conversation\\', __DIR__.'/../../../src/*')
        ->exclude([
            __DIR__.'/../../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
            __DIR__.'/../../../src/Flat/',
        ]);

    $services->load('Pushword\Conversation\Flat\\', __DIR__.'/../../../src/Flat/');

    // Register ConversationSync as the implementation of ConversationSyncInterface
    $services->alias(ConversationSyncInterface::class, ConversationSync::class);
    $services->alias('pushword.flat.conversation_sync', ConversationSync::class);
};
