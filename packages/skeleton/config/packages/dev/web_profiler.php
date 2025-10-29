<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('web_profiler', [
        'toolbar' => true,
        'intercept_redirects' => false,
    ]);

    $containerConfigurator->extension('framework', [
        'profiler' => [
            'only_exceptions' => false,
        ],
    ]);

    $containerConfigurator->extension('debug', [
        'dump_destination' => 'tcp://%env(VAR_DUMPER_SERVER)%',
    ]);
};
