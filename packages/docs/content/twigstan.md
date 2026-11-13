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
