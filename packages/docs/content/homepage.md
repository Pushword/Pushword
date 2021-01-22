---
title: Pushword - Modern CMS to build rapidly Websites (powered by Symfony)
h1:
    <span class="block mt-6 leading-relaxed">Build modern <em class="font-light">Content First</em> websites rapidly
    <br> <span class="text-primary dark:text-white">Manage and maintain it as quickly</span></span>
name: Pushword
template: /page/homepage.html.twig
prose: "max-w-screen-sm prose-sm md:prose-lg mx-auto p-3 prose dark:prose-light"
---

<div class="max-w-screen-sm mx-auto" markdown=1>

Puswhord is a PHP <strong>CMS</strong> to <strong>rapidly</strong> create, manage and maintain <strong>extandable Website(s)</strong>.

It make it easy to create amazing <strong>searchable</strong> websites findable on <strong>google</strong>.

Bonus, managing <strong>multi-site</strong>, <strong>internationalization</strong> and <strong>page vesioning</strong> is so simple.

<strong>Want a demo ?</strong> This website is built with **Pushword** with flat file management !

See [how to install and test Pushword in less than one minute](/installation)...

Or look at the <strong>detailled features</strong> :

</div>

<!-- next: show a preview there -->

{% apply unprose %}

<div class="grid max-w-screen-sm grid-cols-2 gap-4 p-3 mx-auto md:grid-cols-4 xl:grid-cols-6 md:max-w-screen-2xl">
    <div class="col-span-2">
        <!-- Edit -->
        <div class="px-3 py-6 mb-6 rounded-lg shadow-lg bg-green-50">
            <h2 class="flex mb-6 text-xl font-medium">
                <div class="flex-shrink-0">
                    <div class="flex items-center justify-center w-10 h-10 mt-2 text-white bg-green-500 rounded-full">{{
                        svg('tools') }}</div>
                </div>
                <div class="ml-3 text-green-500">Easy to install<br><small>Run it in a few seconds</small></div>
            </h2>
            <div class="prose md:prose-lg">
                <p>Pushword run on a classic up to date <strong>PHP</strong> environnement (and Composer). You have this on your
                    machine or just a cheap shared host ? So you are able to install it in a few seconds.</p>
                <p><a href="/installation">Learn more about requirements and installation.</a></p>
                <p>By default, it works without dirty work. It looks <strong>so simple</strong>. But don't be wrong, you
                    can do amazing custom thing with it !</p>
                <p class="text-sm font-light text-center text-green-500"> PHP 7 // <strong style="color: rgba(16, 185, 129, var(--tw-text-opacity));">PHP 8</strong> // Symfony5</p>
            </div>
        </div>
        <div class="px-3 py-6 mb-6 rounded-lg shadow-lg bg-indigo-50">
            <h2 class="flex mb-6 text-xl font-medium">
                <div class="flex-shrink-0">
                    <div class="flex items-center justify-center w-10 h-10 mt-2 text-white bg-indigo-500 rounded-full">
                        {{ svg('wave-square') }}</div>
                </div>
                <div class="ml-3 text-indigo-500">Extendable<br><small>Look ! It's a symfony application under the hood</small>
                </div>
            </h2>
            <div class="prose md:prose-lg"">
                <p>To create <strong>Multiple sites</strong> with <strong>multiple languages</strong> (i18n) and
                    managing them on <strong>multiple domains</strong> you don't need to extend the core. <strong>Simple
                        site</strong> and <strong>complex content network</strong> can be managed easily with Pushword
                    without extension.</p>
                <p>You want a <strong>blog</strong> or a <strong>documentation website</strong> ? Just install Pushword
                    and play.</p>
                <p>Want another feature ? Look at the <a href="/extensions">{{ svg('puzzle-piece')
                        }}&nbsp;extensions</a>.</p>
                <p>Not finding the one you want ? Pushword is built as a <strong>symfony bundle</strong> so just extend
                    your research to them or find an expert developper to make your wish reality.</p>
            </div>
        </div>
    </div>
    <div class="col-span-2">
        <div class="px-3 py-6 mb-6 rounded-lg shadow-lg bg-blue-50">
            <h2 class="flex mb-6 text-xl font-medium">
                <div class="flex-shrink-0">
                    <div class="flex items-center justify-center w-10 h-10 mt-2 text-white bg-blue-500 rounded-full">
                        {{ svg('feather-alt') }}
                    </div>
                </div>
                <div class="ml-3 text-blue-500">Just Write<br><small>Are you more Flat-file CMS or Full Featured Admin
                        ?</small></div>
            </h2>
            <div class="prose md:prose-lg"">
                <p>Pushword offers the two ways to manage a site : a <strong>simple, functionnable and efficient default
                        Admin</strong>, if you come from Wordpress, you will find your way easily or a <strong>powerfull
                        flat-file CMS</strong>, you will be able to edit your content or your template files from where
                    you want (nextcloud folder, custom editor, git compatible...).</p>
                <p>Default editor use <strong>Markdown/Html</strong> with extended <a href="/editor">features</a>
                    (video, responsive image, encrypted link...).</p>
                <p>{{ svg('code-branch') }} A block editor is planned and will be released soon.</p>
            </div>
        </div>
        <div class="px-3 py-6 mb-6 rounded-lg shadow-lg bg-red-50">
            <h2 class="flex mb-6 text-xl font-medium">
                <div class="flex-shrink-0">
                    <div class="flex items-center justify-center w-10 h-10 pt-1 mt-2 text-white bg-red-500 rounded-full">
                        {{ svg('paint-roller') }}
                    </div>
                </div>
                <div class="ml-3 text-red-500">Be unique : theme it quickly<br><small>Do you know Tailwind CSS and Twig ?</small>
                </div>
            </h2>
            <div class="prose md:prose-lg"">
                <p>Thanks to <strong>Tailwind CSS</strong> and <strong>Twig</strong>, you will be
                    able to customize the default theme rapidly if you master html and css.</p>
                <p>Maybe you will prefer rebuild your own custom theme. Do as you wish, you use Pushword.</p>
                <p>{{ svg('eye') }} Want to see how easy it is ? See this documentation website {{ link('assets',
                    'https://github.com/Pushword/Pushword/tree/main/packages/docs') }} and {{ link('template files',
                    'https://github.com/Pushword/Pushword/tree/main/packages/skeleton/templates/pushword.piedweb.com') }}.</p>
            </div>
        </div>
    </div>
    <div class="flex flex-col items-start col-span-2 xl:flex-col md:col-span-4 xl:col-span-2 md:flex-row xl:col-start-auto md:space-x-3 xl:space-x-0">
        <div class="px-3 py-6 mb-6 rounded-lg shadow-lg bg-pink-50">
            <h2 class="flex mb-6 text-xl font-medium">
                <div class="flex-shrink-0">
                    <div class="flex items-center justify-center w-10 h-10 mt-2 text-white bg-pink-500 rounded-full">
                        {{ svg('star') }}
                    </div>
                </div>
                <div class="ml-3 text-pink-500">Searchable website<br><small>Want to be found on google ?</small>
                </div>
            </h2>
            <div class="prose md:prose-lg"">
                <p>Pushword was first crafted by a seo and developper guy. Being on the first page of search result matters !</p>
                <p>So, of course, Pushword manage <strong>title</strong>, <strong>h1</strong>, <strong>description</strong>, <strong>nice url</strong>.</p>
                <p>But discover more SEO feature like <strong>health checker</strong> (dead links checker), <strong>internal links improver</strong> (suggest links to add in your content) and more...</p>
                <p>Woring about speed ? If default installation is not fast enough for you, you will fall in love with the <a href="/extension/static">{{ svg('bolt') }} Static Website Generator</a>.</p>
            </div>
        </div>
        <div class="px-3 py-6 mb-6 rounded-lg shadow-lg bg-yellow-50 md:-mt-24 xl:mt-0">
            <h2 class="flex mb-6 text-xl font-medium">
                <div class="flex-shrink-0">
                    <div class="flex items-center justify-center w-10 h-10 pt-1 mt-2 text-white bg-yellow-500 rounded-full">
                        {{ svg('gem') }}
                    </div>
                </div>
                <div class="ml-3 text-yellow-500">Design to last<br><small>Do you want to rebuild a new fancy website each year
                        ?</small>
                </div>
            </h2>
            <div class="prose md:prose-lg">
                <p>Pushword is crafted to last. Source code is <strong>hight quality</strong>, <strong>open source</strong> and <strong>well tested</strong>.</p>
                <p>Bringing a new feature or refactor your code will be painless. Thanks to <strong>symfony best practices</strong>, it will last in the time.</p>
            </div>
        </div>
    </div>
</div>
{% endapply %}

Time to [read the docs](/installation) or maybe have a look to the #[{{ svg('github') }} source code](https://github.com/Pushword/pushword).
