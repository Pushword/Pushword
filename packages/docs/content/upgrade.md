---
title: 'Upgrade a Pushword installation | Changelog'
h1: 'Upgrade Guide'
publishedAt: '2026-01-30 13:24'
parentPage: installation
---

Smooth way is to use [composer](https://getcomposer.org), a dependency manager for PHP.

Run `composer update` and the job is done (almost).

`composer show pushword/core` tells you which version you run. Read every note below
that is newer than it, oldest first, and skip the ones whose packages you do not
install. Releases absent from the table ask for nothing beyond `composer update`.

Package names drop their `pushword/` prefix; `js-helper` is the npm package
`@pushword/js-helper`.

## Release notes

| Version | Packages | What changed |
| --- | --- | --- |
| [rc828](/upgrade/rc828) | `core` `installer` `new` | the first install is repaired: no destructive installer, a super admin, starter content |
| [rc827](/upgrade/rc827) | `conversation` `core` `page-scanner` `repurpose` `snippet` | four more entities expose their columns as properties |
| [rc825](/upgrade/rc825) | `core` | page exposes its columns as properties, with no getter/setter left |
| [rc823](/upgrade/rc823) | `newsletter` | content triggers merged into automations, newsletter entities expose properties — run `doctrine:schema:update --force` |
| [rc819](/upgrade/rc819) | `admin` `core` `js-helper` | htmx 4 in the admin, gated live blocks |
| [rc812](/upgrade/rc812) | `flat` | the deploy resets production even when the push fails |
| [rc808](/upgrade/rc808) | `flat` | the deploy excludes every generated output |
| [rc807](/upgrade/rc807) | `flat` | the deploy counts its deletions before making them |
| [rc805](/upgrade/rc805) | `flat` | the deploy script ships with the package |
| [rc804](/upgrade/rc804) | `api` `conversation` `newsletter` `quiz` | quiz and newsletter API coverage, conversation merge safety |
| [rc803](/upgrade/rc803) | `api` `flat` | one serializer for the flat page file, raw `.md` API intake |
| [rc802](/upgrade/rc802) | `conversation` `flat` `quiz` | flat sync survives split databases — run `doctrine:schema:update --force` |
| [rc799](/upgrade/rc799) | `core` | entity and site state are native properties (PHP 8.4) |
| [rc798](/upgrade/rc798) | `newsletter` `js-helper` | the newsletter form is fetched, and CSRF-protected |
| [rc796](/upgrade/rc796) | `core` `search` | search runs on Loupe 1.0, `pages_list` searches are parsed — run `pw:search:index` |
| [rc787](/upgrade/rc787) | `admin` | the browser no longer strips the rights it uploads |
| [rc785](/upgrade/rc785) | `core` | image rights are read from PNG, WebP and C2PA — run `pw:media:license --all` |
| [rc784](/upgrade/rc784) | `admin` `core` | image license metadata — run `doctrine:schema:update --force`, then `pw:media:license` |
| [rc769](/upgrade/rc769) | `conversation` | the author IP is stored as text — run `doctrine:schema:update --force` |
| [rc757](/upgrade/rc757) | `core` `dev-app` | `pushword/skeleton` renamed to `pushword/dev-app` |
| [rc673](/upgrade/rc673) | `conversation` | review replies |
| [rc650](/upgrade/rc650) | `version` | activity journal — run `doctrine:schema:update --force` |
| [rc637](/upgrade/rc637) | `api` `core` `static-generator` | per-page publication hold, `page-workflow` removed — run `doctrine:schema:update --force` |
| [rc627](/upgrade/rc627) | `core` `flat` | `redirectFrom` authored on the destination page — run `doctrine:schema:update --force` |
| [rc623](/upgrade/rc623) | `api` | the REST API becomes its own bundle |
| [rc621](/upgrade/rc621) | `core` `page-scanner` `js-helper` | unpublished links are restored for logged-in editors |
| [rc589](/upgrade/rc589) | `core` | `image()` is the single entry point for images |
| [rc564](/upgrade/rc564) | `js-helper` | the package installs from GitHub, not npm |
| [rc555](/upgrade/rc555) | `core` | retina fix: the `thumb` filter leaves the default filter sets |
| [rc437](/upgrade/rc437) | `flat` | deferred export runs on Messenger, optional git auto-commit |
| [rc372](/upgrade/rc372) | `admin` `core` | entity, site and content-pipeline overhaul — run `doctrine:schema:update --force`, then `pw:migrate` |
| [rc371](/upgrade/rc371) | `conversation` `core` `installer` | `Dimensions` value object, media filename utils, conversation route |
| [rc341](/upgrade/rc341) | `conversation` `core` `page-update-notifier` | unified notification email service |
| [rc333](/upgrade/rc333) | `admin` `admin-block-editor` `conversation` `core` | template changes (DOM simplification) |
| [rc294](/upgrade/rc294) | `core` | `MainContentSplitter` replaced by a Twig function |
| [rc247](/upgrade/rc247) | `core` `js-helper` | Symfony 8 — run `pw:image:cache` |
| [rc80](/upgrade/rc80) | `admin` `admin-block-editor` `core` `flat` `js-helper` | Sonata to EasyAdmin, Tailwind v4, Markdown-backed editor |
| [rc79](/upgrade/rc79) | `core` | entity cleanup, `pushword/svg` dropped |
