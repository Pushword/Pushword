<?php

use Pushword\Installer\PostInstall;
use Pushword\TemplateEditor\PushwordTemplateEditorBundle;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

PostInstall::registerBundle(PushwordTemplateEditorBundle::class);
PostInstall::importRoutes('template_editor', '@PushwordTemplateEditorBundle/TemplateEditorRoutes.yaml');
