---
title: 'Editor Hidden Super Power - Cheatsheet Pushword'
h1: 'Editor Hidden Super Power<br><small>and Markdown Cheatsheet</small>'
publishedAt: '2025-12-21 21:55'
prose: ' '
raw: true
main_content_filters: twig
---

<div class="flex flex-wrap max-w-5xl">
    <div class="order-2 w-fulloverflow-hidden lg:order-1 lg:w-4/5">
        <div class="p-3 prose dark:prose-light max-w-none">
            <h1>{{ pw(page).h1|raw }}</h1>

After installing the [admin](/extension/admin), you will be able to read this doc directly from your Pushword installation at `/admin/markdown-cheatsheet`.

</div>
    </div>
    <div class="order-3 w-full overflow-hidden lg:order-2 lg:w-1/5">
        <div class="max-w-screen-sm p-2 pt-4 m-2 rounded shadow-md bg-gray-50 dark:border-gray-700 lg:max-w-xs lg:absolute">
            <h3 class="block px-1 mb-3 text-sm font-semibold tracking-wide text-gray-900 uppercase dark:text-gray-100 lg:mb-3 lg:text-xs">Contents</h3>
            <div class="px-1 -ml-6 prose-sm dark:prose-light max-w-none">
                {% apply markdown %}{{ block("cheatsheet_toc", "@PushwordAdmin/markdown_cheatsheet.html.twig") }}{% endapply %}
            </div>
        </div>
    </div>

    <div class="order-4 w-full overflow-hidden lg:w-4/5">
        <div class="max-w-3xl p-3">

            {{ block("cheatsheet_content", "@PushwordAdmin/markdown_cheatsheet.html.twig") }}
        </div>
    </div>

</div>