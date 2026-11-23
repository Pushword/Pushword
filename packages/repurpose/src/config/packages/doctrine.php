<?php

declare(strict_types=1);

use Pushword\Repurpose\Doctrine\CompressedJsonType;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('doctrine', [
        'dbal' => ['types' => [CompressedJsonType::NAME => CompressedJsonType::class]],
        'orm' => [
            'mappings' => [
                'PushwordRepurposeBundle' => [
                    'type' => 'attribute',
                    'dir' => 'Entity',
                    'alias' => 'PushwordRepurpose',
                ],
            ],
        ],
    ]);
};
