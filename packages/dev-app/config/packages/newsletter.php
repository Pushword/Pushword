<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('newsletter', [
        'email_templates' => [
            'pushword' => ['label' => 'Pushword Classic', 'template' => 'newsletter/pushword.html.twig'],
            'pushword_letter' => ['label' => 'Pushword Letter', 'template' => 'newsletter/pushword_letter.html.twig'],
            'pushword_bulletin' => ['label' => 'Pushword Bulletin', 'template' => 'newsletter/pushword_bulletin.html.twig'],
        ],
    ]);
};
