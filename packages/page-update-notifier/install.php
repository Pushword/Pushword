<?php

declare(strict_types=1);

use Pushword\Installer\PostInstall;
use Pushword\PageUpdateNotifier\PushwordPageUpdateNotifierBundle;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

PostInstall::registerBundle(PushwordPageUpdateNotifierBundle::class);
