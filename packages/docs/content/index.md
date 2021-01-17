---
title: Pushword - Modern CMS to build rapidly Websites (powered by Symfony)
h1: Build modern websites rapidly <br> <span class="text-primary">maintain it as quickly</span>
name: Pushword
template: /page/homepage.html.twig
prose: "max-w-screen-lg prose-lg mx-auto p-3 prose dark:prose-light"
---

<div class="p-3 text-xl bg-yellow-50 text-secondary rounded-xl" markdown=1>

Puswhord is a **PHP** <strong style="color:var(--primary)">CMS</strong> to <strong style="color:var(--primary)">rapidly</strong> create, manage and maintain <strong style="color:var(--primary)">extandable Website(s)</strong>.

It's

-   easily _editable_ via <strong style="color:var(--primary)">flat files</strong> or <strong style="color:var(--primary)">full featured admin</strong>
-   fully _configurable_ and _customizable_ : configure it via one config file in _yaml_ and edit the default theme built with <strong style="color:var(--primary)">Tailwind</strong> in a second
-   **searchable** : be findable on <strong style="color:var(--primary)">google</strong> and other search engine
-   build on top one of the most popular PHP framework <strong style="color:var(--primary)">symfony</strong>. And you don't need to know about it <strong style="color:var(--primary)">to install Pushword</strong>.

<p class="text-center"><strong class="inline-block p-3 -mb-3 rotate-180 bg-yellow-100 rounded shadow-md" style="color:var(--primary)">Bonus</strong> </p>

With Pushword managing <strong style="color:var(--primary)">multi-site</strong> and <strong style="color:var(--primary)">internationalization</strong> is so simple.

 </div>

Want a demo ? This website is built with **Pushword**.

See [how to install and test Pushword in less than one minute](/installation) :

{{ link('Get started', '/installation', {class: 'block text-center font-bold uppercase mx-auto p-3 text-white rotate-180 rounded bg-primary hover:opacity-75', 'style': 'max-width:200px'})|unprose }}

Or look at the detailled features or directly the #[source code](https://github.com/Pushword/pushword) (monorepo).

## **Edition** <br><small>Manage your content efficiently </small>

-   Simple, functionnable and efficient default Admin. If you come from Wordpress, you will find your way easily.
    If you prefer a **flat file cms**, Pushword do that too !
-   By default, Pushword offers you to write content with **Markdown** or directly in **html** (with **Twig** functionnalities avalaible). It's render pretty cleaned source code and if you never use it, it's very easy to learn.
-   **Multi-site**, Multi-language (i18n), Multi-domain or just **one simple website** : both are possible on the same installation
-   **Easily extendable** ([extensions list](/extensions)) or ask a developper what you wish, extend _Pushword_ is simple as writing a symfony bundle.

## **Theme it**

-   Customize the default theme with ease, it's built with **Tailwind CSS** (you never use it ? It's #[amazing](https://tailwindcss.com)).
-   Create new theme extending other or just override default theme, see how it's simple for this website #[assets](https://github.com/Pushword/Pushword/tree/main/packages/docs)/#[template](https://github.com/Pushword/Pushword/tree/main/packages/skeleton/templates/pushword.piedweb.com))
-   Stack : **Twig** / _WhatYouWant_ (**Webpack**/Encore per default, you can use whatever you want and just copy your generated assets inside public folder)

## **Extend** <br><small>Feel **at home** if you ever used Symfony and composer</small>

Symfony upgrade your developpement process : the famous framework come with autowiring, event suscriber, large community and good documentation !

-   Build on top on Symfony and other #[fantastic well maintained packages](https://raw.githubusercontent.com/Pushword/Pushword/main/composer.json)
-   Build as a symfony bundle, **extendable** with symfony bundle
-   **Tested** / **Traits** / **Command**

## Be visible on Search Engine<br> <small>**SEO** : feel like **wikipedia**</small>

-   Title / H1 / Description / Url Rewriting
-   i18n (`link alternate hreflang`) easy way
-   Links Watcher (dead links, redirection, etc.)
-   Links Improver (links suggestion on writing, or automatic adding)
-   Blazing Fast (**static website generator** with dynamic possibilities)

... and more to discover, just [install it in a few seconds](/installation), browse the #[code](https://github.com/Pushword/Pushword) or read the [docs](/installation).
