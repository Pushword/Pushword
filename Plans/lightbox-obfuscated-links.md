# Plan — Lightbox on obfuscated links

## Goal

Make the lightbox work again for galleries, videos and standalone content images.

Three roadmap entries — "video in lightbox", "simple image self linked",
"`convertImageLinkToWebPLink()` is not working anymore" — are one root cause.
Fixing it decides the open question in the same entry: keep glightbox, or roll
back to fslightbox.

## Current state (baseline)

`link()` obfuscates by default (`packages/core/src/Service/LinkProvider.php:52`,
`:83-88`). A gallery item and a video thumbnail therefore render as a **`<span>`
carrying no `href`**:

```html
<span class="glightbox" data-gallery="…" data-dwl="…webp" data-rot="…">…</span>
```

- `packages/core/src/templates/component/images_gallery.html.twig:54-56`
- `packages/core/src/templates/component/video.html.twig:19`

Against that markup:

1. **glightbox binds nothing usable.** `new Glightbox()` runs at
   `packages/js-helper/src/app.js:41`, before any un-cloaking. It attaches a click
   handler to `.glightbox`, calls `preventDefault()`, and has no `href` to open.
2. **`uncloakLinks()` only converts on interaction.**
   `packages/js-helper/src/helpers.js:414-417` defaults
   `onClickMouseoverOrTouchstart = true`, so the `<span>` becomes an `<a>` on
   click/mouseover/touchstart (`:468-492`) — the same click glightbox is already
   handling. Two listeners race on one event.
3. **`convertImageLinkToWebPLink()` queries `a[data-dwl]`** (`helpers.js:566-576`)
   but `data-dwl` sits on the span until step 2 has run
   (`packages/core/src/Twig/MediaExtension.php:81`). It only ever matches through
   the deferred `DOMChanged` pass that `uncloakLinks` fires
   (`helpers.js:453-457`) — which is why it looks broken rather than dead.
4. **A standalone image is not clickable at all.**
   `packages/core/src/templates/component/image.html.twig` never wraps its
   `<picture>` in a link; only the gallery does. So `![alt](img)` in Markdown
   renders a non-zoomable image — the "simple image self linked" entry.

## Decision to take first

Obfuscation and a lightbox want opposite things: one hides the `href` from
robots, the other needs it at bind time. Three ways out, pick one before coding:

- **A. Don't obfuscate media links.** A link to `/media/…jpg` carries no crawl
  budget worth hiding — it is not a page. Pass `obfuscate: false` in the gallery
  and video components, and everything below falls out: glightbox binds real
  anchors, `convertImageLinkToWebPLink()` finds them on the first pass, no race.
  Cheapest, and the one to justify against first.
- **B. Un-cloak eagerly for lightbox links only.** Keep obfuscation everywhere
  else; call `uncloakLinks('data-rot', false)` (the convert-all branch) for
  `.glightbox` before instantiating the lightbox. Keeps the robots story intact,
  costs an extra DOM pass and keeps the ordering constraint alive.
- **C. Drive the lightbox from `data-rot` directly.** Most control, most code,
  and it makes the lightbox library harder to swap. Only if A and B are refused.

## Steps

1. **Decide A/B/C** above. The rest assumes A or B.
2. **Fix the ordering in `app.js`.** `onDomChanged()` runs
   `convertImageLinkToWebPLink()` *before* `uncloakLinks()`
   (`packages/js-helper/src/app.js:27-29`). Whatever option wins, the WebP swap
   must run after anchors exist. Move it, and instantiate glightbox after the
   first un-cloak pass rather than before (`:41-42`).
3. **Make a content image self-linking.** Add an opt-in to
   `component/image.html.twig` that wraps the `<picture>` in the same
   `class="glightbox" data-dwl` anchor the gallery builds, and have the Markdown
   image path use it (`MediaExtension.php:62`). Decide whether it is on by
   default — it changes every existing site's rendering, so it needs an
   `upgrade/next-release.md` note either way.
4. **Confirm video.** `video.html.twig` already emits `data-type="video"`; with a
   real `href` glightbox should play it inline. Verify before claiming the
   roadmap line.
5. **Then judge glightbox.** If it still misbehaves once it is handed ordinary
   anchors, the library is the problem and the fslightbox rollback is justified.
   Not before — today's symptoms are ours, not the library's.

## Verification

- `packages/js-helper/src/helpers.test.js` covers the helpers; add a case that a
  cloaked `.glightbox` span ends up as an `<a href>` with a WebP `href`.
- Browser check with the `ui-debug` skill: gallery zoom, video play, a Markdown
  image, and a WebP-capable and non-WebP browser.
- `composer console pw:page-scan` on the dev-app, to catch nothing regressing in
  the media checks.
