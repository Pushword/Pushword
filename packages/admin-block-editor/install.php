<?php

use Pushword\AdminBlockEditor\PushwordAdminBlockEditorBundle;
use Pushword\Installer\PostInstall;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

PostInstall::registerBundle(PushwordAdminBlockEditorBundle::class);
PostInstall::importRoutes('admin_block_editor', '@PushwordAdminBlockEditorBundle/AdminBlockEditorRoutes.yaml');
