<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Pushword\PageUpdateNotifier\PageUpdateNotifier;

return function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services->set('pushword.page_update_notifier', PageUpdateNotifier::class)
        ->autowire()
        ->args([
            '$pageClass' => '%pw.entity_page%',
            '$varDir' => '%kernel.root_dir%/../var',
        ])
        ->tag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_page%', 'event' => 'postUpdate'])
        ->tag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_page%', 'event' => 'postPersist'])
        ;
};
