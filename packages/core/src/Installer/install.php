<?php

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! file_exists('vendor')) {
    throw new Exception('installer mus be run from root');
}

\Pushword\Installer\PostInstall::remove([
    'templates/base.html.twig',
    'config/packages/security.yaml',
    'config/packages/doctrine.yaml',
    'config/packages/vich_uploader.yaml',
]);

// Set pushword bundle first to avoid errors
\Pushword\Installer\PostInstall::replace('config/bundles.php', "Pushword\Core\PushwordCoreBundle::class => ['all' => true],", '');
\Pushword\Installer\PostInstall::replace('config/bundles.php', 'return [', "return [\n    Pushword\Core\PushwordCoreBundle::class => ['all' => true],");

echo '~~ Copy Entities in ./src/Entity'.chr(10);
\Pushword\Installer\PostInstall::mirror('vendor/pushword/skeleton/src/Entity', 'src/Entity');
@unlink('src/DataFixtures/AppFixtures.php');
\Pushword\Installer\PostInstall::mirror('vendor/pushword/skeleton/src/DataFixtures', 'src/DataFixtures');

echo '~~ Adding Puswhord Routes'.chr(10);
\Pushword\Installer\PostInstall::addOnTop('config/routes.yaml', "pushword:\n    resource: '@PushwordCoreBundle/Resources/config/routes/all.yaml'\n");

echo '~~ Create database'.chr(10);
\Pushword\Installer\PostInstall::mirror('vendor/pushword/skeleton/media~', 'media');
exec('php bin/console doctrine:schema:create -q');
exec('php bin/console doctrine:fixtures:load -q &');
exec('php bin/console pushword:image:cache -q &');

// Add an admin user
//exec('php bin/console pushword:user:create admin@example.tld p@ssword ROLE_SUPER_ADMIN');

echo '~~ Symlinking assets'.chr(10);
exec('php bin/console assets:install --symlink --relative -q');
\Pushword\Installer\PostInstall::dumpFile('public/build/manifest.json', '{}');

echo '~~ Copy assets file in ./assets'.chr(10);
\Pushword\Installer\PostInstall::remove(['package.json', 'webpack.config.js', 'assets']);
\Pushword\Installer\PostInstall::mirror('vendor/pushword/skeleton/assets', 'assets');

$defaultConfig = 'pushword:'.chr(10)
    .'    # Documention'.chr(10)
    .'    # https://pushword.piedweb.com/configuration'.chr(10)
    .'    # Example'.chr(10)
    .'    # https://github.com/Pushword/Pushword/blob/main/packages/skeleton/config/packages/pushword.yaml'.chr(10);

\Pushword\Installer\PostInstall::dumpFile('config/packages/pushword.yaml', $defaultConfig);
