---
title: Pushword - Modern CMS to build rapidly Websites (powered by Symfony)
h1: <span class="block mt-6 leading-relaxed">Build modern <em class="font-light">Content First</em> websites rapidly <br> <span class="text-primary dark:text-white">Manage and maintain it as quickly</span></span>
name: Pushword
template: /page/homepage.html.twig
prose: "max-w-screen-lg prose-sm md:prose-lg mx-auto p-3 prose dark:prose-light"
---

{% apply unprose %}

<div class="max-w-screen-lg p-3 mx-auto mb-6 text-xl md:p-6 bg-gradient-to-br from-yellow-500 to-yellow-600 text-yellow-50 text-secondary rounded-xl">
<p class="py-3">Puswhord is a PHP <strong class="text-white">CMS</strong> to <strong class="text-white">rapidly</strong> create, manage and maintain <strong class="text-white">extandable Website(s)</strong>.</p>
<p class="py-3">It’s</p>
<ul class="list-disc list-inside">
<li class="py-2">easily <em>editable</em> via <strong class="text-white">flat files</strong> or <strong class="text-white">full featured admin</strong></li>
<li class="py-2">fully <em>configurable</em>, <em>customizable</em> and <em>extandable</em>. <strong>Configure it</strong> via one config file in <em>yaml</em>, edit the default theme built with <strong class="text-white">Tailwind</strong> in an instant, extend it with <a href="/extension">extension</a></li>
<li class="py-2"><strong>searchable</strong> : be findable on <strong class="text-white">google</strong> and other search engine</li>
<li class="py-2">build on top one of the most popular PHP framework <strong class="text-white">symfony</strong>. And you don’t need to know about it <strong class="text-white">to install Pushword</strong>.</li>
</ul>
<p class="hidden w-24 p-3 mt-3 font-bold text-center transform bg-white rounded-lg shadow-md lg:block lg:-ml-10 -rotate-12 text-primary lg:-mb-3">Bonus</p>
<p class="py-3">With Pushword managing <strong class="text-white">multi-site</strong>, <strong class="text-white">internationalization</strong> and <strong>page vesioning</strong> is so simple.</p>
</div>
{% endapply %}

Want a demo ? This website is built with **Pushword** with flat file management, see the #[{{ svg('github') }} source code](https://github.com/Pushword/Pushword/tree/main/packages/docs).

See [how to install and test Pushword in less than one minute](/installation) :

{{ link('Get started', '/installation', {class: 'block text-center font-bold uppercase mx-auto p-3 text-white rotate-180 rounded-lg bg-primary hover:opacity-75', 'style': 'max-width:200px'})|unprose }}

Or look at the detailled features or directly the #[source code](https://github.com/Pushword/pushword) (monorepo).

{% apply unprose %}

<div class="grid grid-cols-1 gap-4 p-3 mx-auto md:grid-cols-2 xl:grid-cols-4 max-w-screen-2xl">

<!-- Edit -->
<div class="px-3 py-6 mb-6 rounded-lg shadow-lg bg-green-50">
    <h2 class="flex mb-6 text-xl font-medium">
        <div class="flex-shrink-0">
            <div class="flex items-center justify-center w-10 h-10 mt-2 text-white bg-green-500 rounded-full">{{ svg('tools') }}</div>
        </div>
        <div class="ml-3 text-green-500">Easy to install<br><small>Run it in a second</small></div>
    </h2>
    <div class="prose">
        <p>Pushword run on a classic <strong>PHP</strong> environnement (and Composer). You have this on your machine or just a cheap shared host ? So you are able to install it in a few seconds.</p>
        <p>By default, it works without dirty work. It even looks <strong>so simple</strong>. But don't be wrong, you can do amazing custom thing with it !</p>
        <p>You want a <strong>blog</strong> or a <strong>documentation website</strong> ? Just install Pushword and play.</p>
        <p>Want more features ?<br>Look at the <a href="/extensions">{{ svg('puzzle-piece') }} extensions</a>.</p>
        <p><a href="/installation">Learn more about requirements and installation.</a></p>
    </div>

</div>

<!-- Edit -->
<div class="px-3 py-6 mb-6 rounded-lg shadow-lg bg-yellow-50">
    <h2 class="flex mb-6 text-xl font-medium">
        <div class="flex-shrink-0">
            <div class="flex items-center justify-center w-10 h-10 text-white bg-yellow-500 rounded-full">
                {{ svg('pencil-ruler') }}
            </div>
        </div>
        <div class="ml-3 text-yellow-500">Easy to edit<br><small>Are you more Flat-file CMS or Full Featured Admin ?</small></div>
    </h2>
    <div class="prose">
        <p>Pushword offers the two ways to manage a site : a <strong>simple, functionnable and efficient default Admin</strong>, if you come from Wordpress, you will find your way easily or a <strong>powerfull flat-file CMS</strong>, you will be able to edit your content or your template files from where you want (nextcloud folder, custom editor, git compatible...).</p>
        <p>Default editor use <strong>Markdown/Html</strong> with extended <a href="/editor">features</a> (video, responsive image, encrypted link...).</p>
        <p>{{ svg('dot-circle') }} A block editor is planned and will be released soon.</p>
    </div>
</div>
</div>
{% endapply %}

-   By default, Pushword offers you to write content with **Markdown** or directly in **html** (with **Twig** functionnalities avalaible). It's render pretty cleaned source code and if you never use it, it's very easy to learn.
-   **Multi-site**, Multi-language (i18n), Multi-domain or just **one simple website** : both are possible on the same installation
-   **Easily extendable** ([extensions list](/extensions)) or ask a developper what you wish, extend _Pushword_ is simple as writing a symfony bundle.

## **Theme it**

-   Customize the default theme with ease, it's built with **Tailwind CSS** (you never use it ? It's #[amazing](https://tailwindcss.com)).
-   Create new theme extending other or just override default theme, see how it's simple for this website #[assets](https://github.com/Pushword/Pushword/tree/main/packages/docs)/#[template](https://github.com/Pushword/Pushword/tree/main/packages/skeleton/templates/pushword.piedweb.com))
-   Stack : **Twig** / _WhatYouWant_ (**Webpack**/Encore per default, you can use whatever you want and just copy your generated assets inside public folder)

## **Extend** <br><small>Feel **at home** if you ever used Symfony and composer</small>

Symfony upgrade your developpement process : the famous framework come with autowiring, event suscriber, large community and good documentation !

-   Built on top on Symfony and other #[fantastic well maintained packages](https://raw.githubusercontent.com/Pushword/Pushword/main/composer.json)
-   Built as a symfony bundle, **extendable** with symfony bundle
-   **Tested** / **Traits** / **Command**

## Be visible on Search Engine<br> <small>**SEO** : feel like **wikipedia**</small>

-   Title / H1 / Description / Url Rewriting
-   i18n (`link alternate hreflang`) easy way
-   Links Watcher (dead links, redirection, etc.)
-   Links Improver (links suggestion on writing, or automatic adding)
-   Blazing Fast (**static website generator** with dynamic possibilities)

... and more to discover, just [install it in a few seconds](/installation), browse the #[code](https://github.com/Pushword/Pushword) or read the [docs](/installation).
