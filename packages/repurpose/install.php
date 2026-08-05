<?php

use Pushword\Installer\PostInstall;
use Pushword\Repurpose\PushwordRepurposeBundle;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

PostInstall::registerBundle(PushwordRepurposeBundle::class);
PostInstall::importRoutes('repurpose', '@PushwordRepurposeBundle/Resources/config/routes/repurpose.yaml');
