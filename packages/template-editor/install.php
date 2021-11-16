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
\Pushword\Installer\PostInstall::addOnTop('config/routes.yaml', "template_editor:\n    resource: '@PushwordTemplateEditorBundle/TemplateEditorRoutes.yaml'\n");
