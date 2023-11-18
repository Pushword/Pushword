<?php

declare(strict_types=1);

use Pushword\Core\PushwordCoreBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$env', '%kernel.environment%')
        ->bind('$message', '%pw.conversation.entity_message%')
        ->bind('$projectDir', '%kernel.project_dir%')
        ->bind('$messageEntity', '%pw.conversation.entity_message%')
        ->bind('$entityClass', '%pw.conversation.entity_message%');

    $services->load('Pushword\Conversation\\', __DIR__.'/../../../src/*')
        ->exclude([
            __DIR__.'/../../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
        ]);
};
