---
title: 'How to manage assets CSS / Javascript in Pushword CMS ?'
h1: 'Managing Assets (css/js)'
editMessage: 'Imported via pw:flat:sync from manage-assets.md'
publishedAt: '2025-12-21 21:55'
parentPage: themes
toc: true
revision: 608434c860c8c6e116cb3df1af02070eb1f95a3c # read only
---

The installer copies a `package.json` and a `vite.config.js` that build the Tailwind
theme of `@pushword/js-helper` (`src/app.js` and `src/app.css`, with Tailwind v4's
CSS-based configuration). To customize it, point the `input` entries of
`vite.config.js` at your own files, then build:

```bash
npm install && npm run build
# or rebuild on every change:
npm run watch
```

The files each page loads are set per app under `assets` in
`config/packages/pushword.yaml`:

```yaml
pushword:
  apps:
    - hosts: [example.com]
      assets:
        stylesheets: ['assets/tw.css']
        javascripts: ['assets/app.js']
        # also: vite_stylesheets, vite_javascripts (Vite entries), favicon
```

## Tailwind content sources

Tailwind's automatic detection skips whatever `.gitignore` covers, so it never
scans the bundle templates in `vendor/pushword/`. The `app.css` of
`@pushword/js-helper` declares them with an explicit `@source`, which does reach a
gitignored path:

```css
@source "./../../../../vendor/pushword/*/src/templates/**/*.twig";
```

Build your stylesheet from that file (the default `vite.config.js` does) and
bundle templates are covered. Write your own entry point instead and you must
carry the line over, relative to your own CSS file — otherwise every class a
bundle template uses (the newsletter form, the conversation form, the video
component) is purged and that markup renders unstyled.

**An `@source` glob has to end on a filename**: `vendor/pushword/**/templates/`
silently matches nothing. A plain directory without a glob (`@source "templates";`)
is walked whole.

## Automatic Tailwind Update on page update

So that Tailwind classes used inside page content get built, Pushword (in the `prod` environment) caches each saved page's content and runs `npm run build` once at the end of the request or console command. A bulk import such as `pw:flat:sync` therefore builds once after all pages have been saved.

Builds are locked per project: concurrent saves share a pending build, and content saved during a running build is included in a subsequent build. Console commands wait for the build to finish; HTTP builds run after the response has been sent.

The last build's output is in `var/log/lastTailwindGeneration`. If `npm` is not found,
set its path:

```yaml
pushword:
  path_to_bin: /home/username/bin:/opt/alt/alt-nodejs16/root/usr/bin/
```

To disable it:

```yaml
pushword:
  tailwind_generator: false
```
