<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('doctrine', [
        'dbal' => [
            'driver' => 'pdo_mysql',
            'charset' => 'utf8mb4',
            'default_table_options' => [
                'charset' => 'utf8mb4',
                'collate' => 'utf8mb4_unicode_ci',
            ],
            'url' => '%pw.database_url%',
        ],
        'orm' => [
            'report_fields_where_declared' => true,
            'validate_xml_mapping' => true,
            'auto_generate_proxy_classes' => '%kernel.debug%',
            'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
            'auto_mapping' => true,
            'enable_lazy_ghost_objects' => true,
            'mappings' => [
                'PushwordCoreBundle' => [
                    'type' => 'attribute',
                    'dir' => 'Entity',
                    'alias' => 'PushwordCore',
                ],
            ],
        ],
    ]);
};
