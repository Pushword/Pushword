<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    // Use env vars so the compiled container is cacheable across workers.
    // Each worker sets these env vars in bootstrap.php to point to its own directories.
    $container->extension('pushword', [
        'media_dir' => '%env(PUSHWORD_TEST_MEDIA_DIR)%',
        'database_url' => '%env(PUSHWORD_TEST_DATABASE_URL)%',
    ]);

    $container->extension('pushword_flat', [
        'flat_content_dir' => '%env(PUSHWORD_TEST_FLAT_CONTENT_DIR)%',
    ]);
};
