<?php

use Pushword\Conversation\PushwordConversationBundle;
use Pushword\Installer\PostInstall;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

PostInstall::registerBundle(PushwordConversationBundle::class);
PostInstall::importRoutes('conversation', '@PushwordConversationBundle/Resources/config/routes/conversation.yaml');
