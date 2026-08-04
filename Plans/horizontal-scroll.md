# Plan — Horizontal scroll, and its edge fades in CSS

## Goal

Ship the horizontal-scroll carousel js-helper already contains, and replace its
JavaScript edge-fades with the scroll-driven CSS the roadmap linked to.

This is the roadmap's bare `https://x.com/jh3yy/status/1798728699459563905`
(altimood) entry, plus the dead `HorizontalScroll` import above it.

## What the linked post says

jhey, 6 Jun 2024, answering a scroll-driven-animation challenge from Adam
Wathan. Elements in a horizontally scrolling list fade in and out at the edges
of the viewport — **no JavaScript**:

```css
ul { scroll-padding-inline: 200px; }

article {
  animation: highlight;
  animation-timeline: view(inline);
}

@keyframes highlight {
  entry 0%, exit 100% { opacity: 0; }
  entry 100%, exit 0% { opacity: 1; }
}
```

The insight in the thread is `scroll-padding-inline`: it is what makes the
`entry`/`exit` ranges line up with the visible edge instead of the scrollport
edge. Without it the fade fires in the wrong place — the thing everyone else got
stuck on.

## Current state (baseline)

- **`packages/js-helper/src/HorizontalScroll.js` exists and is not shipped.**
  The import in `app.js:16-17` is commented out, so the class is in the repo,
  in the npm package, and in nobody's bundle. It handles wheel-to-horizontal
  scroll, prev/next buttons with disabled states, and a touch-device bypass. Its
  usage is documented only as a commented-out CodePen demo at the bottom of the
  file.
- **`packages/js-helper/src/ScrollEnhancer.js` fades edges in JavaScript.** It
  injects `bg-gradient-to-t from-white to-transparent` divs
  (`ScrollEnhancer.js:11-13`) — exactly what the CSS above replaces. It is
  marked "Demo in Draft" and is also not wired into `app.js`.
- Neither has tests. `ShowMore.js` and `variantLinks.js` are the shape to follow:
  a small module, an `init*()` export, wired once in `app.js`, with a
  `*.test.js` beside it.

## Steps

1. **Decide whether this ships at all.** Two unwired draft modules is the current
   answer being "no". If a real site (altimood) needs it, wire it; otherwise
   delete both and close the roadmap entry — dead code in a published npm
   package is worse than a missing feature.
2. **Fades in CSS.** Add the `animation-timeline: view(inline)` rule to the
   js-helper stylesheet, gated on `@supports (animation-timeline: view())`.
   `ScrollEnhancer.js`'s gradient injection then becomes the fallback for
   browsers without it — or is dropped, if the fallback is judged not worth
   carrying.
3. **`prefers-reduced-motion`.** A fade tied to scroll position is motion; the
   public templates already respect the media query (`motion-reduce:` classes in
   `card.html.twig`), so this must too.
4. **Wire `HorizontalScroll`** the way `initShowMore()` / `initVariantLinks()`
   are: an exported init, called once in `app.js`, idempotent under `DOMChanged`.
   Its current API is a constructor called from inline `onclick` in the demo —
   that needs to become a delegated listener before it is shipped.
5. **A Twig component**, so content can use it: the carousel is only useful if
   `pages_list` / `gallery` can render into it. Decide whether it is a `view:`
   option on those, or its own component.
6. **Tests + demo page.** `HorizontalScroll.test.js` alongside
   `ShowMore.test.js`, and a kitchen-sink entry so the behaviour is visible.

## Verification

- Wheel-scroll, buttons, and touch swipe on the dev-app kitchen sink.
- Fades correct at both ends, including the first and last item — the
  `scroll-padding-inline` value is what gets this wrong.
- Chrome (has `animation-timeline`) and Firefox (check current support) both
  legible; no layout shift when the feature is unsupported.
- `prefers-reduced-motion: reduce` disables the fade animation.
