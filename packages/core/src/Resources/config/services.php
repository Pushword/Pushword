<?php

declare(strict_types=1);

use PiedWeb\RenderAttributes\TwigExtension;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\PushwordCoreBundle;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Service\VichUploadPropertyNamer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Twig\Extension\StringLoaderExtension;
use Twig\Extra\Intl\IntlExtension;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$projectDir', '%kernel.project_dir%')
        ->bind('$filterSets', '%pw.image_filter_sets%')
        ->bind('$publicMediaDir', '%pw.public_media_dir%')
        ->bind('$mediaDir', '%pw.media_dir%')
        ->bind('$rawApps', '%pw.apps%')
        ->bind('$publicDir', '%pw.public_dir%')
        ->bind('$pathToBin', '%pw.path_to_bin%')
        ->bind('$tailwindGeneratorisActive', '%pw.tailwind_generator%');

    $services->set(PushwordCoreBundle::class);

    $services->load('Pushword\Core\\', __DIR__.'/../../../src/*')
        ->exclude([
            __DIR__.'/../../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
        ]);

    $services->load('Pushword\Core\Controller\\', __DIR__.'/../../../src/Controller')
        ->tag('controller.service_arguments');

    // $services->set(PageRepository::class)->arg('$entityClass', '%pw.entity_page%');

    // $services->set(MediaRepository::class)->arg('$entityClass', '%pw.entity_media%');

    // $services->set(UserRepository::class)->arg('$entityClass', '%pw.entity_user%');

    $services->set(StringLoaderExtension::class);

    $services->set(TwigExtension::class);

    $services->set(IntlExtension::class);

    // # todo limit to test https://stackoverflow.com/questions/54466158/symfony-4-2-how-to-do-a-service-public-only-for-tests
    $services->set(PushwordRouteGenerator::class)
        ->public();

    $services->set(AppPool::class)
        ->public();

    // See who to avoid limit for this one too
    $services->set(VichUploadPropertyNamer::class)
        ->public();
};
