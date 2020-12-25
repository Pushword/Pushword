<?php

use Pushword\Installer\PostInstall;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

echo '~~ Adding Routes'.chr(10);
PostInstall::addOnTop('config/routes.yaml', "conversation:\n    resource: '@PushwordConversationBundle/Resources/config/routes/conversation.yaml'\n");

exec('php bin/console doctrine:schema:update --force');
