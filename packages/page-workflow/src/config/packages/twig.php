<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('twig', [
        'paths' => [
            __DIR__.'/../../templates' => 'PushwordPageWorkflow',
        ],
    ]);
};
