<?php

use Pushword\Installer\PostInstall;
use Pushword\PageScanner\PushwordPageScannerBundle;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

PostInstall::registerBundle(PushwordPageScannerBundle::class);
PostInstall::importRoutes('page_scanner', '@PushwordPageScannerBundle/PageScannerRoutes.yaml');
