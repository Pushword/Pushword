---
title: 'Hide Links to Unpublished Pages'
h1: 'Unpublished Links<br> <small>Hide draft targets from visitors, restore them for editors</small>'
publishedAt: '2026-05-28 12:00'
toc: true
---

Unpublished pages still render at their URL, so a link to a draft would leak it to visitors. The `HtmlUnpublishedLink` filter rewrites such links at render time.

## What it does

For every `<a href="...">text</a>` whose target is a `Page` that exists but is not yet published (`publishedAt IS NULL` or in the future), the filter emits:

```html
<span title="Page en cours de publication" data-status="unpublished" data-href="/original">text</span>
```

The text stays, the link is gone, and `data-href` lets JS restore it for editors. External links, anchors, `mailto:`, `tel:`, and links resolving to known *published* pages are left untouched.

## Where it sits in the filter chain

```
ShowMore → LinkCollector → Markdown → Typography → HtmlRedirectFromLink → HtmlLinkMultisite → HtmlUnpublishedLink → HtmlObfuscateLink → HtmlVariantLink → Extended
```

It runs after `HtmlLinkMultisite` (cross-host links already resolved) and before `HtmlObfuscateLink`, so it never has to decode `data-rot`.

## Restoring links for logged-in editors

The shipped `app.js` bundle includes a small restorer that rewrites each `<span data-status="unpublished" data-href>` back into an `<a>` when the `pw_auth=1` cookie is present. Restored links get `data-unpublished="1"` and a `0.6` opacity so editors can spot drafts at a glance.

Everyone gets the same HTML, so the page cache needs no per-user variant. `pw_auth` is set on login for `ROLE_EDITOR` only and cleared on logout (see [Page Cache](/extension/page-cache)); reading it costs no request.

`GET /_pushword/auth-check` (204 = authenticated, 401 = anonymous) is the probe older bundles used; it is kept, and must keep answering 401 to anonymous visitors since those bundles key off `response.ok`.

## Pairing with `pw:page-scan`

Since those links are handled at render time, the scanner reports them only on request:

```bash
php bin/console pw:page-scan                       # quiet about unpublished targets
php bin/console pw:page-scan --check-unpublished   # list every link pointing to a draft
```

## Static generator caveat

`pw:static` output is frozen: if A links to draft B, A's static HTML keeps the `<span>` after B is published, until A is regenerated.
