---
title: Write from Facebook on Pushword CMS
h1: Facebook
toc: true
parent: extensions
---

Write from Facebook on your page managed by Pushword.

## Install

```
composer require pushword/facebook
```

## Usage

For now, this extension just permit to show last post from a page.

```
{{ facebook_last_post('Google') }}
# will return the last post from Google's Facebook Page render via /component/FacebookLastPost.html.twig

{% set fb_last_post_meta_data = facebook_last_post('Google', '') %}
# will return an array
```

### Override default theme

Create a `/component/FacebookLastPost.html.twig` in your app template directory.

## Usage

### Command Line

```
php bin/console pushword:flat:import $host
```

Where $host is facultative.

```

```
