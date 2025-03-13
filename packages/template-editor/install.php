<?php

use Pushword\Installer\PostInstall;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

echo '~~ Adding Routes'.chr(10);
PostInstall::addOnTop('config/routes.yaml', "template_editor:\n    resource: '@PushwordTemplateEditorBundle/TemplateEditorRoutes.yaml'\n");
PostInstall::addOnTop('config/framework.yaml', "template_editor:\n    resource: '@PushwordTemplateEditorBundle/TemplateEditorRoutes.yaml'\n");
