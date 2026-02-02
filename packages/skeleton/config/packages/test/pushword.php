<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $runId = $_ENV['TEST_RUN_ID'] ?? $_SERVER['TEST_RUN_ID'] ?? '';
    if ('' === $runId) {
        return;
    }

    $testBaseDir = sys_get_temp_dir().'/com.github.pushword.pushword/tests/'.$runId;

    $container->extension('pushword', [
        'media_dir' => $testBaseDir.'/media',
        'database_url' => 'sqlite:///'.$testBaseDir.'/test.db',
    ]);

    $container->extension('pushword_flat', [
        'flat_content_dir' => $testBaseDir.'/content/_host_',
    ]);
};
