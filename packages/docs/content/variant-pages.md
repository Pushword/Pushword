---
title: 'Variant Pages'
h1: 'Variant Pages<br> <small>Consolidate near-duplicate pages onto a master for SEO</small>'
publishedAt: '2026-06-04 12:00'
toc: true
---

Several pages can share almost the same content with minor variations — the same product sold by several vendors, the same stay declined per partner, campaign / white-label landing pages. To search engines these are near-duplicates that cannibalise each other. Variant pages let you keep the per-page customisation **without** the duplicate-content penalty.

## The model

A page can declare itself a **variant of a master page**:

- `Page::$variantOf` (ManyToOne) / `Page::$variants` (OneToMany) — one master, many variants, a single flat level (a variant cannot itself be a master). `Page::isVariant()` tells them apart.
- A variant is a real, server-rendered page with its own URL and **full independent content**. It is *not* served under the master's URL.

Pushword owns the **decision** (which page is the master) and its **reversibility** — not the SEO measurement (left to Search Console / Matomo).

## SEO behaviour (guaranteed, no JS required)

- **Canonical → master.** A variant emits `<link rel="canonical" href="{master}">` and no `hreflang` cluster (it is not self-canonical). No `noindex` is used — combined with the canonical it sends contradictory signals and risks de-consolidating the master.
- **Link rewriting.** Every internal link to a variant is rewritten by the `HtmlVariantLink` filter to:

  ```html
  <a href="{master-url}" data-variant="{variant-url}">…</a>
  ```

  Crawlers and visitors without JS follow `href` = the master (internal link juice consolidated, the variant kept out of the index). The `data-variant` attribute is a hook for the optional JS layer.
- **Exclusions.** Variants are dropped from the **sitemap**, **internal search** and **menus** via `PageRepository::andNotVariant()` (applied in `getIndexablePagesQuery()`, which backs sitemap, feed and search). They are *not* excluded from content lists / cards — a catalogue keeps the variant card visible, with its link rewritten.

`customCanonical` (a free per-page canonical override) is the generic primitive variants build on; you can set it on any page independently.

## Where the filter sits

```
ShowMore → LinkCollector → Markdown → HtmlLinkMultisite → HtmlUnpublishedLink → HtmlObfuscateLink → HtmlVariantLink → Extended
```

It runs last among the link filters so nothing reconstructs the `<a>` afterwards (which would drop the `data-variant` hook); the route generator already makes the master URL host-aware.

## Progressive enhancement (optional, `pushword/js-helper`)

The core only emits the `data-variant` hook. The opt-in vanilla helper in `pushword/js-helper` (`variantLinks.js`, wired in `app.js`) intercepts the click, fetches the variant page, swaps its content zone in place and pushes the variant URL for shareability, then dispatches a `DOMChanged` event so other components (Alpine, Glightbox, a booking widget…) can re-initialise. Without JS it is a no-op. Sites with their own JS (htmx/Alpine) can bind the `data-variant` hook themselves.

The content zone selector defaults to `[data-variant-zone], main, #content` and can be overridden: `initVariantLinks({ zone: '#my-zone' })`.

## Reversibility (promote / demote)

Promotion is the case-by-case SEO arbitrage lever. From the admin, **Promote to master** turns a variant into the master; its former master and the other variants follow automatically (`VariantManager::promote()`). Deleting a master auto-promotes one of its variants so none is orphaned and the self-referencing foreign key stays satisfied.

## Editing

On the page form, the **Variant** panel exposes:

- *Variant of (master page)* — pick the master (only non-variant pages on the same host are offered).
- *Canonical URL (override)* — force the canonical independently of the variant relation.
