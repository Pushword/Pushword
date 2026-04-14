<?php

use Pushword\Core\Service\BackgroundProcessManager;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    // Use env vars so the compiled container is cacheable across workers.
    // Each worker sets these env vars in bootstrap.php to point to its own directories.
    $container->extension('pushword', [
        'media_dir' => '%env(PUSHWORD_TEST_MEDIA_DIR)%',
        'database_url' => '%env(PUSHWORD_TEST_DATABASE_URL)%',
        'enable_password_reset' => true,
    ]);

    $container->extension('pushword_flat', [
        'flat_content_dir' => '%env(PUSHWORD_TEST_FLAT_CONTENT_DIR)%',
    ]);

    // Per-worker var dir for BackgroundProcessManager pid files.
    // Without this, parallel workers running pw:static share the same pid file
    // and one worker sees another's pid → exits early, leaving the test files
    // ungenerated.
    $container->services()
        ->set(BackgroundProcessManager::class)
        ->args([
            service('filesystem'),
            '%env(PUSHWORD_TEST_VAR_DIR)%',
            '%kernel.project_dir%',
        ])
        ->autowire()
        ->autoconfigure();
};
