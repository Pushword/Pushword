<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('babdev_pagerfanta', [
        'default_view' => 'default',
        'default_twig_template' => '@PushwordCoreBundle/component/pager.html.twig',
    ]);
};
