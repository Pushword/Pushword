<?php

declare(strict_types=1);
use Pushword\Core\PushwordCoreBundle;
use Pushword\Flat\Converter\FlatPropertyConverterInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    // Tag FlatPropertyConverterInterface implementations
    $services->instanceof(FlatPropertyConverterInterface::class)
        ->tag('pushword.flat.property_converter');

    $services->load('Pushword\AdvancedMainImage\\', __DIR__.'/../*')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
        ]);
};
