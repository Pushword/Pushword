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
    "\npushword:\n    resource: '@PushwordCoreBundle/Resources/config/routes.yaml'\n",
    PostInstall::INSERT_AT_END
);

echo '~~ Create database'.chr(10);
// if it's a default symfony installation, switch from postgresql to sqlite
PostInstall::replace('.env', 'postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8', 'sqlite:///%kernel.project_dir%/var/app.db');
// and define an APP_SECRET
PostInstall::replace('.env', "APP_SECRET=\n", 'APP_SECRET='.sha1(md5(uniqid())).chr(10));
PostInstall::mirror('vendor/pushword/skeleton/media~', 'media');
$freshInstall = ! file_exists('var/app.db');
exec('php bin/console doctrine:schema:update --force -q');
if ($freshInstall) {
    exec('php bin/console doctrine:fixtures:load --no-interaction -q &');
}
exec('php bin/console pw:image:cache -q &');

// Add an admin user
// exec('php bin/console pw:user:create admin@example.tld p@ssword ROLE_SUPER_ADMIN');

echo '~~ Symlinking assets'.chr(10);
exec('php bin/console assets:install --symlink --relative -q');
PostInstall::dumpFile('public/build/manifest.json', '{}');

echo '~~ Copy assets file in ./assets'.chr(10);
PostInstall::remove(['package.json', 'webpack.config.js', 'assets']);
PostInstall::mirror('vendor/pushword/skeleton/assets', 'assets');
PostInstall::copy('vendor/pushword/skeleton/vite.config.js', 'vite.config.js');
PostInstall::copy('vendor/pushword/skeleton/package.json', 'package.json');
PostInstall::copy('vendor/pushword/skeleton/Caddyfile', 'Caddyfile');

$defaultConfig = 'pushword:'.chr(10)
    .'    # Documention'.chr(10)
    .'    # https://pushword.piedweb.com/configuration'.chr(10)
    .'    # Example'.chr(10)
    .'    # https://github.com/Pushword/Pushword/blob/main/packages/skeleton/config/packages/pushword.php'.chr(10);

PostInstall::dumpFile('config/packages/pushword.yaml', $defaultConfig);

// Install phpstan
// ---------------

PostInstall::copy('vendor/pushword/skeleton/phpstan.dist.neon', 'phpstan.dist.neon');
PostInstall::copy('vendor/pushword/skeleton/bin/console-test.php', 'bin/console-test.php');
PostInstall::copy('vendor/pushword/skeleton/bin/object-test.php', 'bin/object-test.php');
// À tester si appeler composer depuis composer ne fout pas le bordel
exec('composer config --no-plugins allow-plugins.phpstan/extension-installer true');
exec('composer config --no-plugins scripts.stan "vendor/bin/phpstan"');
exec('composer require --dev phpstan/extension-installer:* phpstan/phpstan:* phpstan/phpstan-doctrine:* phpstan/phpstan-phpunit:* phpstan/phpstan-strict-rules:* phpstan/phpstan-symfony:*');

// Install php-cs-fixer
// -------------------
PostInstall::copy('vendor/pushword/skeleton/.php-cs-fixer.dist.php~', '.php-cs-fixer.dist.php');
exec('composer config --no-plugins scripts.format "vendor/bin/php-cs-fixer fix"');
exec('composer require --no-plugins  --dev friendsofphp/php-cs-fixer:*');

// Install RECTOR
// -------------------
// Rector is a bit too expensive on a cheap VPS with 4Gb of RAM
/*
cp vendor/pushword/skeleton/rector.php rector.php && \
cp vendor/pushword/skeleton/tests/symfonyContainer.php tests/symfonyContainer.php && \
composer config --no-plugins scripts.rector "vendor/bin/rector process && composer format" && \
composer require --no-plugins --dev rector/rector:*
*/
// PostInstall::copy('vendor/pushword/skeleton/rector.php', 'rector.php');
// PostInstall::copy('vendor/pushword/skeleton/tests/symfonyContainer.php', 'tests/symfonyContainer.php');
// exec('composer config --no-plugins scripts.rector "vendor/bin/rector process && composer format"');
// exec('composer require --no-plugins  --dev rector/rector:*');

PostInstall::replace('.gitignore', '/var/', '/var/*');
PostInstall::insertIn('.gitignore', '###> pushword ###
public/assets
public/media
public/sw.js
static/
!/var/installer/
###< pushword ###
', PostInstall::INSERT_AT_END);
