<?php

use Pushword\Newsletter\Repository\DQL\JsonScalarFunction;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('doctrine', [
        'orm' => [
            'mappings' => [
                'PushwordNewsletterBundle' => [
                    'type' => 'attribute',
                    'dir' => 'Entity',
                    'alias' => 'PushwordNewsletter',
                ],
            ],
            'dql' => [
                'string_functions' => [
                    'JSON_SCALAR' => JsonScalarFunction::class,
                ],
            ],
        ],
    ]);
};
