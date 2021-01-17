---
title: "How to manage assets CSS / Javascript in Pushword CMS ? "
h1: Managing Assets (css/js)
toc: true
parent: themes
---

The default installer copy/paste a skeleton for a website _colored_ with tailwindcss.

To update it juste go in `./assets` and edit `app.js`, `app.css`, directly the [tailwind configuration](https://tailwindcss.com/docs/configuration) or the `webpack.config.js`.

Then run wepback :

```
yarn && yarn encore (dev|production)
```

If you want to change the default location for assets, just edit `./config/packages/pushword.yaml` and configure `apps.0.assets` (#[eg](https://github.com/Pushword/Pushword/blob/main/packages/skeleton/config/packages/pushword.yaml#L31))
