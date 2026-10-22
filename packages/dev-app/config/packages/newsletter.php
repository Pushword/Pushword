<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('newsletter', [
        'email_templates' => [
            'pushword' => ['label' => 'Pushword (Twig)', 'template' => 'newsletter/pushword.html.twig'],
        ],
    ]);
};
