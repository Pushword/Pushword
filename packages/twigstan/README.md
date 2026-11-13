# Pushword TwigStan

Development-only template analysis for Pushword sites. The package resolves literal
Twig `view()` and PHP `getView()` calls using the selected site's template resolver.

## Install

Requires PHP 8.5 and Pushword core 1.0.108 or later.

```shell
composer require --dev pushword/twigstan:^1.0.108
```

Create `.scripts/TwigStanKernel.php` outside the application service scan:

```php
<?php

declare(strict_types=1);

namespace App\TwigStan;

use App\Kernel;
use Pushword\TwigStan\AnalysisKernelTrait;

final class TwigStanKernel extends Kernel
{
    use AnalysisKernelTrait;
}
```

Create `twigstan-loader.php`:

```php
<?php

declare(strict_types=1);

use App\TwigStan\TwigStanKernel;
use Pushword\TwigStan\Environment;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__.'/.scripts/TwigStanKernel.php';
new Dotenv()->bootEnv(__DIR__.'/.env');

return Environment::load(new TwigStanKernel('dev', true));
```

Create `twigstan.php`:

```php
<?php

declare(strict_types=1);

use Pushword\TwigStan\Config;

return Config::configure(__DIR__, __DIR__.'/twigstan-loader.php')
    ->phpPaths(__DIR__.'/src', __DIR__.'/vendor/pushword/core/src')
    ->twigPaths(__DIR__.'/templates')
    ->create();
```

Add the source directories of other installed bundles that render templates you
want to check. The returned builder is TwigStan's native `ConfigBuilder`, so its
configuration methods remain available. To use a custom PHPStan configuration,
include `vendor/pushword/twigstan/config/phpstan.neon` in it and pass that file to
`phpstanConfigurationFile()`.

Add `/.twigstan/` to `.gitignore`, then run:

```shell
vendor/bin/pushword-twigstan
PUSHWORD_TWIGSTAN_HOST=example.org vendor/bin/pushword-twigstan
```

On the first run, Composer installs the locked analyzer dependencies in this
package's `tools/vendor`. The project autoloader takes precedence for shared
libraries. Analysis uses a separate Symfony container and disables Twig's compiled
cache. The trait supports both Symfony's default kernel and project-specific
container/route configuration methods. The loader enables the test service
container only for analysis and switches Pushword's request context to the selected
host. Unknown hosts fail explicitly.

The project's PHPStan extension installer is isolated from the analyzer. Include
only the extensions needed for template analysis in its PHPStan configuration;
the project's ordinary `composer stan` command keeps its own extensions.

## CI

Start with explicit template files whose PHP render context is known. Create
`twigstan-ci.php` with a narrower `twigPaths()` list using the same builder, then:

```shell
vendor/bin/pushword-twigstan --configuration=twigstan-ci.php --check-coverage
```

The command fails for analyzer errors or an entry template without a collected PHP
render context. The coverage check expects files rather than directory paths. It
counts each covered file and lists missing ones; it does not turn skipped templates
into successful checks. Run each configured host separately.

Dynamic template names, including Pushword's generic page renderer, still require
additional context discovery. A clean scoped CI run does not establish coverage of
every page template or of Twig embedded in page content.

## Temporary upstream patches

The isolated tool pins TwigStan 0.2.6, PHPStan 2.3.5 and Twig 3.30.0. Composer Patches
applies two local, hashed patches:

- [twigstan/twigstan#328](https://github.com/twigstan/twigstan/issues/328): preserve
  inherited parent block methods during flattening.
- [twigstan/twigstan#329](https://github.com/twigstan/twigstan/issues/329): accept
  guarded attribute access on a null value.

Nine fixtures verify the fixes and preserve real invalid-argument, missing-variable
and unguarded-null errors. Run `composer test` in this package to check them.

When upgrading TwigStan, remove fixed patch entries and their files, update the
pinned constraints, then run `patches-relock`, `update`, `patches-repatch` and the
tests from `tools/`. Commit the tool's dependency and patch locks together.

This repository is a distribution mirror. Source and changes live in the
[Pushword monorepo](https://github.com/Pushword/Pushword/tree/main/packages/twigstan).
