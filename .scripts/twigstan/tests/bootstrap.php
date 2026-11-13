<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use PHPStan\ExtensionInstaller\GeneratedConfig;

$root = \dirname(__DIR__, 3);
$loader = require $root.'/vendor/autoload.php';
$loader->addPsr4('PHPStan\\ExtensionInstaller\\', __DIR__.'/bootstrap-fixture');
putenv('PUSHWORD_TWIGSTAN_PROJECT_DIR='.$root);
require $root.'/packages/twigstan/tools/bootstrap.php';

if (class_exists(GeneratedConfig::class)) {
    throw new RuntimeException('TwigStan loaded the project extension installer.');
}

// Also exercise initialization from the isolated tool's Composer metadata.
InstalledVersions::reload(require $root.'/packages/twigstan/tools/vendor/composer/installed.php');
if (! InstalledVersions::isInstalled('symfony/framework-bundle')) {
    throw new RuntimeException('The analysis bootstrap lost the project package metadata.');
}

echo "TwigStan bootstrap: extension isolation and project package metadata verified.\n";
