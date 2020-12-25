<?php

use Pushword\Installer\PostInstall;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

PostInstall::remove([
    'config/packages/sonata_admin.yaml',
]);

echo '~~ Adding Puswhord Admin Routes'.chr(10);
PostInstall::addOnTop('config/routes.yaml', "admin:\n    resource: '@PushwordAdminBundle/AdminRoutes.yaml'\n");
