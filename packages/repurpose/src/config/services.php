<?php

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Pushword\Api\Controller\ApiControllerInterface;
use Pushword\Core\PushwordCoreBundle;
use Pushword\Flat\Sync\FlatSyncInterface;
use Pushword\Repurpose\Controller\Api\RepurposeApiController;
use Pushword\Repurpose\Service\ConfigCreatorResolver;
use Pushword\Repurpose\Service\CreatorResolverInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$publicDir', '%pw.public_dir%')
        ->bind('$publicMediaDir', '%pw.public_media_dir%')
        ->bind('$fontDir', '%pw.pushword_repurpose.font_dir%')
        ->bind('$chromiumBinary', '%pw.pushword_repurpose.chromium_binary%')
        ->bind('$ffmpegBinary', '%pw.pushword_repurpose.ffmpeg_binary%');

    $apiAvailable = interface_exists(ApiControllerInterface::class);
    $apiExclude = $apiAvailable ? [] : [__DIR__.'/../Controller/Api'];

    // SocialPostSync implements pushword/flat's FlatSyncInterface.
    $flatExclude = interface_exists(FlatSyncInterface::class) ? [] : [__DIR__.'/../Sync'];

    $services->load('Pushword\Repurpose\\', __DIR__.'/../*')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
            __DIR__.'/../Model', // plain value objects, not services
            __DIR__.'/../Admin',  // loaded below only when EasyAdmin is present
            ...$apiExclude,
            ...$flatExclude,
        ]);

    // The default creator resolver reads `repurpose_creators` from the site
    // config; a downstream app can override this alias with its own resolver.
    $services->alias(CreatorResolverInterface::class, ConfigCreatorResolver::class);

    // Admin integration is optional: only wire it when EasyAdmin is installed.
    if (class_exists(AbstractCrudController::class)) {
        $services->load('Pushword\Repurpose\Admin\\', __DIR__.'/../Admin');
    }

    if ($apiAvailable) {
        $services->set(RepurposeApiController::class)
            ->autowire()
            ->tag('controller.service_arguments')
            ->tag('pushword.api.controller');
    }
};
