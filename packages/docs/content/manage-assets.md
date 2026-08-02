---
title: 'How to manage assets CSS / Javascript in Pushword CMS ?'
h1: 'Managing Assets (css/js)'
editMessage: 'Imported via pw:flat:sync from manage-assets.md'
publishedAt: '2025-12-21 21:55'
parentPage: themes
toc: true
revision: 608434c860c8c6e116cb3df1af02070eb1f95a3c # read only
---

The default installer copy/paste a skeleton for a website stylized with Tailwind CSS.

To update it, just edit `app.js`, `app.css`, directly the [tailwind configuration](https://tailwindcss.com/docs/configuration) or the `vite.config.js`.

Then run the build:

```bash
npm install && npm run build
# OR for development with hot reload:
npm run dev
```

If you want to change the default location for assets, just edit `./config/packages/pushword.yaml` and configure `apps.0.assets` (#[eg](https://github.com/Pushword/Pushword/blob/main/packages/dev-app/config/packages/pushword.yaml#L31))

## Tailwind content sources

Bundle templates ship inside `vendor/pushword/`, and Tailwind's automatic
detection never walks in there: it skips whatever your `.gitignore` covers. They
have to be declared, which is what the `app.css` shipped by `@pushword/js-helper`
does — an explicit `@source` **does** reach a gitignored path:

```css
@source "./../../../../vendor/pushword/*/src/templates/**/*.twig";
```

Build your stylesheet from that file (the default `vite.config.js` does) and
bundle templates are covered. Write your own entry point instead and you must
carry the line over, relative to your own CSS file — otherwise every class a
bundle template uses (the newsletter form, the conversation form, the video
component) is purged and that markup renders unstyled.

One trap if you edit these lines: **an `@source` pattern has to end on a
filename.** `vendor/pushword/**/templates/` reads correctly and matches nothing
whatsoever, silently — you only notice because the stylesheet came out smaller.
A plain directory with no glob in it (`@source "templates";`) is walked whole and
is fine; anything with a `*` in it needs to reach files.

## Automatic Tailwind Update on page update

If you use Tailwind classes inside page content, by default the command `npm run build` is run when you update a page.

May be sure this option is working by checking `var/log/lastTailwindGeneration`.

If not working, you may add path to bin in config :

```yaml
pushword:
  path_to_bin: /home/username/bin:/opt/alt/alt-nodejs16/root/usr/bin/
```

To disable it, add in config :

```yaml
pushword:
  tailwind_generator: false
```

Note : the assets built by tailwind can be built after page loaded.