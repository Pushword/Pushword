---
title: 'Conversation: Add Comment, Newsletter Form or Contact For'
h1: Conversation
editMessage: 'Imported via pw:flat:sync from extension/conversation.md'
publishedAt: '2026-03-02 19:16'
parentPage: extensions
toc: true
filter_twig: 0
revision: a9a96ebb860049f2cdfd0ebda7ce072950ce5e15 # read only
---

Extend your Pushword website with **comments**, a **contact** form or just an **user input**.

## Install

Via #[Packagist](https://packagist.org/packages/pushword/conversation) :

```
# Get the Bundle
composer require pushword/conversation
```

That's it ! If you have a custom installation (not used the [default installer](/installation)),
you may have a look inside `vendor/pushword/admin/install.php`.

## Usage

### You can use it as is and include it in your Page with two manners :

```bash
# Load form via fetch (javascript)
<div data-live="{{ conversation('newsletter') }}"></div>

# Only fetch when a cookie is present (useful for cached/static pages)
<div data-live="{{ conversation('newsletter') }}" data-live-if="cookie:pw_auth=1"></div>
# =
<div data-live="{{ path('pushword_conversation', {'type': 'newsletter', 'referring': 'newsletter-'~page.slug, 'host': page.host}) }}"></div>

# Render form in Controller
{{ render(controller('Pushword\\Conversation\\Controller\\ConversationFormController::show')) }}

# Or add a button to click before loading block
<button data-src-live="{{ path('pushword_conversation', {'type': 'newsletter', 'referring': 'nslttr-'~page.slug, 'host': page.host}) }}" class="btn btn-primary">Register</button>

# Shorthand (obfuscates the URL automatically)
{{ conversationFormBtn('Register', 'newsletter', 'btn btn-primary') }}

# Advanced usage
<p>This is an invitation to <button data-src-live="..." data-target="parent">register</button></p>
```

Activate the `data-live` element with [@pushword/js-helper](https://github.com/Pushword/js-helper) :

````
import { liveForm } from "@pushword/js-helper/src/helpers";

// on dom changed and on page loaded :
liveBlock();
```

### Locale

The form is loaded by its own HTTP request, so it cannot guess the language of the page
embedding it. `conversation()` and `conversationFormBtn()` add `?locale=` for you; when
you build the URL by hand with `path('pushword_conversation', …)`, add it to render the
form in another language than the site's:

```twig
<div data-live="{{ path('pushword_conversation', {'type': 'newsletter', 'referring': 'newsletter-'~page.slug, 'host': page.host, 'locale': page.locale}) }}"></div>
```

Without `locale`, the form falls back to the locale of the site matching `host` (or the
requested host). The locale is applied early enough to reach the translator and the
validator, and is carried over to the next step of a multi-step form.

### Cross-origin or same-origin

`conversation()` and `conversationFormBtn()` return an **absolute** URL, built on the
site's `base_live_url`. That is what makes the form work on a statically generated host:
those pages have no PHP, so a relative URL would resolve against their own origin and 404.
The price is a cross-origin request — hence `possible_origins`, and cookies of the visited
host never reaching the handler.

When the static host proxies `/conversation/*` to PHP itself (a `reverse_proxy` matcher in
its Caddyfile, say), that price buys nothing: make the URL relative instead.

```yaml
conversation:
    conversation_absolute_url: false # default: true
```

It is an app fallback property, so a single site can opt out in its own
`pushword.apps[…]` entry while the others stay absolute. Already generated pages keep the
absolute URL they were built with — regenerate them before relying on the new one.

### Render published comment

```twig
{{ showConversation(referring[, orderBy, limit, template]) }}
````

### Get mail notification for new message

Configure the bundle directly in app configuration

```yaml
    conversation_notification_email_to: "example@example.tld",
    conversation_notification_email_from: "example@example.tld",
    conversation_notification_interval: "PT1S" #each 1s, default 1 time per day
```

## Customization

## Small rendering customization

By overriding `@PushwordConversation/conversation/conversation.html.twig`
(or `'@PushwordConversation/conversation/'.$type.'Step'.$step.'.html.twig`
or `'@PushwordConversation/conversation/'.$type.$referring.'Step'.$step.'.html.twig`).

## Create a new form

Per default, there are 4 form types: `newsletter`, `message`, `ms_message` and `multistep_message`.

Add a new class in bundle config `pushword_conversation.conversation_form.myNewType: myNewFormClass` or at the app level config `pushword.apps[...].conversation_form: [...]`

## Flat sync integration

When the [Flat extension](/extension/flat) is enabled, every `pw:flat:sync` run also synchronizes
conversation messages with a CSV file.

- **Export** : each message is written with its core fields (content, author, tags, dates, …) and one column per custom property.
- **Import** : editing the CSV lets you re-import messages, including any custom properties (arrays are encoded as JSON in their dedicated column).

This allows you to backup or edit conversations alongside pages and medias without needing a database access.

### Merge identity

Each message carries a `uuid` column: it is the merge identity between databases
that no longer travel together (a laptop and a production server each writing
their own SQLite). Import matches rows by uuid — an unknown uuid becomes a new
message, a known one is updated, and messages the CSV has never seen are kept
and re-exported into the file. Nothing is ever deleted by a sync, and ids may
differ between machines without messages overwriting each other.

Rows predating the column are backfilled on their first export (run
`bin/console doctrine:schema:update --force` after upgrading).

### Deletion

Deleting a message (admin or API) sets a `deletedAt` tombstone instead of
removing the row: a hard-deleted row would simply be recreated by the next
merge. Tombstoned messages disappear from the admin, the front and the API, but
stay in the database and the CSV so the deletion reaches every synced copy. An
empty `deletedAt` cell in a stale CSV never resurrects a deleted message —
deletion is sticky; to un-delete, clear the value in the database (or CSV) on
each side.

### Stale rows never overwrite fresher database edits

A row whose `updatedAt` is strictly older than the database row is stale — a
CSV rsynced from a machine that has not seen an edit made here (typically
through the production admin, when no pull happened before the deploy). Import
keeps the database version and, when the row's content differs, creates an
admin notification (with email alert) so the divergence is visible. Hand-edited
rows keep their exported `updatedAt` (equal, not older) and still apply.

### Storage mode

By default, all conversations are stored in a single global file at `content/conversation.csv`, regardless of host. This simplifies management, especially for single-site installations or when conversations don't need to be separated by host.

You can switch to per-host storage if needed:

```yaml
# config/packages/pushword.yaml
conversation:
  flat_conversation_global: true   # (default) Single file: content/conversation.csv
  # flat_conversation_global: false  # Per-host files: content/<host>/conversation.csv
```

The `host` column in the CSV preserves the host information in both modes, allowing filtering or migration between modes.

### CLI helpers

```bash
# Auto-detect import vs export (or force with -f import|export|sync)
php bin/console pw:message:flat [host] [-f sync]

# Import an external CSV without touching local files
php bin/console pw:message:import path/to/conversation.csv [--host=example.com]
```

## Review replies

Each review can carry a public **reply** and the **name of who replied**, both stored as
custom properties on the review. Edit them from `/admin/review`: the reply text is editable
inline directly in the list, and the full edit form exposes both the *Reply* and *Reply author*
fields. On the front, the reply renders below the review followed by a footer:
`— Reply from {author}`.

When a review has no specific reply author, the footer falls back to a site-wide default name.
Configure it per host (it is an app-fallback property, so a global default under `conversation:`
applies to every host unless overridden):

```yaml
pushword:
  apps:
    - hosts: ["example.com"]
      conversation_review_default_reply_author: "Lorène (Grand Angle)"
```

The `%siteName%` placeholder is replaced at render time with the current site name (the host's
`name`), so a single global default works across hosts:

```yaml
conversation:
  conversation_review_default_reply_author: "The %siteName% team"
```

It also works in a review's own *Reply author* field. When a review is saved from the admin
(form or inline reply) with an empty author, the configured default is written onto the review.
The footer also falls back to it at render time, so existing reviews and imports display it too.
With no author and no default configured, the footer shows a generic "Reply from the team".

## Review Translation

Automatically translate reviews to multiple languages using DeepL or Google Cloud Translation APIs.

### Configuration

Add your API keys in the pushword configuration:

```yaml
# config/packages/pushword.yaml
conversation:
  translation_deepl_api_key: '%env(DEEPL_API_KEY)%'
  translation_google_api_key: '%env(GOOGLE_API_KEY)%'
  translation_deepl_use_free_api: true  # Use DeepL free API endpoint
  translation_deepl_monthly_limit: 450000  # Monthly char limit (0 = unlimited)
  translation_google_monthly_limit: 450000
```

DeepL is used as the primary service (higher priority). If DeepL's monthly limit is exceeded or unavailable, Google Cloud Translation is used as fallback.

### Translate reviews

```bash
# Translate all reviews to French
php bin/console pw:conversation:translate-reviews --locale=fr

# Translate to multiple locales
php bin/console pw:conversation:translate-reviews --locale=fr,de,es

# Filter by host
php bin/console pw:conversation:translate-reviews --locale=fr --host=example.com

# Force re-translation of existing translations
php bin/console pw:conversation:translate-reviews --locale=fr --force

# Preview without making changes
php bin/console pw:conversation:translate-reviews --locale=fr --dry-run
```

The command automatically detects the source language of each review. If a review has no locale set, the translation API will detect it and save it for future use.

### Edit translations through the API

`/api/review` returns the translation map and writes it back, so a bad machine translation
can be fixed without re-running the command:

```bash
curl -X PATCH -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
     -d '{"translations":{"fr":{"title":"Titre","content":"Contenu"}}}' \
     https://example.com/api/review/42
```

Only the locales carried by the payload are written: an entry replaces that locale's
title/content pair, `"fr": null` removes the locale, and the locales left out keep what
they had.

### Display translated reviews

Translations are automatically displayed based on the current page locale. The `review.html.twig` template uses `page.locale` (or `app.request.locale` as fallback) to show the appropriate translation.

If no translation exists for the requested locale, the original content is displayed.

### Monthly usage tracking

Character usage is tracked per service per month in the `translation_usage` database table. When a service exceeds its configured limit, the system automatically falls back to the next available service.

To check current usage:

```bash
php bin/console dbal:run-sql "SELECT * FROM translation_usage"
```