<?php

use Pushword\Installer\PostInstall;
use Pushword\Search\PushwordSearchBundle;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

PostInstall::registerBundle(PushwordSearchBundle::class);
PostInstall::importRoutes('search', '@PushwordSearchBundle/SearchRoutes.yaml');
