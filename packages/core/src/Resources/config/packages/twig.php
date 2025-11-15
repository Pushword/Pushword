<?php

declare(strict_types=1);

use Pushword\Core\Component\App\AppPool;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('twig', [
        'default_path' => '%kernel.project_dir%/templates',
        'debug' => '%kernel.debug%',
        'strict_variables' => '%kernel.debug%',
        'paths' => [
            '%kernel.project_dir%/templates' => 'App',
            '%pw.package_dir%/core/src/templates/TwigBundle' => 'Twig',
            '%pw.package_dir%/advanced-main-image/src/templates' => 'Pushword',
            '%pw.package_dir%/core/src/templates' => 'Pushword',
            '%kernel.project_dir%/public' => null,
        ],
        'globals' => [
            'apps' => service(AppPool::class),
            'twig' => service('twig'),
            'unprose' => 'not-prose lg:-mx-40 my-6 md:-mx-20',
        ],
        'form_themes' => [
            '@SonataForm/Form/datepicker.html.twig',
        ],
    ]);
};
