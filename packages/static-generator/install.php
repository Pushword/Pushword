<?php

use Pushword\Installer\PostInstall;
use Pushword\StaticGenerator\PushwordStaticGeneratorBundle;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

PostInstall::registerBundle(PushwordStaticGeneratorBundle::class);
PostInstall::importRoutes('static', '@PushwordStaticGeneratorBundle/StaticRoutes.yaml');
