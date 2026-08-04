# Plan — Fix ScrollEnhancer.js in place

Split out of `Plans/horizontal-scroll.md`, whose own subject — a CSS-only horizontal
scroller — shipped as the `horizontalScroll` view of `pages_list()`. What is left here
is the part that was never about the new component: three live bugs in the module that
altimood, alpescheval and piedweb.com run today.

**Do not delete `packages/js-helper/src/ScrollEnhancer.js`.** It is unused in
`packages/js-helper/src/app.js` — which no site imports — and in production on three.

## The bugs

- **`ScrollEnhancer.js:148` clobbers `window.scrollX`.** The property is `[Replaceable]`,
  so the assignment succeeds and permanently shadows the native scroll position with a
  function. `altimood/assets/app.js:41` and
  `multi-piedweb.com/assets/alpescheval.com/app.js:25` then read `window.scrollX !== 0`
  on the line *after* constructing `ScrollXEnhancer`: the guard is always true and the
  Chrome `#:~:text=` workaround it protects fires on every page load. The clobber is
  deliberate — inline `onclick="scrollX(this, 400)"` needs a global — so fixing it means
  renaming the global and binding listeners instead of relying on inline handlers.
- **`ScrollEnhancer.js:186` compares `dataset.scrolly === 0`**, string against number.
  altimood's navbar sets `data-scrolly="0"` to opt out of the wheel→page-scroll fallback
  (`navbar.html.twig:29`); it has never taken effect.
- **`ScrollEnhancer.js:207` concatenates `dataset.arrowleft + dataset.arrowright`**
  unguarded. A template that omits both injects the string `undefinedundefined` into the
  DOM.

Write the first one into `upgrade/next-release.md`: a site reading `window.scrollX`
after constructing `ScrollXEnhancer` was getting a dead guard, and fixing the module
changes what that line does.

## Then

- **Migrate altimood and multi-piedweb** onto the `horizontalScroll` view, and retire the
  horizontal half of the module. On both sites the arrows are already dead — their
  `arrowX.html.twig` is `data-arrowRight="" data-arrowLeft=""` with the real markup
  commented out, and piedweb's `portfolio.html.twig:4` uses bare attributes — so what they
  actually lose is drag-to-scroll and wheel-hijack, both dropped on purpose. Only
  altimood's navbar still has working arrows. Both repos are outside this one.
- **`enhance-scroll-y` stays.** It is a clipped-text reveal on review cards and mincards,
  not a carousel. `::scroll-button(block-end)` replaces its "⌄" and `overscroll-behavior`
  its wheel chaining, but that is its own change — do not fold it in here.
