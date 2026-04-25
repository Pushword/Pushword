<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('locale', 'en');

    $parameters->set('pw.piedweb.flat_content_dir', '%kernel.project_dir%/../docs/content');

    $parameters->set('database', '%env(resolve:DATABASE_URL)%');

    $parameters->set('secret', 'myS3cretKey');

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\', __DIR__.'/../src/*')
        ->exclude([
            __DIR__.'/../src/{DependencyInjection,Entity,Migrations,Tests,Kernel.php}',
        ]);

    $services->load('App\Controller\\', __DIR__.'/../src/Controller')
        ->tag('controller.service_arguments');
};
