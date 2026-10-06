---
title: 'Variant Pages'
h1: 'Variant Pages<br> <small>Consolidate near-duplicate pages onto a master for SEO</small>'
publishedAt: '2026-06-04 12:00'
toc: true
---

Near-duplicate pages — one product sold by several vendors, a stay declined per partner, white-label landing pages — cannibalise each other in search. Variant pages keep the per-page content **without** the duplicate-content penalty.

## The model

A page can declare itself a **variant of a master page**:

- `Page::$variantOf` (ManyToOne) / `Page::$variants` (OneToMany) — one master, many variants, a single flat level (a variant cannot itself be a master). `Page::isVariant()` tells them apart.
- A variant is a real, server-rendered page with its own URL and **full independent content**. It is *not* served under the master's URL.

## SEO behaviour (guaranteed, no JS required)

- **Canonical → master.** A variant emits `<link rel="canonical" href="{master}">` and no `hreflang` cluster. No `noindex`: combined with the canonical it sends contradictory signals.
- **Link rewriting.** Every internal link to a variant is rewritten by the `HtmlVariantLink` filter to:

  ```html
  <a href="{master-url}" data-variant="{variant-url}">…</a>
  ```

  Crawlers and visitors without JS follow the master; `data-variant` is a hook for the optional JS layer.
- **Exclusions.** `PageRepository::getIndexablePagesQuery()` drops variants (`andNotVariant()`) from the **sitemap**, **feed** and **internal search**. Content lists keep them — a catalogue still shows the variant card, with its link rewritten.

`customCanonical`, a per-page canonical override, works on any page independently.

## Where the filter sits

```
ShowMore → LinkCollector → Markdown → Typography → HtmlRedirectFromLink → HtmlLinkMultisite → HtmlUnpublishedLink → HtmlObfuscateLink → HtmlVariantLink → Extended
```

It runs last among the link filters, so nothing rebuilds the `<a>` and drops `data-variant`.

## Progressive enhancement (optional, `pushword/js-helper`)

`variantLinks.js` (`pushword/js-helper`, wired in `app.js`) intercepts the click, fetches the variant, swaps its content zone in place, pushes the variant URL, then dispatches `DOMChanged` so other components can re-initialise. Sites with their own JS (htmx, Alpine) can bind `data-variant` themselves.

The zone selector defaults to `[data-variant-zone], main, #content`; override it with `initVariantLinks({ zone: '#my-zone' })`. The default `page/_content.html.twig` already marks `data-variant-zone`; custom themes should too.

The swap runs inside `document.startViewTransition()`, so it animates with the same CSS as a full navigation — see [view transitions](/view-transitions).

## Reversibility (promote / demote)

**Promote to master** (admin) turns a variant into the master; the former master and the other variants re-point to it (`VariantManager::promote()`). Deleting a master promotes one of its variants.

## Editing

On the page form, the **Variant** panel exposes:

- *Variant of (master page)* — pick the master (only non-variant pages on the same host are offered).
- *Canonical URL (override)* — force the canonical independently of the variant relation.
