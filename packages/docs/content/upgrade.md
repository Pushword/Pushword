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
| [rc875](/upgrade/rc875) | `flat` | an unchanged `pw:flat:sync` no longer bumps every page's `updatedAt` and `revision:` |
| [rc874](/upgrade/rc874) | `core` | `pw:image:cache` in parallel no longer dies on a large media library |
| [rc873](/upgrade/rc873) | `core` `quiz` | `fenced_code_pre_class` works for the first time; quiz images size themselves; body images size themselves inside a quiz |
| [rc872](/upgrade/rc872) | `core` | markdown body images take their `sizes` from `body_image_sizes`, settable per app |
| [rc871](/upgrade/rc871) | `core` `js-helper` | links to unpublished pages are restored from the pw_auth cookie instead of an auth probe; `image()` takes a `sizes` argument and defaults to `100vw` |
| [rc869](/upgrade/rc869) | `newsletter` | a newsletter form can be fetched by the front end itself, so a modal loads it on open rather than on every page load |
| [rc868](/upgrade/rc868) | `newsletter` | the newsletter subscribe token is signed instead of session-bound, so a statically generated site keeps CSRF protection on |
| [rc866](/upgrade/rc866) | `newsletter` | audiences carry a postal address, printed at the foot of every mail; confirmation mails are capped per address, so resubmitting no longer resends at once |
| [rc865](/upgrade/rc865) | `admin-block-editor` `core` `flat` | rendered text gets locale-aware typography (smart quotes, non-breaking spaces…); sources stay plain — the editor no longer writes typographic characters and the flat export straightens them |
| [rc864](/upgrade/rc864) | `advanced-main-image` | the main image "∅" format is now labelled None (Aucun in French) |
| [rc858](/upgrade/rc858) | `api` `conversation` | /api/conversation and /api/review each serve only their own type |
| [rc857](/upgrade/rc857) | `newsletter` | the tracking opt-in mail states its purpose in a hint line over a bare confirm button |
| [rc856](/upgrade/rc856) | `newsletter` | newsletter mails can track clicks, behind a double consent — run `doctrine:schema:update --force` |
| [rc853](/upgrade/rc853) | `admin` `admin-block-editor` | the show-more calls accept named arguments, and the editor writes them |
| [rc852](/upgrade/rc852) | `admin` `admin-block-editor` `conversation` `core` `js-helper` | a group can be made collapsible from the block editor, and the legacy show-more markers are parsed, paired and convertible |
| [rc851](/upgrade/rc851) | `quiz` | the quiz question list opts out of the host's prose styling |
| [rc850](/upgrade/rc850) | `admin` `core` `flat` | saving a page in the admin without editing it no longer counts as an edit; a page imported from a flat file gets an author, and editing a page no longer claims its authorship |
| [rc849](/upgrade/rc849) | `admin-block-editor` `core` `flat` | the link panel's style options are named in English so they can be translated; the static build's workers drop the opcache file cache that was killing them; link-improver counts a page's links once, so a site whose links are written absolute gets the density it configured; a flat file edited in the same second as a sync is no longer lost; SQLite enforces the schema's foreign keys and makes a concurrent writer wait rather than fail |
| [rc848](/upgrade/rc848) | `admin` `core` | the Docker account guard and the per-editor unsaved-changes key described in rc845 actually ship here; a new project installs again |
| [rc845](/upgrade/rc845) | `admin` `admin-block-editor` `core` `newsletter` `page-scanner` `snippet` `js-helper` | a forged delivery report can no longer unsubscribe an address; a Docker container no longer seeds a default-credential super admin over a restored database; a missing template degrades instead of 500ing the page; media uploaded after the pages naming them get their usage rows — run `pw:media:usage:rebuild` |
| [rc843](/upgrade/rc843) | `admin` `admin-block-editor` `advanced-main-image` `api` `conversation` `core` `flat` `installer` `link-improver` `newsletter` `page-scanner` `page-update-notifier` `quiz` `repurpose` `search` `snippet` `static-generator` `template-editor` `version` `js-helper` | every bundle registers itself in config/bundles.php and imports its own routes; the markdown body is edited in Monaco instead of EasyMDE |
| [rc842](/upgrade/rc842) | `admin-block-editor` `core` `link-improver` | card lists and galleries lay themselves out from the wrapper |
| [rc841](/upgrade/rc841) | `static-generator` | multilingual static sites serve the localized 404 page |
| [rc840](/upgrade/rc840) | `core` `js-helper` | full-bleed blocks no longer scroll the page sideways |
| [rc839](/upgrade/rc839) | `core` `dev-app` `installer` `page-scanner` | Docker is offered at install time; the page scan sees unreachable hosts again |
| [rc838](/upgrade/rc838) | `admin-block-editor` `page-scanner` | the block editor uploads inline, groups blocks under a div wrapper, links carry any rel, and page-scan tells an unreachable link from a bad status |
| [rc837](/upgrade/rc837) | `admin-block-editor` `core` `js-helper` | pages_list gets a CSS-only horizontal scroller |
| [rc835](/upgrade/rc835) | `admin` `admin-block-editor` `core` `flat` `js-helper` `newsletter` `page-scanner` `static-generator` | the database now knows which pages use which media; admin dates follow the locale; page-scan findings have a code |
| [rc834](/upgrade/rc834) | `core` `newsletter` `page-scanner` `js-helper` | the page scanner reports missing image alts and colliding translation locales, two newsletter contacts can be joined into one, galleries, videos and body images open in the lightbox again |
| [rc833](/upgrade/rc833) | `newsletter` | newsletter contacts can be keyed on a phone number, campaigns carry one body per locale, and bounces can be read over IMAP — run `doctrine:schema:update --force` |
| [rc832](/upgrade/rc832) | `newsletter` | a mailbox of delivery failures can be read back into the list |
| [rc831](/upgrade/rc831) | `search` | the static search endpoint ships a populated index, and an unreadable index rebuilds itself — run `pw:static` |
| [rc830](/upgrade/rc830) | `static-generator` | parallel static workers each get their own opcache file cache |
| [rc829](/upgrade/rc829) | `newsletter` | a fresh install ships real starter content; six unused editorjs twig helpers are gone; automations keep a send ledger |
| [rc828](/upgrade/rc828) | `core` `installer` `new` | the first install is repaired: no destructive installer, a super admin, an AGENTS.md |
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
