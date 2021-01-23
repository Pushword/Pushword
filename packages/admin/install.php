<?php

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand
 */

if (! \Pushword\Installer\PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

\Pushword\Installer\PostInstall::remove([
    'config/packages/sonata_admin.yaml',
]);

echo '~~ Adding Puswhord Admin Routes'.chr(10);
\Pushword\Installer\PostInstall::addOnTop('config/routes.yaml', "admin:\n    resource: '@PushwordAdminBundle/AdminRoutes.yaml'\n");
