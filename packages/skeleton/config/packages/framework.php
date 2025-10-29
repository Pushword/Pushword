<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'ide' => 'vscode',
        'mailer' => [
            'dsn' => 'null://null',
        ],
        'http_method_override' => false,
    ]);
};
