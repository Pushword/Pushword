---
title: 'View Transitions'
h1: 'View Transitions<br> <small>Animate navigations without shipping a router</small>'
publishedAt: '2026-08-01 12:00'
toc: true
---

Pushword animates page-to-page navigation with the native [View Transition API](https://developer.mozilla.org/en-US/docs/Web/API/View_Transition_API) — no JavaScript router, no client-side navigation, no build step. The browser keeps a snapshot of the outgoing page, paints the incoming one, and animates between them. Browsers without support navigate the old way.

## What ships

Three pieces, all in the default theme:

- **`base.html.twig`** inlines the opt-in: `@view-transition{navigation:auto}` inside the existing `<style>` block. It is inlined rather than put in the stylesheet because the rule has to be parsed on **both** the outgoing and the incoming document before the navigation commits. Every template extends `base.html.twig`, so error pages and the login screen are covered too — a page that misses the rule breaks the transition in both directions.
- **`utility.css`** (`pushword/js-helper`) names two elements and animates the content zone.
- **`variantLinks.js`** runs its same-document swap inside `document.startViewTransition()`, so [variant pages](/variant-pages) animate with the same CSS.

## Getting it on an existing site

The feature ships through **two channels**, and `composer update` only moves one of them:

| Channel | Carries | Updated by |
| ------- | ------- | ---------- |
| `pushword/core` (Composer) | the `@view-transition` opt-in in `base.html.twig` | `composer update` |
| `@pushword/js-helper` (npm / `github:`) | `view-transition-name` + the keyframes in `utility.css` | `yarn upgrade @pushword/js-helper` |

With only the Composer half you get the browser's plain cross-fade — no pinned navbar, no
content slide. That reads as "nothing happened". `yarn install` does **not** close the gap:
a `github:`-resolved dependency is pinned to a commit in `yarn.lock`, and `--check-files`
re-installs that same commit. Move the pin explicitly, then rebuild:

```shell
yarn upgrade @pushword/js-helper && yarn build
```

On a statically exported host, regenerate afterwards — `pw:static` renders through its own
`prod`/`debug=false` kernel, so a template change is only picked up once its compiled Twig
cache is gone:

```shell
rm -rf var/cache/prod && php bin/console pw:static {host}
```

To check a deployed host, compare it against the same app served dynamically: if the
dynamic URL has `@view-transition` in its inline `<style>` and the static one does not,
the export is stale, not the code.

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

**A `view-transition-name` used twice on the same page aborts the entire transition.** Not the element's animation — the whole page's. This is the single easiest way to break the feature, and it fails silently: navigation still works, it just stops animating.

That makes Twig loops the danger zone. `_content.html.twig` renders `content_part` in a `{% for %}`, and `cardList` / `pages_list` are loops too. Never write:

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

Tailwind utilities cannot help here: `::view-transition-old()` / `::view-transition-new()` live outside the document tree, so no utility class reaches them. This is hand-written CSS by necessity.

## Reduced motion

The names and the transforms sit inside `@media (prefers-reduced-motion: no-preference)`. Visitors who ask for reduced motion still get the browser's cross-fade — a fade is not motion — but no sliding and no independent groups. Keep any animation you add inside the same media query.

## Same-document swaps

The same CSS drives partial swaps, so the animation is written once:

- **Variant links** — `variantLinks.js` wraps its content-zone swap in `startViewTransition()`. Nothing to configure.
- **htmx** — if your theme loads htmx, set `htmx.config.globalViewTransitions = true`, or opt in per swap with `hx-swap="innerHTML transition:true"`. The `pw-content` keyframes apply unchanged.

Both paths animate through `::view-transition-old(pw-content)` / `::view-transition-new(pw-content)`, exactly like a full navigation.

## Cost, and why static hosting suits it

A cross-document transition holds the outgoing snapshot on screen **until the incoming document is ready to paint**. Server time is therefore visible as a pause before anything moves — the feature rewards fast responses and punishes slow ones.

That makes it a natural fit for [static export](/extension/static-generator) and [page-cache](/extension/page-cache), where the response is a file read. On an uncached dynamic site, measure first: if time-to-first-byte is high, the transition will feel like lag rather than polish. Pair it with [speculation rules](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/script/type/speculationrules) to prefetch or prerender on hover if you need a dynamic site to feel instant.

## Browser support

Cross-document transitions ship in Chrome/Edge 126+ and Safari 18.2+. Firefox has not enabled them by default yet. Same-document transitions (`document.startViewTransition`, used by variant links and htmx) have wider support.

Unsupported browsers ignore the `@view-transition` rule and navigate normally — there is nothing to feature-detect and no fallback to write.
