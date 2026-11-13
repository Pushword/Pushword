---
title: 'Analyze Pushword templates with TwigStan'
publishedAt: '2026-11-13 00:00'
parentPage: architecture
toc: true
---

[TwigStan](https://github.com/twigstan/twigstan) analyzes Twig templates using PHPStan.
Pushword core provides `Pushword\Core\TwigStan\ResolveViewNodeVisitor` to make literal
`view()` calls resolvable during analysis. It uses the selected site's normal template
resolver, including host overrides, themes and fallback namespaces.

The visitor is activated explicitly in the analysis loader. It is not registered in
the application's Twig environment and does not add a TwigStan dependency to core.

## Patched tool in the Pushword monorepo

From the repository root:

```shell
composer twigstan
composer twigstan -- packages/core/src/templates/user/forgot_password.html.twig
composer twigstan-ci
composer test-twigstan
```

These commands install a separate development tool under `.scripts/twigstan/vendor`.
Composer Patches applies the checked-in patches automatically. The tool pins
TwigStan 0.2.6, Twig 3.30.0 and PHPStan 2.3.5; its dependency lock and patch hashes
are versioned separately from the application's dependencies.

The temporary fixes preserve inherited parent block methods
([#328](https://github.com/twigstan/twigstan/issues/328)) and accept guarded attribute
access on a null value ([#329](https://github.com/twigstan/twigstan/issues/329)).
The regression tests also check that invalid arguments, missing variables and
unguarded null access still produce errors.

To analyze another configured site:

```shell
PUSHWORD_TWIGSTAN_HOST=pushword.piedweb.com composer twigstan
```

The analysis uses its own Symfony container and separates TwigStan caches by host,
patch lock and PHP view adapter. Literal PHP `getView()` calls are resolved for the
selected site too, so their render arguments reach TwigStan's context collectors.
Dynamic template names remain unresolved.

PAO is disabled for this command because its PHPStan formatter
replaces the result files TwigStan needs. Remaining template diagnostics still
make the command fail.

### CI scope and diagnostic triage

`composer twigstan-ci` checks the 22 entry templates listed in
`.scripts/twigstan/ci.php`, plus their dependencies, at level 8. They cover login and
password forms, links, cards, videos, conversation notifications, static-server
configuration and progress fragments. The command also verifies that each entry
has a PHP render context; losing a collector cannot silently remove it from the
check. There is no baseline or ignored error in this scope.

The initial full run reported 159 diagnostics. Each was assigned to one of these
investigation groups; the counts are diagnostics, not confirmed bugs:

| Group | Diagnostics | Follow-up |
| --- | ---: | --- |
| EasyAdmin vendor templates | 60 | Embedded-template compilation and admin context typing |
| Controller, branch and form-theme context propagation | 44 | Preserve controller context separately from form widget context |
| Generated guards and specialized contexts | 23 | Distinguish redundant generated checks from template errors |
| Nullable request, admin, field or review context | 17 | Check request and branch narrowing before changing templates |
| Generated write-only escaper properties | 6 | TwigStan compiler output |
| Guarded optional values | 5 | Check `is defined` and coalescing propagation |
| Library type contracts | 4 | Compare declared types with runtime behavior |

For example, the Git history timestamp renders correctly as an integer despite
IntlExtension's narrower PHPDoc, and newsletter design variables are supplied by
the controller despite form-theme diagnostics. Existing runtime tests cover these
cases. The PHP view adapter expands the full analysis and can expose additional
diagnostics; the full command remains outside the required CI check.

Inspecting the gallery also uncovered two runtime bugs: an image's link override
leaked to following images, and unlinked images were escaped as HTML text. Both
have rendering regression tests. The gallery remains outside TwigStan CI while
its generated optional-attribute guards still produce false positives.

When an upstream release fixes either issue, remove the corresponding patch entry
and file, update the pinned dependencies in `.scripts/twigstan/composer.json`, then run:

```shell
composer --working-dir=.scripts/twigstan patches-relock
composer --working-dir=.scripts/twigstan update
composer --working-dir=.scripts/twigstan patches-repatch
composer test-twigstan
```

Commit the updated tool lock files with the patch removal. The setup below installs
upstream TwigStan directly in a downstream project; it does not apply these patches.

## Install and configure

Install TwigStan as a development dependency in the downstream project:

```shell
composer require --dev twigstan/twigstan:^0.2.6
```

Create `twigstan-loader.php` in the project root. This example uses Symfony's test
container to access private services; the `test` environment must enable
`framework.test` and provide the environment variables needed to boot the application.

```php
<?php

use App\Kernel;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\TwigStan\ResolveViewNodeVisitor;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new Kernel('test', true);
$kernel->boot();

$container = $kernel->getContainer()->get('test.service_container');
$twig = $container->get('twig');
$site = $container->get(SiteRegistry::class)->getDefault();

// Keep analysis-specific compiled templates out of the application's Twig cache.
$twig->setCache(false);
$twig->addNodeVisitor(new ResolveViewNodeVisitor($site));

return $kernel;
```

Create `phpstan-twigstan.neon` with analysis settings for templates, separate from
the project's PHP rules:

```neon
parameters:
    level: 8
```

Create `twigstan.php`:

```php
<?php

use TwigStan\Config\TwigStanConfig;

return TwigStanConfig::configure(__DIR__)
    ->phpstanConfigurationFile(__DIR__.'/phpstan-twigstan.neon')
    ->phpstanMemoryLimit('1G')
    ->twigEnvironmentLoader(__DIR__.'/twigstan-loader.php')
    ->tempDirectory(__DIR__.'/.twigstan/default')
    ->phpPaths(__DIR__.'/src', __DIR__.'/vendor/pushword/core/src')
    ->twigPaths(__DIR__.'/templates')
    ->create();
```

Add other installed bundles' PHP source directories when they render templates you
want to analyze. Add `.twigstan/` to `.gitignore`, then run `vendor/bin/twigstan`.
Returning the kernel lets TwigStan also inspect Symfony routes and security rules.

## What the visitor resolves

For the selected site, this expression:

```twig
{% extends view('/base.html.twig') %}
```

becomes a literal template reference in the parsed tree, such as
`@Pushword/base.html.twig` or a host-specific override. TwigStan can then follow the
parent and propagate the controller's variables into child blocks. The same
resolution applies to literal `view()` calls in includes and other expressions.

Both positional and named arguments are supported, including an explicit fallback
namespace and `fallback: null`. Calls with dynamic arguments, such as
`view(page.template)`, remain unresolved. Invalid arguments remain intact so the
analyzer can report them.

## Multiple sites and coverage limits

Run analysis separately for each configured site: replace `getDefault()` in the
loader with `findByHost('your-host')` and fail if the host is unknown. Give each run
a separate TwigStan temporary directory. A template path can resolve to different
parents for different hosts, so one site's results do not cover the others.

This visitor resolves template references. It does not discover PHP render calls
whose template names are selected dynamically, such as Pushword's main page render
path, or infer values from page content. TwigStan's default mode only reports
findings for templates with a known render context; scanning a file does not by
itself establish coverage. Review those render points before making the analysis
a required CI check.
