<?php

use Pushword\Installer\PostInstall;
use Pushword\Quiz\PushwordQuizBundle;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

PostInstall::registerBundle(PushwordQuizBundle::class);
PostInstall::importRoutes('quiz', '@PushwordQuizBundle/Resources/config/routes/quiz.yaml');
