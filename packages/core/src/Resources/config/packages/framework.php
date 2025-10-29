<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'secret' => 'secretKeyToOverride (normally, it s automatically done by symfony standard edition',
        'session' => [
            'cookie_lifetime' => 0,
            'handler_id' => 'file://%kernel.project_dir%/var/sessions',
        ],
        'php_errors' => [
            'log' => true,
        ],
        'http_method_override' => false,
    ]);
};
