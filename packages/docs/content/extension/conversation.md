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
