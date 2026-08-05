<?php

use Pushword\Flat\PushwordFlatBundle;
use Pushword\Installer\PostInstall;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

PostInstall::registerBundle(PushwordFlatBundle::class);
PostInstall::importRoutes('flat', '@PushwordFlatBundle/FlatRoutes.yaml');
