<?php

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Pushword\AdminBlockEditor\Editor\EditorJsToolProviderInterface;
use Pushword\Core\PushwordCoreBundle;
use Pushword\Snippet\Registry\SnippetRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    // The EditorJS integration requires the optional block editor bundle.
    $editorExclude = interface_exists(EditorJsToolProviderInterface::class) ? [] : [__DIR__.'/../Editor'];

    $services->load('Pushword\Snippet\\', __DIR__.'/../*')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
            __DIR__.'/../Attribute',
            __DIR__.'/../Admin',
            ...$editorExclude,
        ]);

    $services->set(SnippetRegistry::class)
        ->args([tagged_iterator('pushword.snippet')]);

    // Admin integration is optional: only wire it when EasyAdmin is installed.
    if (class_exists(AbstractCrudController::class)) {
        $services->load('Pushword\Snippet\Admin\\', __DIR__.'/../Admin');
    }
};
