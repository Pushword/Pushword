<?php

use Pushword\Admin\PushwordAdminBundle;
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

PostInstall::registerBundle(PushwordAdminBundle::class);
PostInstall::importRoutes('admin', '@PushwordAdminBundle/AdminRoutes.yaml');
