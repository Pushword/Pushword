<?php

use Pushword\Installer\PostInstall;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

echo '~~ Adding Routes'.chr(10);
PostInstall::insertIn('config/routes.yaml', "version:\n    resource: '@PushwordVersionBundle/VersionRoutes.yaml'\n");
