<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('pushword_page_scanner', [
        'links_to_ignore' => [
            'https://example.tld/*',
        ],
    ]);
};
