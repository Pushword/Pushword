<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('newsletter', [
        'sender_profiles' => [
            'editorial' => ['label' => 'Pushword editorial team', 'from_email' => 'newsletter@localhost.dev', 'from_name' => 'Pushword editorial team', 'reply_to' => 'editor@localhost.dev', 'signature_markdown' => '**The Pushword team**'],
        ],
        'email_templates' => [
            'pushword' => ['label' => 'Pushword Classic', 'template' => 'newsletter/pushword.html.twig'],
            'pushword_letter' => ['label' => 'Pushword Letter', 'template' => 'newsletter/pushword_letter.html.twig'],
            'pushword_bulletin' => ['label' => 'Pushword Bulletin', 'template' => 'newsletter/pushword_bulletin.html.twig'],
        ],
    ]);
};
