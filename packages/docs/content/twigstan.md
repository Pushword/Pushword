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

## Install in a Pushword project

Install the development package alongside Pushword core 1.0.108 or later:

```shell
composer config repositories.pushword-twigstan vcs https://github.com/Pushword/twigstan
composer require --dev pushword/twigstan:^1.0.108
```

Follow the [package setup](https://github.com/Pushword/twigstan#install): a small
analysis kernel outside `src/`, a loader, and `twigstan.php` selecting PHP sources
and templates. The package preserves the project's kernel configuration, enables
its analysis service container and keeps its cache under `.twigstan/`. Add that
directory to `.gitignore`.

The analyzer dependencies and temporary patches are locked inside the package.
Their installation does not change the site's PHPStan or its extension installer.
Only extensions explicitly included in the template-analysis PHPStan configuration
are loaded. Shared application libraries retain autoload priority.

Run from the project root:

```shell
vendor/bin/pushword-twigstan
PUSHWORD_TWIGSTAN_HOST=example.org vendor/bin/pushword-twigstan
```

Literal Twig `view()` and PHP `getView()` calls use the selected site's real
resolver, including host overrides, themes and fallback namespaces. The loader
switches the request context to that host; unknown hosts fail explicitly. Run each
host separately rather than treating one site's results as coverage of all sites.

Dynamic template names, including Pushword's generic page renderer, and Twig
embedded in page content still require additional context discovery. Scanning a
file does not establish coverage when no render context is known.

## CI

Start with explicit template files having known PHP render points. A separate
`twigstan-ci.php` can select this narrower list. Run:

```shell
vendor/bin/pushword-twigstan --configuration=twigstan-ci.php --check-coverage
```

The command fails on analyzer errors and on any entry without a collected PHP
render context. Its final report counts each covered template and lists missing
ones. The coverage mode expects template files rather than directories.

## Pushword monorepo

The monorepo uses the same package through thin wrappers under `.scripts/twigstan/`:

```shell
composer twigstan
composer twigstan -- packages/core/src/templates/user/forgot_password.html.twig
composer twigstan-ci
composer test-twigstan
```

`composer twigstan-ci` checks the 22 entries in `.scripts/twigstan/ci.php`, plus
their dependencies, at level 8. These cover login and password forms, links, cards,
videos, conversation notifications, static-server configuration and progress
fragments. There is no baseline or ignored error in this scope.

## Temporary upstream patches

The package's isolated tool pins TwigStan 0.2.6, PHPStan 2.3.5 and Twig 3.30.0.
Composer Patches applies hashed local fixes for inherited parent block methods
([#328](https://github.com/twigstan/twigstan/issues/328)) and guarded attribute
access on null ([#329](https://github.com/twigstan/twigstan/issues/329)). Nine
regression fixtures also verify that invalid arguments, missing variables and
unguarded null access still produce errors.

For an upstream upgrade, remove fixed patch entries and their files under
`packages/twigstan/tools/`, update the pinned constraints, then run:

```shell
composer --working-dir=packages/twigstan/tools patches-relock
composer --working-dir=packages/twigstan/tools update
composer --working-dir=packages/twigstan/tools patches-repatch
composer test-twigstan
```

Commit the dependency and patch locks with the patch removal. PAO is disabled in
analysis subprocesses because its PHPStan formatter replaces TwigStan's result
files.

## Initial diagnostic triage

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
