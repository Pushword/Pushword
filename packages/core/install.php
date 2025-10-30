<?php

use Pushword\Core\PushwordCoreBundle;
use Pushword\Installer\PostInstall;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

PostInstall::remove([
    'templates/base.html.twig',
    'config/packages/security.yaml',
    'config/packages/doctrine.yaml',
    'config/packages/vich_uploader.yaml',
]);

// Set pushword bundle first to avoid errors
PostInstall::replace('config/bundles.php', PushwordCoreBundle::class."::class => ['all' => true],", '');
PostInstall::replace('config/bundles.php', 'return [', 'return [
    '.PushwordCoreBundle::class."::class => ['all' => true],");

// echo '~~ Copy Entities in ./src/Entity'.chr(10);
// PostInstall::mirror('vendor/pushword/skeleton/src/Entity', 'src/Entity');
@unlink('src/DataFixtures/AppFixtures.php');
PostInstall::mirror('vendor/pushword/skeleton/src/DataFixtures', 'src/DataFixtures');

echo '~~ Adding Puswhord Routes'.chr(10);
PostInstall::insertIn(
    'config/routes.yaml',
    "\npushword:\n    resource: '@PushwordCoreBundle/Resources/config/routes/all.php'\n",
    PostInstall::INSERT_AT_END
);

echo '~~ Create database'.chr(10);
// if it's a default symfony installation, switch from postgresql to sqlite
PostInstall::replace('.env', 'postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8', 'sqlite:///%kernel.project_dir%/var/app.db');
// and define an APP_SECRET
PostInstall::replace('.env', "APP_SECRET=\n", 'APP_SECRET='.sha1(md5(uniqid())).chr(10));
PostInstall::mirror('vendor/pushword/skeleton/media~', 'media');
exec('php bin/console doctrine:schema:create -q');
exec('php bin/console doctrine:fixtures:load -q &');
exec('php bin/console pushword:image:cache -q &');

// Add an admin user
// exec('php bin/console pushword:user:create admin@example.tld p@ssword ROLE_SUPER_ADMIN');

echo '~~ Symlinking assets'.chr(10);
exec('php bin/console assets:install --symlink --relative -q');
PostInstall::dumpFile('public/build/manifest.json', '{}');

echo '~~ Copy assets file in ./assets'.chr(10);
PostInstall::remove(['package.json', 'webpack.config.js', 'assets']);
PostInstall::mirror('vendor/pushword/skeleton/assets', 'assets');
PostInstall::mirror('vendor/pushword/skeleton/vite.config.js', 'vite.config.js');
PostInstall::mirror('vendor/pushword/skeleton/package.json', 'package.json');

$defaultConfig = 'pushword:'.chr(10)
    .'    # Documention'.chr(10)
    .'    # https://pushword.piedweb.com/configuration'.chr(10)
    .'    # Example'.chr(10)
    .'    # https://github.com/Pushword/Pushword/blob/main/packages/skeleton/config/packages/pushword.php'.chr(10);

PostInstall::dumpFile('config/packages/pushword.yaml', $defaultConfig);
