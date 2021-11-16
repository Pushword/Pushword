<?php

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! isset($postInstallRunning)) {
    return;
}
if (! \Pushword\Installer\PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

echo '~~ Adding Routes'.chr(10);
\Pushword\Installer\PostInstall::addOnTop('config/routes.yaml', "page_scanner:\n    resource: '@PushwordPageScannerBundle/PageScannerRoutes.yaml'\n");
