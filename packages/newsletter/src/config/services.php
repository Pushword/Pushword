<?php

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Pushword\Api\Controller\ApiControllerInterface;
use Pushword\Core\PushwordCoreBundle;
use Pushword\Newsletter\Controller\Api\AutomationApiController;
use Pushword\Newsletter\Controller\Api\CampaignApiController;
use Pushword\Newsletter\Controller\Api\ContactApiController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $apiAvailable = interface_exists(ApiControllerInterface::class);
    $apiExclude = $apiAvailable ? [] : [__DIR__.'/../Controller/Api'];

    $services->load('Pushword\Newsletter\\', __DIR__.'/../*')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
            __DIR__.'/../Admin',
            __DIR__.'/../Enum',
            __DIR__.'/../Repository/DQL',
            __DIR__.'/../Segment/SegmentCriteria.php',
            __DIR__.'/../Segment/SegmentException.php',
            __DIR__.'/../Utm/UtmTag.php',
            ...$apiExclude,
        ]);

    // Admin integration is optional: only wire it when EasyAdmin is installed.
    if (class_exists(AbstractCrudController::class)) {
        $services->load('Pushword\Newsletter\Admin\\', __DIR__.'/../Admin');
    }

    if ($apiAvailable) {
        $services->set(ContactApiController::class)
            ->autowire()
            ->tag('controller.service_arguments')
            ->tag('pushword.api.controller');
        $services->set(CampaignApiController::class)
            ->autowire()
            ->tag('controller.service_arguments')
            ->tag('pushword.api.controller');
        $services->set(AutomationApiController::class)
            ->autowire()
            ->tag('controller.service_arguments')
            ->tag('pushword.api.controller');
    }
};
