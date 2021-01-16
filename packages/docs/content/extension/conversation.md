---
title: 'Conversation: Add Comment, Newsletter Form or Contact For'
h1: Conversation
toc: true
twig: 0
parent: extensions
---

Extend your Pushword website with **comments**, a **contact** form or just an **user input**.

## Install

Via #[Packagist](https://packagist.org/packages/pushword/conversation) :

```
# Get the Bundle
composer require pushword/conversation

# Add the route to your Routes:
conversation:
    resource: '@PushwordConversationBundle/Resources/config/routes/conversation.yaml'

# or do it in 1 command line
# $ sed -i '1s/^/admin:\n    resource: "@PushwordConversationBundle\/Resources\/config\/routes\/conversation.yaml"\n/' config/routes.yaml
```

Update database

```
php bin/console doctrine:schema:update --force
```

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
<button src-data-live="{{ path('pushword_conversation', {'type': 'newsletter', 'referring': 'nslttr-'~page.slug, 'host': page.host}) }}" class="btn btn-primary">Register</button>
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

Configure the bundle (`piedweb_conversation.notification_email_to`) and programm a cron :

```
bin/console pushword:conversation:notify
```

## Customization

## Small rendering customization

By overriding `@PushwordConversation/conversation/conversation.html.twig`
(or `'@PushwordConversation/conversation/'.$type.'Step'.$step.'.html.twig`
or `'@PushwordConversation/conversation/'.$type.$referring.'Step'.$step.'.html.twig`).

## Create a new form

Per default, there is 3 form types : `newsletter`, `message` and `multiStepMessage`.

Add a new class in config `piedweb_conversation.form.myNewType: myNewFormClass`.
