---
title: 'Hide Links to Unpublished Pages'
h1: 'Unpublished Links<br> <small>Hide draft targets from visitors, restore them for editors</small>'
publishedAt: '2026-05-28 12:00'
toc: true
---

A page can be linked from your content before its target is published. Pushword routes don't 404 unpublished pages — visiting them by URL still renders the draft — so a raw `<a href>` to a draft slug leaks the URL to anonymous visitors. The `HtmlUnpublishedLink` filter rewrites those links at render time.

## What it does

For every `<a href="...">text</a>` whose target is a `Page` that exists but is not yet published (`publishedAt IS NULL` or in the future), the filter emits:

```html
<span title="Page en cours de publication" data-status="unpublished" data-href="/original">text</span>
```

- The visible text stays.
- The hyperlink is gone — anonymous visitors can no longer click through.
- The original href is kept in `data-href` so JS can put the `<a>` back for editors.
- The `title` tooltip helps debugging.

External links, anchors, `mailto:`, `tel:`, and links resolving to known *published* pages are left untouched.

## Where it sits in the filter chain

```
ShowMore → LinkCollector → Markdown → HtmlLinkMultisite → HtmlUnpublishedLink → HtmlObfuscateLink → Extended
```

It runs *after* `HtmlLinkMultisite` (links are already resolved to absolute URLs cross-host) and *before* `HtmlObfuscateLink`. Obfuscated links pointing to drafts are seen as plain `<a href>` and get masked — no need to decode `data-rot`.

## Restoring links for logged-in editors

The shipped `app.js` bundle includes a small restorer that rewrites each `<span data-status="unpublished" data-href>` back into an `<a>` when the `pw_auth=1` cookie is present. Restored links get `data-unpublished="1"` and a `0.6` opacity so editors can spot drafts at a glance.

This avoids per-user HTML cache invalidation: anonymous and authenticated visitors get the exact same HTML.

`pw_auth` is the editor-only client-side hint core already sets on login and clears on logout (see [Page Cache](/extension/page-cache)); reading it costs no request, so an anonymous visitor makes no network call at all and the restore also survives a prerender. Only `ROLE_EDITOR` carries the cookie, where the probe answered to any fully authenticated user of the firewall covering it — non-editor accounts sharing that firewall no longer get draft links back.

`GET /_pushword/auth-check` (204 = authenticated, 401 = anonymous) is the probe the restorer used before, kept for bundles built earlier. It answers 401 to anonymous visitors and must keep doing so: those bundles key off `response.ok`.

## Pairing with `pw:page-scan`

The scanner used to flag every link to an unpublished target as an error. Now those links are handled gracefully at render time, so the check is **opt-in**:

```bash
php bin/console pw:page-scan                       # quiet about unpublished targets
php bin/console pw:page-scan --check-unpublished   # list every link pointing to a draft
```

Run with `--check-unpublished` when you want a pre-publication audit (e.g. "what would change once I publish this draft?").

## Static generator caveat

The HTML built by `pw:static` is frozen at generation time. If page A links to page B while B is a draft, A's generated HTML will contain the `<span>` placeholder. When B later gets published, A is **not** automatically rebuilt — you need to re-run the static generation for A (or any wholesale rebuild) for the `<a>` to come back in the static output. Editors browsing the live app are not affected.
