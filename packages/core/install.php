<?php

use Pushword\Core\PushwordCoreBundle;
use Pushword\Installer\PostInstall;
use Pushword\Installer\SystemCheck;

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
    // doctrine/doctrine-bundle's recipe writes a PostgreSQL service, and symfony/mailer
    // adds a Mailpit one to the override — a stack with no application service, for a
    // database Pushword does not use. `pw:docker:init` writes the real thing.
    'compose.yaml',
    'compose.override.yaml',
]);

// Set pushword bundle first to avoid errors. Every other bundle appends itself from its
// own install.php, so core cannot use PostInstall::registerBundle() here: it needs the
// head of the list, not the tail.
PostInstall::replace('config/bundles.php', PushwordCoreBundle::class."::class => ['all' => true],", '');
PostInstall::replace('config/bundles.php', 'return [', 'return [
    '.PushwordCoreBundle::class."::class => ['all' => true],");

// echo '~~ Copy Entities in ./src/Entity'.chr(10);
// PostInstall::mirror('vendor/pushword/dev-app/src/Entity', 'src/Entity');
// The starter content, not dev-app's fixtures: those are the test suite's rig, they
// reference pages and bundles a fresh install does not have.
@unlink('src/DataFixtures/AppFixtures.php');
PostInstall::mirror('vendor/pushword/core/starter', 'src/DataFixtures');

// At the end: the catch-all page route must come after every other bundle's.
echo '~~ Adding Puswhord Routes'.chr(10);
PostInstall::insertIn(
    'config/routes.yaml',
    "\npushword:\n    resource: '@PushwordCoreBundle/Resources/config/routes.yaml'\n",
    PostInstall::INSERT_AT_END
);

echo '~~ Create database'.chr(10);
$requestedDatabaseUrl = trim((string) getenv('PUSHWORD_DATABASE_URL'));
$databaseUrl = '' !== $requestedDatabaseUrl ? $requestedDatabaseUrl : 'sqlite:///%kernel.project_dir%/var/app.db';
// Symfony's Doctrine recipe starts with this placeholder. Pushword remains SQLite by
// default; provisioning can opt into PostgreSQL before create-project starts.
PostInstall::replace('.env', 'postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8', $databaseUrl);
// and define an APP_SECRET
PostInstall::replace('.env', "APP_SECRET=\n", 'APP_SECRET='.bin2hex(random_bytes(32)).chr(10));
// dev-app's media doubles as the test suite's fixtures: take the demo photos, leave the
// branding and the PDF test artifact behind.
PostInstall::mirror('vendor/pushword/dev-app/media~', 'media');
PostInstall::remove(['media/piedweb-logo.png', 'media/logo.svg', 'media/test.pdf']);
$serverDatabase = ! str_starts_with($databaseUrl, 'sqlite:');
$freshInstall = ! $serverDatabase && ! file_exists('var/app.db');
$runCommand = static function (string $command): void {
    exec($command, $output, $status);
    if (0 !== $status) {
        throw new RuntimeException('Installer command failed: '.$command);
    }
};

$runCommand('php bin/console doctrine:schema:update --force -q');
if ($serverDatabase) {
    exec('php bin/console pw:user:exists -q', $output, $status);
    $freshInstall = 0 !== $status;
}

if ($freshInstall) {
    $runCommand('php bin/console doctrine:fixtures:load --no-interaction -q');
}

exec('php bin/console pw:image:cache -q');

echo '~~ Symlinking assets'.chr(10);
exec('php bin/console assets:install --symlink --relative -q');
PostInstall::dumpFile('public/build/manifest.json', '{}');

if ($freshInstall) {
    // Asking beats seeding a password that ships in the documentation — but only when
    // someone is there to answer. Unattended development installs keep the documented
    // demo login; unattended production installs receive a one-time random secret.
    $userCreated = false;
    if (PostInstall::isInteractive()) {
        echo '~~ Create the account you will log in with:'.chr(10);
        // No arguments: the command asks for each one, and already defaults the role
        // to ROLE_SUPER_ADMIN.
        passthru('php bin/console pw:user:create', $status);
        $userCreated = 0 === $status;
    }

    if (! $userCreated) {
        $production = 'prod' === (getenv('APP_ENV') ?: 'dev');
        $password = $production ? bin2hex(random_bytes(16)) : 'p@ssword';
        $requireChange = $production ? ' --require-password-change' : '';
        $runCommand('php bin/console pw:user:create admin@example.tld '.escapeshellarg($password).' ROLE_SUPER_ADMIN'.$requireChange.' -q');
        echo '~~ Super admin created: admin@example.tld / '.$password.chr(10);
        echo $production
            ? '~~ This one-time temporary password must be changed at first login and will not be displayed again.'.chr(10)
            : '~~ Development credential only. Do not use it in production.'.chr(10);
    }
}

echo '~~ Copy assets file in ./assets'.chr(10);
PostInstall::remove(['package.json', 'webpack.config.js', 'assets']);
PostInstall::mirror('vendor/pushword/dev-app/assets', 'assets');
PostInstall::copy('vendor/pushword/dev-app/vite.config.js', 'vite.config.js');
PostInstall::copy('vendor/pushword/dev-app/package.json', 'package.json');
PostInstall::copy('vendor/pushword/dev-app/Caddyfile', 'Caddyfile');

$defaultConfig = 'pushword:'.chr(10)
    .'    # Documention'.chr(10)
    .'    # https://pushword.piedweb.com/installation'.chr(10)
    .'    # Example'.chr(10)
    .'    # https://github.com/Pushword/Pushword/blob/main/packages/dev-app/config/packages/pushword.php'.chr(10);

PostInstall::dumpFile('config/packages/pushword.yaml', $defaultConfig);

// pushword/new ships AGENTS.md; Claude Code reads CLAUDE.md. Symfony Flex may have
// already created its equivalent two-line pointer, which is safe to replace. Preserve
// any other site-owned CLAUDE.md.
$claudePointer = "# CLAUDE.md\n\n@AGENTS.md";
$replaceableClaudeFile = ! file_exists('CLAUDE.md')
    || (is_file('CLAUDE.md') && $claudePointer === trim((string) file_get_contents('CLAUDE.md')));
if (file_exists('AGENTS.md') && $replaceableClaudeFile) {
    @unlink('CLAUDE.md');
    symlink('AGENTS.md', 'CLAUDE.md');
}

// Install phpstan
// ---------------

PostInstall::copy('vendor/pushword/dev-app/phpstan.dist.neon', 'phpstan.dist.neon');
PostInstall::copy('vendor/pushword/dev-app/bin/console-test.php', 'bin/console-test.php');
PostInstall::replace('bin/console-test.php', "'/../../../vendor/autoload.php'", "'/../vendor/autoload.php'");
PostInstall::copy('vendor/pushword/dev-app/bin/object-test.php', 'bin/object-test.php');
PostInstall::replace('bin/object-test.php', "'/../../../vendor/autoload.php'", "'/../vendor/autoload.php'");
// À tester si appeler composer depuis composer ne fout pas le bordel
exec('composer config --no-plugins allow-plugins.phpstan/extension-installer true');
exec('composer config --no-plugins scripts.stan "vendor/bin/phpstan"');
if (! file_exists('vendor/phpstan/phpstan/phpstan.phar')) {
    exec('composer require --dev phpstan/extension-installer:* phpstan/phpstan:* phpstan/phpstan-doctrine:* phpstan/phpstan-phpunit:* phpstan/phpstan-strict-rules:* phpstan/phpstan-symfony:*');
}

// Install php-cs-fixer
// -------------------
PostInstall::copy('vendor/pushword/dev-app/php-cs-fixer.dist.php.template', '.php-cs-fixer.dist.php');
exec('composer config --no-plugins scripts.format "vendor/bin/php-cs-fixer fix"');
if (! file_exists('vendor/friendsofphp/php-cs-fixer/php-cs-fixer')) {
    exec('composer require --no-plugins  --dev friendsofphp/php-cs-fixer:*');
}

// Install RECTOR
// -------------------
// Rector is a bit too expensive on a cheap VPS with 4Gb of RAM
/*
cp vendor/pushword/dev-app/rector.php rector.php && \
cp vendor/pushword/dev-app/tests/symfonyContainer.php tests/symfonyContainer.php && \
composer config --no-plugins scripts.rector "vendor/bin/rector process && composer format" && \
composer require --no-plugins --dev rector/rector:*
*/
// PostInstall::copy('vendor/pushword/dev-app/rector.php', 'rector.php');
// PostInstall::copy('vendor/pushword/dev-app/tests/symfonyContainer.php', 'tests/symfonyContainer.php');
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

// Docker
// ------
// Offered last, so the answer is the last thing on screen, and offered only when it is
// a real choice: no Docker daemon, no question and no Dockerfile left lying around.
// The recommendation follows the machine — a PHP that already has what Pushword needs
// is better off without a layer in between; one that does not is better off with the
// image, which ships them.
$systemCheck = SystemCheck::probe($databaseUrl);

if (! $systemCheck->shouldAsk()) {
    if ($systemCheck->recommendsDocker()) {
        echo '⚠ Pushword needs '.implode(', ', $systemCheck->missing).', which this PHP does not have.'.chr(10);
        echo '⚠ Install them (see https://pushword.piedweb.com/installation), or install'.chr(10);
        echo '⚠ Docker and run `php bin/console pw:docker:init`.'.chr(10);
    }
} elseif (PostInstall::isInteractive()) {
    $dockerRecommended = $systemCheck->recommendsDocker();

    echo chr(10).'~~ Docker is available here. '.ucfirst($systemCheck->reason()).'.'.chr(10);

    if (PostInstall::confirm(
        '~~ Run Pushword in Docker? '.($dockerRecommended ? '[Yes (recommended) / no]' : '[yes / No (recommended)]').': ',
        $dockerRecommended
    )) {
        passthru('php bin/console pw:docker:init');
    } else {
        echo '~~ No Docker file written. `php bin/console pw:docker:init` adds them later.'.chr(10);
    }
}
