# Plan — Variant Pages (canonical consolidation)

> Revised after first consumer feedback (Symfony stay-catalog platform, htmx +
> Alpine front). **The `#hash` / serve-variant-under-master / client-morph model
> is dropped.** A variant is now a real, server-rendered page with its own URL
> that emits `canonical → master`; consolidation is done by **link rewriting**;
> the no-reload swap is **opt-in progressive enhancement in `packages/js-helper`**,
> never in core.

## Goal

Let a Page declare itself a **variant of a master page**. A variant is a full,
independently rendered page with its own URL, but no own SEO weight: it emits
`<link rel="canonical" href="{master}">`, every internal link to it is rewritten
to point at the master (with the variant URL carried on a `data-variant`
attribute hook), and it is excluded from sitemap / internal search / menus. A
one-click action promotes/demotes a variant ↔ master (case-by-case SEO
arbitrage). Pushword owns the **decision and its reversibility**, not the
measurement (Search Console / Matomo, consumer side).

Use beyond marketplace: near-duplicate product/stay sheets, campaign / partner /
white-label landing pages — anything consolidating onto a canonical page.

## Recorded decisions (post-feedback)

1. **No `#hash`, no required client morph.** Variant = real page, own URL,
   server-rendered, `canonical → master`. The canonical alone guarantees single
   indexation.
2. **Consolidation by link rewriting, not URL masking.** Every internal link to a
   variant is rewritten to:
   ```html
   <a href="{master-url}" data-variant="{variant-url}">…</a>
   ```
   Crawler / no-JS follows `href` = master (internal link juice consolidated,
   variant invisible to Google). The JS layer (opt-in) loads the variant.
3. **No-reload = progressive enhancement, in `js-helper`, not core.**
   - Core emits **only the `data-variant` hook attribute**; it never presumes
     htmx/Alpine.
   - `js-helper` ships an **opt-in vanilla helper** wiring `[data-variant]`:
     click → fetch variant URL → swap content zone → `pushState` variant URL
     (shareability/provenance) → dispatch a re-init event. No-op without JS.
   - A site with its own JS can wire its own behavior instead.
   - `js-helper` may additionally expose an htmx/Alpine hook (≈99% of Pushword
     projects load them).
   - Mirror the existing precedent: `HtmlUnpublishedLink` (filter) ↔
     `js-helper/src/unpublishedLinks.js`.
4. **Exclusions, precise.** Exclude variants from **sitemap + internal search +
   menus**. Do **NOT** blanket-exclude them from content lists/cards — a consumer
   catalog wants the variant card visible (with its link rewritten). So: expose
   `isVariant()` + a repository helper `andNotVariant()`, apply it in
   `getIndexablePagesQuery()` (covers sitemap + feed + search) and in menu/nav
   page queries — but **leave list/card rendering to the consumer**.
5. **`<head>`:** the variant keeps its own head; only the **canonical** points to
   the master.
6. **Reversibility (promote/demote) is a core requirement** — the arbitrage lever.
7. **`customCanonical` (Phase 1)** stays the foundation, independently shippable.

## Interface contract (consumer stitching surface)

- **Entity:** `variantOf` (ManyToOne Page), `variants` (OneToMany), `isVariant()`,
  `customCanonical` (string).
- **Rendering:** variant page → `canonical → master`; internal links to a variant
  → `href="{master}" data-variant="{variant-url}"`.
- **js-helper:** on click on `[data-variant]`, fetch + swap content zone +
  `pushState` variant URL; no effect without JS; dispatches a re-init event.
- **Repository:** `andNotVariant()` applied in `getIndexablePagesQuery()`; menu/nav
  queries exclude variants.
- **Flat:** `variantOf` round-trips as slug; `customCanonical` as string.

## Out of Pushword scope (consumer-handled)

- The metadata **switcher UI** (sibling cards, active/greyed state, spec sheet).
- **Re-initialising the site's JS components** after a swap (Alpine, booking
  widget, Glightbox…). Pushword's helper only **dispatches an event** so the
  consumer can re-init.
- SEO **measurement** and the decision data source (Search Console / Matomo).

---

## Phase 1 — `customCanonical` (foundation, independent deliverable)

1. **Entity.** Trait `packages/core/src/Entity/PageTrait/PageCanonicalTrait.php`
   with `?string $customCanonical = null` + grouped get/set; wire into `Page.php`.
2. **Schema.** `cd packages/dev-app && php bin/console doctrine:schema:update --force`.
3. **Template.** `page_default.html.twig` `robots` block:
   ```twig
   {% if page.customCanonical %}
     <link rel="canonical" href="{{ page.customCanonical }}">
   {% else %}
     <link rel="canonical" href="{{ page(page, true, apps.getCurrentPager()) }}">
   {% endif %}
   ```
4. **Flat.** Add `customCanonical` (scalar) to `PageExporter` + `PageImporter`.
5. **Admin.** Expose `customCanonical` on the Page form (SEO section).
6. **Tests.** Canonical render with/without override; flat round-trip.

---

## Phase 2 — Variant pages (data / SEO layer — stays in Pushword)

### 2.1 Entity & schema

1. Trait `PageTrait/PageVariantTrait.php`, **orthogonal to `PageParentTrait`**:
   - `?Page $variantOf` — `ManyToOne(targetEntity: Page, inversedBy: 'variants')`.
   - `Collection<int, Page> $variants` — `OneToMany(mappedBy: 'variantOf')`.
   - `isVariant(): bool => null !== $this->variantOf`.
   - `setVariantOf()` validation: master must **not itself be a variant** (flat
     hierarchy, no variant-of-variant), no self/cycle — mirror
     `PageParentTrait::validateParentPage()`.
2. Wire trait into `Page.php` (+ docblock); init `$variants` in ctor.
3. Schema update.

### 2.2 Canonical & hreflang (template)

- `robots` block resolution order: `variantOf` → master, else `customCanonical`,
  else self:
  ```twig
  {% if page.variantOf %}
    <link rel="canonical" href="{{ page(page.variantOf, true) }}">
  {% elseif page.customCanonical %}
    <link rel="canonical" href="{{ page.customCanonical }}">
  {% else %}
    <link rel="canonical" href="{{ page(page, true, apps.getCurrentPager()) }}">
  {% endif %}
  ```
- `alternate_language` block: a variant is not self-canonical → emits **no
  hreflang cluster**. Wrap the block body in `{% if not page.variantOf %}`.

### 2.3 Link rewriting — body / markdown (filter)

- New `packages/core/src/Component/EntityFilter/Filter/HtmlVariantLink.php`, clone
  of `HtmlUnpublishedLink` (`#[AsFilter]`, same `HTML_REGEX`, inject
  `PageRepository` + route generator). For each `<a href>`:
  - resolve href → Page; if `target->isVariant()`, rewrite to
    `<a href="{master-url}" data-variant="{original-variant-url}" …>`.
  - master url via `page(target.variantOf)` (route generator, canonical/host-aware).
- Verify ordering vs `HtmlLinkMultisite` (rewrite before host-prefixing, or
  compose so the final `href` is the host-correct master URL).

### 2.4 `page()` / template links

- **Do not silently consolidate `page()`** — a variant is a real page and its own
  URL must stay retrievable (it is the `data-variant` value, and the page must be
  directly reachable). Consolidation for template/consumer markup is done via the
  filter (body) and by the consumer rendering cards using the contract
  (`href = page(variant.variantOf)`, `data-variant = page(variant)`).
- Expose `isVariant()` and `variantOf` to Twig so consumer cards/menus can
  compose. (Optional ergonomic helper `variant_link(page)` only if needed.)

### 2.5 Exclusions (repository + menus)

- Add `andNotVariant(QueryBuilder $qb)` to `PageRepository` and apply it inside
  `getIndexablePagesQuery()` → covers **sitemap** (`SitemapController`), **feed**
  (`FeedController`), **search** (`Indexer.php:61`) in one place.
- Apply `andNotVariant()` to any core menu/nav page query. Site menus that are
  consumer-template-driven use `isVariant()` to filter.
- **Do not** touch generic content-list queries used for cards/catalog — leave to
  the consumer.

### 2.6 Reversibility — promote / demote & delete

- **Promote(V):** `M = V.variantOf`; for each sibling `S` (`S.variantOf === M,
  S !== V`) set `S.variantOf = V`; `M.variantOf = V`; `V.variantOf = null`.
  Canonical + link rewriting follow automatically.
- **Admin action** on PageCrudController ("Promote to master" / "Make variant of").
  Custom EasyAdmin action — remember `easyadmin-page-actions-block` (custom page
  templates need the `page_actions` block).
- **Delete master:** Doctrine `preRemove` listener (or admin delete flow) promotes
  one variant before removal so siblings are not orphaned.

### 2.7 Flat

- Add `variantOf` to `PageExporter` (export as master **slug**) and `PageImporter`
  relation map (`PageImporter.php` ~ line 548, alongside `parentPage => Page::class`);
  add `customCanonical` (scalar) if not already from Phase 1.
- **MariaDB self-FK:** replicate the `parentPage` null-out from `PageSync.php`
  (~ line 479) for `variantOf`, else import order violates the self-referencing FK.

### 2.8 Static generator

- Variants are generated normally (full page, own URL, `canonical → master`). No
  redirect stub, no partial artifact. They drop out of the static sitemap via 2.5.

---

## Phase 3 — `js-helper` progressive enhancement (opt-in, out of core)

1. New module `packages/js-helper/src/variantLinks.js`, mirroring
   `unpublishedLinks.js`:
   - Delegated click listener on `[data-variant]`:
     `e.preventDefault()` → `fetch(el.dataset.variant)` → extract the content zone
     from the response → swap it into the page's content zone →
     `history.pushState({}, '', el.dataset.variant)`.
   - Content-zone selector is **configurable** (default e.g. `#content` or
     `[data-variant-zone]`); document it.
   - After swap, dispatch `pushword:variant:loaded` (and/or call the existing
     `onDomChanged()`) so the consumer re-inits Alpine / Glightbox / booking widget.
   - No-op without JS (the `href` already points at the master → graceful
     degradation).
2. Wire it into `app.js` (import + init), alongside `restoreUnpublishedLinks`.
   Keep it **opt-in** (only active if the helper is loaded / a config flag).
3. Optional: an **htmx/Alpine** convenience hook in addition to the vanilla one
   (the consumer can also bind their own).
4. `popstate` handling: navigating back restores the master content zone (or
   reload) — keep simple, document the choice.

---

## Tests

- **Entity:** `isVariant`; validation rejects variant-of-variant, self, cycle.
- **Filter:** body `<a href="/variant">` → `href="{master}" data-variant="/variant"`;
  non-variant links untouched; ordering vs multisite filter.
- **Canonical/hreflang:** variant render → canonical = master, no hreflang.
- **Repository:** `getIndexablePagesQuery()` excludes variants (assert sitemap,
  feed, search indexer all drop them); content-list queries still include them.
- **Reversibility:** promote reassigns siblings + swaps master; delete-master
  promotes a variant.
- **Flat:** `variantOf` round-trip (export slug → import resolves); MariaDB import
  order under `composer test-mariadb`.
- **js-helper:** `variantLinks.js` unit test (jsdom) — click fetch+swap+pushState,
  no-op without the hook (mirror `unpublishedLinks` / `ShowMore` test style).

## Quality gates

`composer stan`, `composer rector`, `composer test` (+ `composer test-mariadb`
for the FK path), `composer console cache:clear`. js-helper: `yarn test`. No
skipped tests.

## Suggested PR split

1. **PR1 — Phase 1** (`customCanonical`): entity + template + flat + admin + tests.
2. **PR2 — Phase 2** (data/SEO core): `variantOf` entity, canonical/hreflang,
   `HtmlVariantLink` filter, `andNotVariant()` exclusions, flat, static, admin
   field + promote/demote + delete-promotion, tests. **Fully functional
   server-side without any JS** (variant served, links consolidated to master).
3. **PR3 — Phase 3** (`js-helper`): `variantLinks.js` opt-in vanilla helper
   (+ optional htmx/Alpine hook), re-init event, tests.
