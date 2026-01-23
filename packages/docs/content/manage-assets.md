---
title: 'How to manage assets CSS / Javascript in Pushword CMS ?'
h1: 'Managing Assets (css/js)'
publishedAt: '2025-12-21 21:55'
parentPage: themes
toc: true
---

The default installer copy/paste a skeleton for a website stylized with tailwindcss.

To update it, just go in `./assets` and edit `app.js`, `app.css`, directly the [tailwind configuration](https://tailwindcss.com/docs/configuration) or the `webpack.config.js`.

Then run wepback :

```
cd assets

yarn && yarn encore dev
## OR yarn encore production
```

If you want to change the default location for assets, just edit `./config/packages/pushword.yaml` and configure `apps.0.assets` (#[eg](https://github.com/Pushword/Pushword/blob/main/packages/skeleton/config/packages/pushword.yaml#L31))

## Automatic Tailwind Update on page update

If you use tailwind class inside a page content, by default the command `yarn encore production` is runned when you update a page.

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