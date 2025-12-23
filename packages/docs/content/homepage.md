---
title: 'Pushword - CMS to build rapidly Websites (powered by Symfony)'
h1: '<span class="block mt-6 leading-relaxed">Build <em class="font-light">Content First</em> websites rapidly <br> <span class="text-primary dark:text-white">Manage and maintain it as quickly</span></span>'
id: 43
publishedAt: '2025-12-21 21:55'
name: Pushword
template: /page/homepage.html.twig
prose: 'max-w-screen-sm prose-sm md:prose-lg mx-auto p-3 prose dark:prose-light'
---

<div class="flex flex-row max-w-screen-lg mx-auto mb-6">
  <div class="p-3 pr-6 -mt-3">

**Pushword** is a **page-oriented CMS** built on the rock-solid **PHP ecosystem**.

With Pushword, you can **rapidly create, manage, and maintain stunning websites**—and with **AI tools** like _Cursor_, _Claude_, or _Copilot_, taking your content to the next level has never been easier.

**Bonus:** managing **multi-sites**, **internationalization**, and **page versioning** is effortless.

**Curious?** This very website runs on _Pushword_ using flat-file management.

Prefer a **block editor** with a sleek admin dashboard? Just **install an extension**—most are officially maintained to avoid the compatibility headaches common with _WordPress plugins_.

Check out [how to install Pushword easily](/installation) and start customizing it to your needs today!

Or look at the **detailled features** ↓

</div>
<div class="hidden p-3 -mt-3 prose-sm rounded-xs shadow-lg bg-gray-50 dark:bg-gray-900 lg:block" style="width:400px;margin-right:-50px; margin-left:50px">

### Install

<pre><code class="text-sm shell" style="overflow-x: hidden;">composer create-project pushword/new my-project</code></pre>

That's it ! Launch a PHP Server (`cd my-project && php -S 127.0.0.1:8004 -t public/`).

Maybe you will want to change [default configuration](/configuration) or add some [extensions](/extensions).

### Download

If you are not composer friendly, you can download the classic version.

<p class="text-center">{{ link(svg('download', {'class': 'w-6 h-6 inline-block mr-2 dark:fill-white'}) ~ ' Pushword-Classic-1.0.0.zip', '#', {'class': 'font-bold'}) }}
<br><small>{{ svg('exclamation-triangle', {'class': 'w-3 h-3 inline-block mr-1 dark:fill-white'}) }} not yet available</small></p>

Unzip it on a classic Apache/PHP server and play.

</div>
</div>

<div class="absolute hidden transform -right-14 w-96 -top-10 2xl:block 2xl:w-60 rotate-12" style="height:150vh">
  <div class="w-full h-full bg-repeat text-primary-100 heropattern-bubbles-gray-200">
  </div>
</div>

<!-- next: show a preview there -->

<div class="p-3 relative mb-6 shadow-xs bg-gray-50 dark:bg-gray-800 from-gray-50 to-white dark:from-gray-900 dark:to-gray-800">
  <div class="max-w-screen-sm mx-auto">
    <h2 class="pt-12 pb-6 text-4xl">Features<br><small class="text-lg">Create content and publish it on the web smoothly</small></h2>
  </div>

<div class="grid max-w-screen-sm md:max-w-screen-2xl grid-cols-2 gap-4 mx-auto md:px-12 md:grid-cols-4 xl:grid-cols-6  ">
  <div class="col-span-2">
    <!-- Edit -->
    <div class="px-3 py-6 mb-6 rounded-lg dark:bg-gray-900 bg-white shadow dark:text-gray-50">
      <h2 class="flex mb-6 text-xl font-medium">
        <div class="shrink-0">
          <div class="flex items-center justify-center w-10 h-10 mt-2 text-white bg-green-500 rounded-full">{{
            svg('tools') }}</div>
        </div>
        <div class="ml-3 text-green-500">
          Easy to install<br>
          <small>Run it in a few seconds</small>
        </div>
      </h2>
      <div>

Pushword runs on a standard, up-to-date **PHP environment** (with Composer). Have it on your machine or a cheap shared host? You can get started in moments.

[Learn more about requirements and installation](/installation).

By default, it works **out of the box** - clean, simple, and efficient. But don't be fooled: Pushword lets you create **amazing custom sites** when you want.

<p class="text-sm font-light text-center text-green-500"><strong style="color: rgba(16, 185, 129, var(--tw-text-opacity));">PHP 8</strong> // Symfony 7</p>
      </div>
    </div>
    <div class="px-3 py-6 mb-6 rounded-lg dark:bg-gray-900 bg-white shadow dark:text-gray-50">
      <h2 class="flex mb-6 text-xl font-medium">
        <div class="shrink-0">
          <div class="flex items-center justify-center w-10 h-10 mt-2 text-white bg-indigo-500 rounded-full">
            {{ svg('wave-square') }}</div>
        </div>
        <div class="ml-3 text-indigo-500">Extendable<br><small>Look ! It's a symfony application under the hood</small>
        </div>
      </h2>
      <div>

Need **multiple sites**, **multiple languages** (i18n), or **multiple domains**? No need to touch the core. From a simple blog to a complex content network, Pushword handles it all **without extensions**.

Want a **blog** or a **documentation website**? Just install Pushword and start building.

Need more features? Check out the {{ svg('puzzle-piece') }} [extensions](/extensions).

Can't find the one you need? Pushword is built as a Symfony bundle so you can extend it yourself or hire an expert to make your ideas a reality.

</div>
    </div>

  </div>
  <div class="col-span-2">
    <div class="px-3 py-6 mb-6 rounded-lg dark:bg-gray-900 bg-white shadow dark:text-gray-50">
      <h2 class="flex mb-6 text-xl font-medium">
        <div class="shrink-0">
          <div class="flex items-center justify-center w-10 h-10 mt-2 text-white bg-blue-500 rounded-full">
            {{ svg('feather-alt') }}
          </div>
        </div>
        <div class="ml-3 text-blue-500">Just Write with (or without) <strong>AI</strong><br><small>Are you more Flat-file CMS or Full Featured Admin <em>Notion-Like</em>
            ?</small></div>
      </h2>
      <div>

Flat-file CMS or full-featured Notion-like admin? Pushword offers both:

- **Simple, efficient Admin**: If you’re coming from WordPress, you’ll feel right at home. Includes a **[Notion-like block editor](/extension/admin-block-editor)**.
- **Powerful flat-file CMS**: Edit your content or templates anywhere - _Nextcloud Note_, _VSCode_, _Obsidian_, _Git-compatible workflows_…

**Boost your writing with AI without being locked in**: Pushword (_flat file_) works with AI tools like _Cursor_, _Claude_, or _Copilot_, but you're free to choose any provider or switch later.

Generate content, get smart suggestions, and streamline editing **without being tied to a single AI service**.

</div>
    </div>
    <div class="px-3 py-6 mb-6 rounded-lg dark:bg-gray-900 bg-white shadow dark:text-gray-50">
      <h2 class="flex mb-6 text-xl font-medium">
        <div class="shrink-0">
          <div class="flex items-center justify-center w-10 h-10 pt-1 mt-2 text-white bg-red-500 rounded-full">
            {{ svg('paint-roller') }}
          </div>
        </div>
        <div class="ml-3 text-red-500">Be unique : theme it quickly<br><small>Do you know Tailwind CSS and Twig ?</small>
        </div>
      </h2>
      <div>

Customize the default theme rapidly.

Want a completely custom theme? Go ahead—Pushword gives you the freedom to **design your site your way**.

See how easy it is by exploring this [documentation website's assets and templates](https://github.com/Pushword/Pushword/tree/main/packages/docs).

</div>
</div>

  </div>
  <div class="flex flex-col items-start col-span-2 xl:flex-col md:col-span-4 xl:col-span-2 md:flex-row xl:col-start-auto md:space-x-3 xl:space-x-0">
    <div class="px-3 py-6 mb-6 rounded-lg dark:bg-gray-900 bg-white shadow dark:text-gray-50">
      <h2 class="flex mb-6 text-xl font-medium">
        <div class="shrink-0">
          <div class="flex items-center justify-center w-10 h-10 mt-2 text-white bg-pink-500 rounded-full">
            {{ svg('star') }}
          </div>
        </div>
        <div class="ml-3 text-pink-500">Searchable website<br><small>Want to be found on google ?</small>
        </div>
      </h2>
      <div>

Pushword was **crafted by an SEO/GEO and developer consultant**. Being on the AI suggestion or on the first page matters!

Pushword manages titles, H1s, schema, meta tags, nice URLs, and more.

Discover advanced SEO tools:

- **Health checker** (dead link detection)
- **Internal link suggestions**
- And much more

Worried about speed for Google Discover? If default performance isn't enough, you'll love the {{ svg('bolt') }} [Static Website Generator](/extension/static-generator).

</div>
    </div>
    <div class="px-3 py-6 mb-6 rounded-lg md:-mt-24 xl:mt-0 dark:bg-gray-900 bg-white shadow dark:text-gray-50">
      <h2 class="flex mb-6 text-xl font-medium">
        <div class="shrink-0">
          <div class="flex items-center justify-center w-10 h-10 pt-1 mt-2 text-white bg-purple-500 rounded-full">
            {{ svg('gem') }}
          </div>
        </div>
        <div class="ml-3 text-purple-500">Design to last<br><small>Do you rebuild your website every year?</small>
        </div>
      </h2>
      <div>

Pushword is built to **last**.

- **High-quality, open-source code**
- **Well-tested and maintainable**
- **Symfony best practices and static analysis** ensure smooth refactoring and new features

Pushword isn't just a CMS—it's a **long-term solution for your website**.

</div>
    </div>
  </div>

</div>
</div>

<div class="max-w-screen-sm p-3 mx-auto">

<h2 class="text-2xl pt-9">
  <small>Thanks to open source package and their contributors</small><br>
  Pushword CMS is built on top of
</h2>

<ul class="flex flex-row my-6 space-x-6 list-none">
  <li class="text-center"><a href="https://symfony.com"><img src="/media/symfony.svg" alt="Symfony PHP Framework" class="h-16"><small>Symfony</small></a></li>
  <li class="text-center"><a href="https://tailwindcss.com"><img src="/media/tailwind.svg" alt="Tailwind CSS" class="w-16 h-16 mx-auto rounded-full"><small>Tailwind CSS</small></a></li>
  <li class="text-center"><a href="https://codex.so/editor"><img src="/media/editorjs.svg" alt="Editor.js" class="h-16"><small>Editor.js</a></small></li>
  <li class="text-center"><a href="https://sonata-project.org"><img src="/media/sonata.svg" alt="Editor.js" class="w-16 h-16 mx-auto bg-gray-300 rounded-full"><small>Sonata</small></a></li>
</ul>

<div class="pt-3 pb-12 prose dark:prose-light">

And a few more amazon open source ({{ link('dependencies', 'https://github.com/Pushword/Pushword/blob/main/composer.json') }}).

</div>

</div>

<div class="shadow-xs bg-gray-50 -mb-14 dark:bg-gray-800">
  <div class="max-w-screen-sm p-3 py-12 mx-auto prose-sm prose md:prose-lg dark:prose-light">
    <h2 class="font-bold">Next</h2>

Time to [read the docs](/installation) or have a look to the {{ link(svg('github') ~ ' source code', 'https://github.com/Pushword/pushword') }}.

And follow {{ link('@Robind4', 'https://twitter.com/Robind4') }} on twitter or {{ link('github', 'https://github.com/Pushword/pushword') }} to be notified about updates.

  </div>
</div>