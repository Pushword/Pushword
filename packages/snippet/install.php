<?php

use Pushword\Installer\PostInstall;
use Pushword\Snippet\PushwordSnippetBundle;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

PostInstall::registerBundle(PushwordSnippetBundle::class);
