<?php

use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('twig', [
        'default_path' => '%kernel.project_dir%/templates',
        'debug' => '%kernel.debug%',
        'strict_variables' => '%kernel.debug%',
        'paths' => [
            '%kernel.project_dir%/templates' => 'App',
            '%pw.package_dir%/core/src/templates/TwigBundle' => 'Twig',
            '%pw.package_dir%/core/src/templates' => 'Pushword',
            '%kernel.project_dir%/public' => null,
        ],
        'globals' => [
            'apps' => ['type' => 'service', 'id' => SiteRegistry::class],
            'twig' => ['type' => 'service', 'id' => 'twig'],
            'unprose' => ['value' => 'not-prose lg:-mx-40 my-6 md:-mx-20'],
        ],
        'form_themes' => [
            'bootstrap_5_layout.html.twig',
        ],
    ]);
};
