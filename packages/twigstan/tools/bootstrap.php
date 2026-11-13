<?php

declare(strict_types=1);

$projectLoader = require (getenv('PUSHWORD_TWIGSTAN_PROJECT_DIR') ?: getcwd()).'/vendor/autoload.php';
// Load only the PHPStan extensions selected by the template-analysis configuration.
// Keep Composer's registered-loader metadata for InstalledVersions component checks.
spl_autoload_unregister([$projectLoader, 'loadClass']);
spl_autoload_register(static function (string $class) use ($projectLoader): void {
    if (! str_starts_with($class, 'PHPStan\\ExtensionInstaller\\')) {
        $projectLoader->loadClass($class);
    }
});
$toolLoader = require __DIR__.'/vendor/autoload.php';
$toolLoader->unregister();
$toolLoader->register();
