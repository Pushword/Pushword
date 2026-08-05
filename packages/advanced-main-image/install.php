<?php

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
    PostInstall::replace('config/packages/twig.yaml', 'twig:', 'twig:
    paths:
        "%pw.package_dir%/advanced-main-image/src/templates": "Pushword"');
}
