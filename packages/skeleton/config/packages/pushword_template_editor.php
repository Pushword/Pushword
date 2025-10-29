<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('pushword_template_editor', [
        'disable_creation' => true,
        'can_be_edited_list' => [
            '/pushword.piedweb.com/page/_footer.html.twig',
        ],
    ]);
};
