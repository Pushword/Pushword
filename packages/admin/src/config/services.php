<?php

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use Pushword\Admin\Controller\AdminMenu;
use Pushword\Admin\Service\PageEditLockManager;
use Pushword\Core\PushwordCoreBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Pushword\Admin\\', __DIR__.'/../../src/')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
        ]);

    // Explicitly include AdminMenu and AdminMenuInterface (they're in Controller/ but not in Controller/Admin/)
    $services->set(AdminMenu::class)
        ->autowire()
        ->autoconfigure();

    // Locks live in the configured runtime state dir, not a hardcoded var/: tests
    // point pushword.var_dir at a per-worker dir, so lock files (keyed by page id)
    // don't collide with each other or with a running dev app.
    $services->set(PageEditLockManager::class)
        ->arg('$varDir', '%pw.var_dir%');

    $services->alias(AdminContextProviderInterface::class, AdminContextProvider::class);
};
