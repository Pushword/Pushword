---
title: 'Conversation: Add Comment, Newsletter Form or Contact Form'
h1: Conversation
editMessage: 'Imported via pw:flat:sync from extension/conversation.md'
publishedAt: '2026-03-02 19:16'
parentPage: extensions
toc: true
filter_twig: 0
revision: a9a96ebb860049f2cdfd0ebda7ce072950ce5e15 # read only
---

Comments, contact and newsletter forms, and customer reviews.

## Install

```shell
composer require pushword/conversation
```

Custom installations (not the [default installer](/installation)): see `vendor/pushword/admin/install.php`.

## Embed a form

Forms are fetched by their own request, so they work on cached and static pages:

```twig
{# Fetched on load #}
<div data-live="{{ conversation('newsletter') }}"></div>

{# Fetched only when a cookie is present #}
<div data-live="{{ conversation('newsletter') }}" data-live-if="cookie:pw_auth=1"></div>

{# Fetched on click; the shorthand obfuscates the URL #}
{{ conversationFormBtn('Register', 'newsletter', 'btn btn-primary') }}
<button data-src-live="{{ conversation('newsletter') }}" class="btn btn-primary">Register</button>
<p>An invitation to <button data-src-live="..." data-target="parent">register</button></p>

{# Rendered server-side #}
{{ render(controller('Pushword\\Conversation\\Controller\\ConversationFormController::show', {type: 'newsletter'})) }}
```

`data-live` and `data-src-live` are handled by `liveBlock()` from
[@pushword/js-helper](https://github.com/Pushword/js-helper) (already called by its `app.js`).

`conversation(type, referring = type)` builds
`path('pushword_conversation', {type, referring: referring~'_'~host~'/'~slug})` plus
`?host=` and `?locale=`. When you build that URL by hand, pass `locale` yourself, or the
form renders in the site's default locale.

### Absolute or relative URL

`conversation()` and `conversationFormBtn()` return an **absolute** URL on the site's
`base_live_url`, so a statically generated page (no PHP) fetches the form from the live
host. That request is cross-origin: allow the static origins in
`conversation_possible_origins` (space-separated, per site or globally under
`conversation:`), and expect the visited host's cookies not to reach the handler.

Any other origin gets a 403 before its posts count against the visitor's submission limit
(20 per hour per IP and site). An allowed origin gets the CORS headers on every answer,
refusals included, so the page sees a 404 or a 429 instead of a network error: the form
fires `live-block-forbidden` with `{status, url, retryAfter}`, where `retryAfter` is the
429's `Retry-After` header (`null` otherwise).

If the static host proxies `/conversation/*` to PHP itself, make the URL relative (per
site, or globally):

```yaml
conversation:
  conversation_absolute_url: false # default: true
```

Regenerate static pages afterwards; they keep the URL they were built with.

⚠ The generated Caddyfile 301s any path ending in `/` to the slash-less one, dropping the
query string. A **homepage** form's `referring` ends in `/` (empty slug), so a relative URL
gets rewritten in flight. Set `false` only where no homepage carries a form, or where the
proxied paths are exempt from that redirect.

## Published comments

```twig
{{ showConversation(referring[, orderBy = 'createdAt ASC', limit = 0, view]) }}
```

## Mail notifications

Per site, or globally under `conversation:`:

```yaml
conversation_notification_email_to: 'example@example.tld'
conversation_notification_email_from: 'example@example.tld'
conversation_notification_interval: 'P1D' # default; a PHP DateInterval
```

Messages with a valid author email are notified one by one. For the others, schedule
`Pushword\Conversation\Service\NewMessageMailNotifier::send()`, which mails a summary at
most once per interval.

## Customize

Override `@PushwordConversation/conversation/conversation.html.twig`, or per step
`{type}Step{step}.html.twig` / `{type}{referring}Step{step}.html.twig` in the same folder.

### Add a form type

Built-in types: `newsletter`, `message`, `ms_message`, `multistep_message`. Register yours
under `conversation.conversation_form` (globally or per site):

```yaml
conversation:
  conversation_form:
    my_type: App\Form\MyForm # implements ConversationFormInterface
```

An unregistered type falls back to `App\Form\{type}`.

### Drive the steps yourself

A form declares `getStepOne()`, `getStepTwo()`, … and lets `defaultStepValidator()` move
between them. To run your own transition (an API call before advancing), override
`validStepN()` and use:

- `advanceStep()` — moves to the next step **and** stores the workflow. The next POST is
  refused ("Conversation workflow not found") unless the stored state matches its step,
  so never call `incrementStep()` alone.
- `deleteWorkflow()` — burns the token; call it before `showSuccess()` so the last step
  cannot be replayed.

```php
protected function validStepTwo(FormInterface $form): string
{
    if (! $form->isValid()) {
        return $this->showForm($form);
    }

    $this->subscribeToNewsletter($this->message);
    $this->advanceStep();

    return $this->showForm($this->getCurrentStep()->getForm());
}
```

## Flat sync

With the [Flat extension](/extension/flat), every `pw:flat:sync` also syncs messages with a
CSV: core fields plus one column per custom property (arrays as JSON). Dates are exported
as ISO 8601 with their offset; on import (and through the API) an offset is kept to the
instant, and a date without one is read in the
[editorial timezone](/extension/flat#dates-and-time-zones).

- **Merge identity** — rows match by `uuid`, so databases that no longer travel together
  (laptop and production SQLite) merge without ids colliding. Unknown uuid: new message;
  known: updated; messages absent from the CSV are kept and re-exported. A sync never
  deletes.
- **Deletion** — deleting (admin or API) sets a `deletedAt` tombstone, hidden everywhere
  but kept in the database and CSV so the deletion reaches every copy. An empty
  `deletedAt` in a stale CSV never resurrects a message; to un-delete, clear the value on
  each side.
- **Stale rows** — a row whose `updatedAt` is older than the database's is not applied;
  if its content differs, an admin notification (with email) reports the divergence.
  Hand-edited rows keep their exported `updatedAt` and apply.

Storage defaults to one `content/conversation.csv` for every host; the `host` column is
kept in both modes:

```yaml
conversation:
  flat_conversation_global: false # per host: content/<host>/conversation.csv
```

```bash
php bin/console pw:message:flat [host] [-f import|export|sync] # default: auto-detect
php bin/console pw:message:import path/to/conversation.csv [--host=example.com]
```

## Reviews

```twig
{{ reviews(pageOrTag = current page, limit = 10) }} {# alias: reviewList() #}
{{ reviewsCount(pageOrTag) }}
```

A page matches reviews tagged with its slug. Disable the review admin with
`conversation.review_enabled: false`.

### Replies

Each review carries a public **reply** and its **author** (custom properties), edited at
`/admin/review` — the reply inline in the list, both in the form. The front renders the
reply followed by `— Reply from {author}`.

An empty author falls back to `conversation_review_default_reply_author` (per site, or
globally), where `%siteName%` is replaced by the site name; saving from the admin writes
that default onto the review. With neither, the footer reads "Reply from the team".

```yaml
conversation:
  conversation_review_default_reply_author: 'The %siteName% team'
```

### Translation

Reviews are translated with DeepL, falling back to Google Cloud Translation when DeepL's
monthly limit is reached or it fails:

```yaml
conversation:
  translation_deepl_api_key: '%env(DEEPL_API_KEY)%'
  translation_google_api_key: '%env(GOOGLE_API_KEY)%'
  translation_deepl_use_free_api: true
  translation_deepl_monthly_limit: 450000 # characters, 0 = unlimited
  translation_google_monthly_limit: 450000
```

```bash
php bin/console pw:conversation:translate-reviews --locale=fr,de [--host=example.com] [--force] [--dry-run] [--delay=1]
```

A review without a locale gets the one the API detects. Usage is counted per service and
month in the `translation_usage` table.

`review.html.twig` shows the translation for `page.locale` (else the request locale), or
the original when there is none.

Fix a translation through the API — only the locales in the payload change, and
`"fr": null` removes one:

```bash
curl -X PATCH -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
     -d '{"translations":{"fr":{"title":"Titre","content":"Contenu"}}}' \
     https://example.com/api/review/42
```
