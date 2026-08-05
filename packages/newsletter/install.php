<?php

use Pushword\Installer\PostInstall;
use Pushword\Newsletter\PushwordNewsletterBundle;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

PostInstall::registerBundle(PushwordNewsletterBundle::class);
PostInstall::importRoutes('newsletter', '@PushwordNewsletterBundle/NewsletterRoutes.yaml');
