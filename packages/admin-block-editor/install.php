<?php

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand
 */

if (! \Pushword\Installer\PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

echo '~~ Adding Routes'.chr(10);
\Pushword\Installer\PostInstall::addOnTop('config/routes.yaml', "version:\n    resource: '@PushwordVersionBundle/VersionRoutes.yaml'\n");
