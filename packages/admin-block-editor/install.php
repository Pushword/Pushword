<?php

use Pushword\Installer\PostInstall;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! isset($postInstallRunning)) {
    return;
}if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

echo '~~ Adding Routes'.chr(10);
PostInstall::addOnTop('config/routes.yaml', "admin_block_editor:\n    resource: '@PushwordAdminBlockEditorBundle/AdminBlockEditorRoutes.yaml'\n");
