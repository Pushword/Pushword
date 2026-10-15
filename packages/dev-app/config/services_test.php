<?php

declare(strict_types=1);

use Pushword\Core\Tests\Media\Policy\MediaPolicyFixture;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    // Override per-app flat_content_dir for pushword.piedweb.com so tests
    // write to the tmp directory instead of the real packages/docs/content.
    $container->parameters()->set('pw.piedweb.flat_content_dir', '%env(PUSHWORD_TEST_FLAT_CONTENT_DIR)%');

    // The removal half of an app's media rules; the validation half is mapped in
    // packages/test/framework.yaml.
    $container->services()->set(MediaPolicyFixture::class)->autoconfigure();
};
