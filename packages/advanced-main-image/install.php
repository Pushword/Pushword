<?php

declare(strict_types=1);

use Pushword\AdvancedMainImage\PushwordAdvancedMainImageBundle;
use Pushword\Installer\PostInstall;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

PostInstall::registerBundle(PushwordAdvancedMainImageBundle::class);

if (file_exists('config/packages/twig.yaml')) {
    $twigConfig = (string) file_get_contents('config/packages/twig.yaml');
    $twigConfig = preg_replace(
        '/^twig:[ \t]*$/m',
        "twig:\n    paths:\n        \"%pw.package_dir%/advanced-main-image/src/templates\": \"Pushword\"",
        $twigConfig,
        1,
        $replacements
    );
    if (1 !== $replacements || null === $twigConfig) {
        throw new RuntimeException('Could not configure Twig for Advanced Main Image.');
    }

    PostInstall::dumpFile('config/packages/twig.yaml', $twigConfig);
}
