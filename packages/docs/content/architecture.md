---
title: 'Pushword Code Architecture and preparing a development environment'
h1: 'The Code Architecture'
publishedAt: '2025-12-21 21:55'
toc: true
---

This page describes the Pushword monorepo and its development setup. For application
structure, see [Symfony best practices](https://symfony.com/doc/current/best_practices.html)
and the [dev app](https://github.com/Pushword/Pushword/tree/main/packages/dev-app). For
extension points, see [Extensions](/extensions).

## Code Architecture

The core and all officially maintained extensions live in one repository. The
[core](https://github.com/Pushword/Pushword/tree/main/packages/core) provides the shared
entities and rendering pipeline; optional bundles add product features. Larger core
features that do not warrant a package live under `Component/`.

The experimental [Rust acceleration design](/native-acceleration) describes how
selected Pushword operations could gain an optional native implementation while
the complete PHP path remains available on shared hosting.

The [dev app](https://github.com/Pushword/Pushword/tree/main/packages/dev-app) is the test,
demo and documentation application, not a project template. The installer copies selected
starter files from it into new projects.

## On top of Symfony

The core and feature packages are built as [Symfony bundles](https://symfony.com/doc/current/bundles.html). The installer, project template, documentation and Node tooling packages are not bundles.

The `core` package requires a Symfony app installed to be functional.

## Development environment

This setup is for [contributing](/contribute). To build a site, follow the
[installation guide](/installation).

1. Check you have installed all the [required dependencies](/installation).

2. Fork if needed, then clone the [repository](https://github.com/Pushword/Pushword).

3. Install dependencies and initialize the dev app.

```shell
composer update && composer reset-dev-app
```

### Useful commands

```shell
# php-cs-fixer
composer format

# run Rector and format
composer rector

# run phpstan
composer stan

# run the test suite
composer test

# to play with default app console (dev-app)
composer console ...
```

## Packages

| Package | Purpose | Depends on |
|---------|---------|------------|
| core | Base entities (Page, Media, User), entity filters, events, controllers, Twig extensions | — |
| admin | CRUD interface for Page, Media, User built on EasyAdmin | core |
| admin-block-editor | Rich text / block editor for the admin | core |
| advanced-main-image | Main image format options (hidden, hero, etc.) and default templates | core, admin |
| ai-skills | AI authoring skills (the `/pw` router skill and its reference playbooks) — Node package | — |
| api | Token-authenticated REST API mirroring the admin, with an OpenAPI endpoint | core, flat |
| conversation | Comments, contact forms, user input | core, flat |
| docs | Project documentation (ships to end users as `vendor/pushword/docs/content/`) | — |
| flat | Flat-file CMS mode: sync pages/media between database and filesystem | core |
| installer | Bootstraps a new Pushword project | core |
| js-helper | Shared JavaScript utilities (Node package) | — |
| admin-monaco-editor | Monaco editor for the admin: Twig/YAML/JSON fields, and the markdown body with its toolbar (Node package) | — |
| new | Project template whose Flex requirements install the standard bundles | — |
| link-improver | Add bounded, auditable internal links at render time | core |
| newsletter | Audiences, contacts, segmented campaigns and criteria-driven automations | core |
| page-scanner | Dead link detection, 404/301 checks, TODO scanning | core |
| page-update-notifier | Email notifications on page edits | core |
| quiz | Interactive client-side QCM / personality tests via a `{% quiz %}` block | core |
| repurpose | Turn a page into social carousels rendered as SVG slides (PNG/PDF export) | core |
| search | Optional SQLite full-text search via Loupe (zero infra) | core |
| dev-app | Dev/test/demo app (not a copy-paste starter for end users; formerly named `skeleton`) | core |
| snippet | Reusable content fragments + dev-registered components, via `snippet()` | core |
| static-generator | Generate static HTML sites | core |
| template-editor | Edit Twig templates from the admin UI | core |
| version | Page versioning | core |
