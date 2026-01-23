<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Pushword\Core\Entity\Page;
use Pushword\PageUpdateNotifier\PageUpdateNotifier;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();
    $services->set('pushword.page_update_notifier', PageUpdateNotifier::class)
        ->autowire()
        ->args([
            '$varDir' => '%kernel.project_dir%/var',
        ])
        ->tag('doctrine.orm.entity_listener', ['entity' => Page::class, 'event' => 'postUpdate'])
        ->tag('doctrine.orm.entity_listener', ['entity' => Page::class, 'event' => 'postPersist'])
    ;
};
