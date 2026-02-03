<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    // Override per-app flat_content_dir for pushword.piedweb.com so tests
    // write to the tmp directory instead of the real packages/docs/content.
    $container->parameters()->set('pw.piedweb.flat_content_dir', '%env(PUSHWORD_TEST_FLAT_CONTENT_DIR)%');
};
