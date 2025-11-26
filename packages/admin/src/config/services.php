<?php

declare(strict_types=1);

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use Pushword\Admin\Controller\AdminMenu;
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

    $services->alias(AdminContextProviderInterface::class, AdminContextProvider::class);
};
