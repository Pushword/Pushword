---
title: 'View Transitions'
h1: 'View Transitions<br> <small>Animate navigations without shipping a router</small>'
publishedAt: '2026-08-01 12:00'
toc: true
---

Pushword animates page-to-page navigation with the native [View Transition API](https://developer.mozilla.org/en-US/docs/Web/API/View_Transition_API) — no JavaScript router. Browsers without support navigate normally.

## What ships

Three pieces, all in the default theme:

- **`base.html.twig`** inlines `@view-transition{navigation:auto}` in its `<style>` block — inline because both the outgoing and incoming documents must parse it before the navigation commits. Every template, error and login pages included, extends `base.html.twig`; a page missing the rule breaks the transition in both directions.
- **`utility.css`** (`pushword/js-helper`) names two elements and animates the content zone.
- **`variantLinks.js`** runs its same-document swap inside `document.startViewTransition()`, so [variant pages](/variant-pages) animate with the same CSS.

## Getting it on an existing site

The feature ships through **two channels**, and `composer update` only moves one of them:

| Channel | Carries | Updated by |
| ------- | ------- | ---------- |
| `pushword/core` (Composer) | the `@view-transition` opt-in in `base.html.twig` | `composer update` |
| `@pushword/js-helper` (npm / `github:`) | `view-transition-name` + the keyframes in `utility.css` | `yarn upgrade @pushword/js-helper` |

With only the Composer half you get the plain cross-fade — no pinned navbar, no content
slide. `yarn install` does **not** fix it: a `github:` dependency is pinned to a commit in
`yarn.lock`. Move the pin, then rebuild:

```shell
yarn upgrade @pushword/js-helper && yarn build
```

On a static host, regenerate afterwards; `pw:static` renders through a `prod` kernel, so
clear its Twig cache first:

```shell
rm -rf var/cache/prod && php bin/console pw:static {host}
```

If the dynamic URL has `@view-transition` in its inline `<style>` and the static one does
not, the export is stale.

## Naming

Two elements get a name:

| Element                | Name         | Effect                                        |
| ---------------------- | ------------ | --------------------------------------------- |
| `#navbar`              | `pw-navbar`  | Own transition group — holds position         |
| `[data-variant-zone]`  | `pw-content` | Slides up and fades in (`pw-content-in/out`)  |

Everything else rides the default `root` cross-fade.

## Opting out or overriding

The rule sits in its own Twig block. Empty it to disable transitions for a theme:

```twig
{% block view_transition %}{% endblock %}
```

To change the animation, redefine the keyframes in your own stylesheet — it loads after `utility.css`:

```css
::view-transition-new(pw-content) {
  animation: my-slide 300ms ease-out both;
}
```

To name more elements, do it in CSS, not in markup — see the rule below.

### Naming rules

**A `view-transition-name` used twice on one page silently aborts the whole page's transition.** Twig loops are the danger zone (`content_part` in `_content.html.twig`, `cardList`, `pages_list`). Never write:

```twig
{# WRONG — every card gets the same name, transitions stop working site-wide #}
{% for page in pages %}
  <article style="view-transition-name: card">…</article>
{% endfor %}
```

Rules of thumb:

- Only name **singletons** — one per page, guaranteed. `#navbar` is an id; `[data-variant-zone]` is emitted once by `_content.html.twig`.
- Prefer selectors that cannot repeat (`#id`) over element or class selectors. A theme with two `<footer>` elements would silently kill transitions with `footer { view-transition-name: … }`.
- If you must name items in a loop, generate a unique name per item (`view-transition-name: card-{{ page.id }}`) and accept that you own the uniqueness.
- Names are prefixed `pw-` in core so a host theme's own names never collide.

Tailwind utilities cannot reach `::view-transition-old()` / `::view-transition-new()` (outside the document tree): write plain CSS.

## Reduced motion

The names and transforms sit inside `@media (prefers-reduced-motion: no-preference)`; reduced-motion visitors get only the cross-fade. Keep your own animations inside the same query.

## Same-document swaps

The same CSS drives partial swaps, so the animation is written once:

- **Variant links** — `variantLinks.js` wraps its content-zone swap in `startViewTransition()`. Nothing to configure.
- **htmx** — if your theme loads htmx, set `htmx.config.globalViewTransitions = true` (htmx 2) / `htmx.config.transitions = true` (htmx 4), or opt in per swap with `hx-swap="innerHTML transition:true"`. The `pw-content` keyframes apply unchanged.

Both paths animate through `::view-transition-old(pw-content)` / `::view-transition-new(pw-content)`, exactly like a full navigation.

## Cost, and why static hosting suits it

The outgoing snapshot stays on screen **until the incoming document can paint**, so server time shows as a pause. [Static export](/extension/static-generator) and [page-cache](/extension/page-cache) suit it; on an uncached dynamic site with a high time-to-first-byte, add [speculation rules](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/script/type/speculationrules) to prefetch or prerender.

## Browser support

Cross-document transitions ship in Chrome/Edge 126+ and Safari 18.2+. Firefox has not enabled them by default yet. Same-document transitions (`document.startViewTransition`, used by variant links and htmx) have wider support.

Unsupported browsers ignore the rule; there is no fallback to write.
