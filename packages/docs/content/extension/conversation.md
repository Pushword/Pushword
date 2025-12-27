---
title: 'Conversation: Add Comment, Newsletter Form or Contact For'
h1: Conversation
toc: true
filter_twig: 0
parent: extensions
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
# =
<div data-live="{{ path('pushword_conversation', {'type': 'newsletter', 'referring': 'newsletter-'~page.slug, 'host': page.host}) }}"></div>

# Render form in Controller
{{ render(controller('Pushword\\Conversation\\Controller\\ConversationFormController::show')) }}

# Or add a button to click before loading block
<button data-src-live="{{ path('pushword_conversation', {'type': 'newsletter', 'referring': 'nslttr-'~page.slug, 'host': page.host}) }}" class="btn btn-primary">Register</button>

# Advanced usage
<p>This is an invitation to <button data-src-live="..." data-target="parent">register</button></p>
```

Activate the `data-live` element with [@pushword/js-helper](https://yarnpkg.com/package/@pushword/js-helper) :

````
import { liveForm } from "@pushword/js-helper/src/helpers";

// on dom changed and on page loaded :
liveBlock();
```

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

Per default, there is 3 form types : `newsletter`, `message` and `multiStepMessage`.

Add a new class in bundle config `pushword_conversation.conversation_form.myNewType: myNewFormClass` or at the app level config `pushword.apps[...].conversation_form: [...]`

## Flat sync integration

When the [Flat extension](/extension/flat) is enabled, every `pw:flat:sync` run also synchronizes
conversation messages with a CSV file stored at `content/<host>/conversation.csv`.

- **Export** : each message is written with its core fields (content, author, tags, dates, …) and one column per custom property.
- **Import** : editing the CSV lets you re-import messages, including any custom properties (arrays are encoded as JSON in their dedicated column).

This allows you to backup or edit conversations alongside pages and medias without needing a database access.

### CLI helpers

```bash
# Auto-detect import vs export (or force with --force=import|export|sync)
php bin/console pw:message:flat [host] [--force=sync]

# Import an external CSV without touching local files
php bin/console pw:message:import path/to/conversation.csv [--host=example.com]
```

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

### Display translated reviews

Translations are automatically displayed based on the current page locale. The `review.html.twig` template uses `page.locale` (or `app.request.locale` as fallback) to show the appropriate translation.

If no translation exists for the requested locale, the original content is displayed.

### Monthly usage tracking

Character usage is tracked per service per month in the `translation_usage` database table. When a service exceeds its configured limit, the system automatically falls back to the next available service.

To check current usage:

```bash
php bin/console dbal:run-sql "SELECT * FROM translation_usage"
```
