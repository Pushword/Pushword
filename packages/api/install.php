<?php

use Pushword\Api\PushwordApiBundle;
use Pushword\Installer\PostInstall;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

PostInstall::registerBundle(PushwordApiBundle::class);
PostInstall::importRoutes('api', '@PushwordApiBundle/ApiRoutes.yaml');
