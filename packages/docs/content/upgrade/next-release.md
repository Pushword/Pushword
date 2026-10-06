---
title: 'unused PHP helper APIs removed'
publishedAt: '2099-01-01 00:00'
parentPage: upgrade
run: cache:clear
---

<!--
The upgrade note for the next release. `.scripts/release` renames this file to
`upgrade/<version>.md`, adds its row to the table in `upgrade.md` and empties it
back to this scaffold, at the tag.

Write here, in the same commit as the change, whenever a release asks something of
a site that upgrades: a command to run, a config key to set, a template to copy, a
behaviour that changed under an unchanged call. A change `composer update` fully
absorbs needs no note.

Keep it short: what changed, and what to do about it. A note is a checklist, not a
changelog and not a post-mortem — no cause, no code path, no story of the bug. That
belongs in the feature doc, which you link to instead.

- `title:` — the "What changed" cell of the index table. One line, lower case,
  written from the site's side ("the newsletter form is fetched, and CSRF-protected")
  rather than the diff's ("refactor NewsletterFormController"). Several changes: one
  short clause each, semicolon-separated, naming only those that ask something.
  Required as soon as the note has a section; the release stops if it is still empty.
- `run:` — the command(s) the release expects, without `php bin/console`. Omit the
  key when there is none. A list runs in the order given.
- `**Concerns:**` — first line of the body, listing every package a site has to
  install to be affected. Alphabetical, full composer names, `@pushword/js-helper`
  last. Add the packages your change touches to the line, keep the others.
- One `##` section per change, five lines at most: one sentence for what changed, a
  bold line for who is affected when only some sites are, then the action — a command,
  a config key, an edit to make. Nothing to do: say so in the sentence and stop.

Several changes land here between two tags: append to the file, do not replace it.
-->

**Concerns:** `pushword/admin`, `pushword/conversation`, `pushword/core`, `pushword/flat`, `pushword/newsletter`, `pushword/page-scanner`, `pushword/quiz`, `pushword/repurpose`, `pushword/snippet`, `pushword/static-generator`

## Manually constructed services

Remove the unused Twig argument from `Pushword\Core\Twig\AppExtension`, the router
argument from `Pushword\Core\Twig\PageExtension`, and the image-cache manager argument
from `Pushword\Admin\Controller\MediaCrudController` if constructing them manually.

## Admin helpers

Remove calls to `AdminFormFieldManager::getEntityManager()` (use its public `em` property),
`getMessagePrefix()`/`setMessagePrefix()`, and `FormFieldReplacer::count()`.
The CRUD controllers' `MESSAGE_PREFIX` constants and `MediaCrudController::getThumbnailUrl()`
are gone; use `Thumb::PLACEHOLDER_DATA_URI` instead of `Thumb::$thumb` for the placeholder.

## Core configuration and repository helpers

Remove references to `Pushword\Core\DependencyInjection\AppsConfigParser` and `Pushword\Core\Site\SiteAssets`.
`FilterRegistry::hasFilter()` is replaced by checking `getFilter()` for null; configure image filters
through `image_filter_sets` instead of `ImageCacheManager::setFilters()`.
Unused repository helpers `LoginTokenRepository::deleteExpiredTokens()`, `MediaRepository::getAllMedia()`
and `PageRepository::getPagesWithoutParent()` are gone; use Doctrine queries for these operations.

## Core context and authentication helpers

Use `RequestContext::setRequestContext()` instead of `SiteRegistry::setRequestContextData()`.
Use Symfony's `UserAuthenticatorInterface::authenticateUser()` for programmatic login;
`MagicLinkAuthenticator::createPassport()` and `HtmlBeautifer::removeHtmlComments()` are gone.

## Conversation quota helper

`TranslationUsageTracker::getRemainingCharacters()` is gone. Use `getCurrentMonthUsage()`
and the configured limit when displaying a remaining quota, or `isWithinLimit()` when checking it.

## Flat helpers

Check `AdminNotification::getType()` against its type constants instead of `isConflict()`,
`isSyncError()` or `isLockInfo()`. Use `MediaImporter::getMissingFiles()` instead of the `MediaSync` wrapper.
Unused `ConflictResolver::setCurrentHost()`, `resolveMediaConflict()` and `recordCsvConflict()`
are gone; custom integrations must handle their own additional conflict reporting.

## Newsletter helpers

Compare `CampaignRecipient::$state` with `RecipientState::Pending` instead of `isPending()`.
Read `Enrollment::$status->value` instead of `getStatusLabel()`, and use
`EnrollmentRepository::findOneBy()` instead of `findOneFor()`.

## Page scanner helpers

The unused `LinkedDocsScanner::disableCollectMode()`, `disableCheckUnpublished()`
and `getLinksCheckedCounter()` helpers are gone. Use separate scanner instances
if a custom integration needs both enabled and default modes in the same process.

## Quiz and repurpose helpers

Decode the schema providers' `json()` output instead of calling `toArray()`.
Unused `LaidOutText::maxWidth()`, `BackgroundEffectRegistry::all()`, `ChromiumRasterizer::available()`
and `FontResolver::bodyFamily()` are gone; use the existing rendering and font-resolution APIs.

## Snippet lookup helper

Use `SnippetRepository::findOneBy()` with a normalized slug and host instead of
`findOneBySlugAndHost()`.

## Static generation helpers

Subscribe to `StaticPreGenerateEvent` and `StaticPostGenerateEvent` by class name instead of
the unused `PushwordEvents::STATIC_PRE_GENERATE`/`STATIC_POST_GENERATE` constants.
Use `Configuration::DEFAULT_ASSETS` instead of `DEFAULT_COPY`. Unused `GenerationStateManager::getPageState()`/
`clearHost()`, `AbstractGenerator::copy()`, `KernelTrait::$kernel` and `StaticAppGenerator::isIncremental()` are gone.
