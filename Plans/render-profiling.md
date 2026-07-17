# Page render / static generation profiling — findings

**Date:** 2026-07-17
**Scope:** Where does time actually go when rendering a page (and therefore when
running `pw:static`)? Prioritise what to optimise. Secondary question: is it
worth swapping `league/commonmark` for `tempestphp/markdown`?

## TL;DR

Rendering is **TOC-bound, not markdown-bound**.

`SplitContent` runs **two full HTML5 DOM parse + serialize passes per page** via
`caseyamcl/toc`. That is **32% of a warm render**. Actual markdown parsing is
**0.87% of a warm render** and ~23% of a cold one.

Swapping the markdown engine is therefore the wrong lever: even an *infinitely
fast* parser saves 0% warm / 23% cold, while costing the six custom inline
parsers and byte-identical-output guarantees. This is the **second** refutation
of the tempest#3 thesis for commonmark (see `commonmark-profiling.md`, which
found cloning at 0.6%).

## Method

- **Instrument:** altimood, host `us.altimood.com`, **48 real pages**, avg
  **123 KB** of HTML output. Real corpus, real templates, real Twig extensions.
- **Validity:** all **11 render-path files** are **byte-identical** between
  altimood's vendored `pushword/*` (rc738) and monorepo HEAD, so altimood
  profiles HEAD exactly. Verified by `diff` before profiling.
  - *Do not* port altimood content into `packages/skeleton` to profile: the
    content depends on altimood-only Twig functions (`gpx`, `price`, `mail`,
    `tel`, `link`, `refuges`, `pw`, `encrypt`) which would throw and degrade to
    `TwigErrorMarker` — you would be profiling error handling.
  - *Do not* profile via `pw:page-scan`: its own DomCrawler pass (Masterminds
    HTML5, one `parseHtml5` per page) dominates the profile and is **not** part
    of the render.
- **Harness:** a read-only script boots the kernel and renders N pages through
  the real path (`Request::create` + `$kernel->handle()`), mirroring what
  `PageGenerator::saveAsStatic()` does (`PageGenerator.php:117`).
- **Profiler:** Xdebug 3.5.3 (`/opt/remi/php85/root/usr/lib64/php/modules/xdebug.so`
  loads fine into Fedora PHP 8.5.8), cachegrind aggregated by self + inclusive
  cost. Every headline number is **cross-checked with a profiler-free A/B**,
  because xdebug inflates call-heavy code such as Masterminds.

## Measurements (profiler-free, stable ±0.5 ms, 48 pages)

| Scenario | ms/page | Delta |
|---|---|---|
| Warm render (markdown cache hit) | **26.8** | baseline |
| Cold render (markdown pool cleared) | 34.9 | **markdown parse = +8.2 (23% of cold)** |
| Warm render, TOC disabled | 18.2 | **TOC = 8.5 (32% of warm)** |
| `HtmlMinifier::compress` | +5.3 | ~16% of static per-page cost |

TOC only runs when the page has the `toc` / `tocTitle` custom property — 24 of
48 pages here, so it is **~17 ms on the pages that use it (~48% of their render)**.
Cost is linear in document size: `tour-du-mont-blanc` (492 KB) spends **67.8 ms
in TOC alone**.

## Where the TOC time goes (inclusive, warm profile)

| Function | incl % | calls |
|---|---|---|
| `Masterminds\HTML5->loadHTML` **from `MarkupFixer->fix`** | 21.3% | 24 |
| `Masterminds\HTML5->loadHTML` **from `TocGenerator->getMenu`** | **21.3%** | 24 |
| `Masterminds\HTML5->saveHTML` (fix only) | 9.9% | 24 |
| knp-menu building + slugger + renderer | **~1.5%** | — |

The two parses cost the **same**, and the menu algorithm itself is nearly free.
`SplitContent.php:50` calls `MarkupFixer()->fix()` (parse → inject heading ids →
serialize) and `SplitContent.php:122` calls `TocGenerator()->getHtmlMenu()`,
which **re-parses the same HTML** just to read the headings back out.

## Ranked optimisation targets

1. **TOC double parse — 8.5 ms/page (32% of warm render).**
   Both calls are pure functions of a string.
   - **(a) Cache** the result by content hash. Biggest win *on a warm cache*,
     but a full `pw:static` build renders each page exactly once, so a cold
     build gains **nothing**. Good for incremental rebuilds only.
   - **(b) Share one DOM parse** — removes one of the two parses. Helps **cold
     builds too**, which is what static generation actually is. **DONE — see
     "Fix (b), applied" below.**
   - **(c) Build ids + TOC from the CommonMark AST.** `league/commonmark`
     already ships `TableOfContents` and `HeadingPermalink` extensions and
     `MarkdownParser` does **not** register them. Removes the DOM round-trip
     entirely — but **blocked**: Pushword parses markdown **per block**
     (`Markdown::transformPart`), so a TOC extension cannot see headings across
     blocks.

2. **`MediaRepository::warmupFileNameIndexLight` — 8.4% of render.**
   Rebuilds the full **12 133-row** filename index **~once per page** (45 rebuilds
   / 48 pages). **DONE — see "Fix, applied — keep the media index across a
   clear" below.**

3. ~~**`HtmlMinifier` — 5.3 ms/page.**~~ **Investigated and dropped — see
   "HtmlMinifier: not worth it" below.** The O(n·m) protect loop called out here
   is only **7%** of the minifier; this ranking was wrong.

4. **`SVGExtension::getSvg` — 949 calls (~20/page), zero caching. DONE — see
   "Fix, applied — read each icon once" below.** Worth **−17%**, not the 1.8% the
   profiler suggested.

5. **Twig filter per markdown block — ~24% incl, 3266 calls.**
   `Twig::render()` runs `createTemplate()` **per markdown block**
   (`Markdown.php:80`), *outside* the markdown cache, so it re-runs on every
   cache hit. Much of it is genuine `gallery()` / `image()` work, so treat this
   as context rather than pure waste.

## Fix (b), applied — share the DOM parse

`MarkupFixer` and `TocGenerator` both accept an **injectable `Masterminds\HTML5`
parser**. `Pushword\Core\Service\Toc\DomCapturingHtml5` overrides `loadHTML()` to
keep a reference to the document it returns. Since `MarkupFixer::fix()` mutates
that DOM in place to inject the heading ids, `SplitContent` can harvest the
headings straight out of it (`extractTocHeadings()`, one XPath union query over
`h1..h6 | comment()`, stopping at the first `stop-toc` / `end-toc` comment) and
hand `TocGenerator` a **headings-only document of a few hundred bytes** instead
of the whole page.

No library logic is copied: `MarkupFixer` still injects the ids and
`TocGenerator` still builds the menu with its own algorithm — it just no longer
re-parses 200 KB to find the headings. The `<!--end-toc-->` cutoff moved from a
string `explode()` to a DOM comment-node check, which is equivalent (a marker
that is escaped in the source is text in both models, so neither cuts).

**Result (48 pages, profiler-free):**

| | Before | After | |
|---|---|---|---|
| Warm render | 26.8 ms | **21.1 ms** | **−21%** |
| Cold render | 34.9 ms | **28.2 ms** | **−19%** |
| TOC share of warm render | 8.5 ms | **2.9 ms** | **−66%** |

**Output equivalence:** all **473 real pages** (`us.altimood.com` +
`altimood.com`) re-rendered and diffed byte-for-byte against the previous
implementation. Identical everywhere, except two intentionally random ids
(`data-gallery="…"`, `sh-…`) and PHP object ids inside two pages that already
errored before the change. TOC menu links verified byte-identical (md5) on
`tour-du-mont-blanc`, a page using the cutoff marker.

Regression guard: `EntityFilterTest::testTocStopsAtCutoffMarker` (both marker
spellings) — verified to fail if the cutoff is broken.

## Fix, applied — keep the media index across a clear

**The first diagnosis blamed the wrong thing.** `cache.app` being an
`ApcuAdapter` with `apc.enable_cli = Off` is real, but it is only the *missing
airbag*, not the crash. The actual chain:

1. `MediaRepository` is `#[AsDoctrineListener(event: Events::onClear)]`.
2. `onClear()` called `resetFileNameIndexLight()`, dropping the in-memory index.
3. `PagesGenerator.php:110` calls `EntityManager::clear()` **every 2 pages**.
4. → a full 12 133-row DQL rebuild every other page.
5. APCu would normally absorb that, but it never hits in CLI.

The index holds **scalar rows only** (its own docblock says so; it is built with
`getArrayResult()`). `EntityManager::clear()` detaches *entities* — it cannot
stale a scalar array. The reset was throwing away valid data.

**Why not move the pool to the filesystem** (the first idea): it would *penalise*
APCu users — in-memory beats filesystem on the web path, which is why they picked
APCu — and it would not fix the cause anyway: you would still read and unserialize
a 12k-row array every 2 pages.

**What `onClear` is actually for:** it is the freshness trigger for long-lived
processes. In worker mode the clear between requests is what makes the next
lookup re-read the version counter and notice another process's writes. So it
cannot simply be deleted.

**The fix:** split the two resets.

- `resetFileNameIndexLight()` stays the **hard** reset (drops the index) and is
  still what real writes use — `bumpVersion()`, `MediaCacheInvalidationListener`,
  `MediaImporter`.
- `onClear()` becomes a **soft** reset: it only clears `warmedLight`, so the next
  lookup re-checks the version (one cheap cache read) and reuses the index it
  already has when the version is unchanged. `indexVersion` records what the memo
  was built for.

Works for **every** backend (APCu, filesystem, Redis, none), no config change, no
new pool, and it fixes CLI, web and worker alike.

**Result (48 pages, profiler-free, measured on its own):** warm render
**25.3 ms → 21.6 ms (−15%)**.

**Output equivalence:** 473 real pages, released vendor vs patched, identical —
the only diffs are PHP object ids inside 4 pages that already threw *before* any
change (pre-existing altimood data issues).

Guards: `MediaRepositoryTest::testOnClearRechecksVersion` (verified to fail if the
memo ignores the version) and `testLookupStillResolvesAfterOnClear`.

> Measuring trap: an `old/` HTML dump taken before any `cache:clear` is **not** a
> valid baseline — unrelated cached state (internal link hrefs) drifts and shows
> up as ~50 false diffs. Always dump control and candidate in the *same* state.

## Fix, applied — read each icon once

`SVGExtension::getSvg()` had no caching at all: every `svg()` call did
`file_exists()` + `mime_content_type()` + `file_get_contents()`. A page makes
~20 of those for a handful of distinct icons, so the same file was opened over
and over.

`getSvg()` now splits into the per-call part (rendering `$attr` onto the tag) and
a memoized `loadSvg()` returning the raw file contents. The memo is keyed on the
**resolved search path**, not the `$dir` argument: `svg_dir` is per app, so with
`$dir = ''` the same name resolves differently per host — altimood serves 17.
Icons are static files, so a per-instance memo needs no invalidation, and it
survives across pages in a `pw:static` run.

**Result:** warm render **20.1 ms → 16.6 ms (−17%)**, output identical on 473
real pages (zero diffs).

**The profiler said this was 1.8%.** It was 17%. Xdebug inflates PHP function
calls but not C calls, so an IO-bound one-liner like `mime_content_type()`
(libmagic: opens the file, reads magic bytes) is *under*-represented in a
cachegrind profile — the mirror image of the Masterminds over-weighting. Trust
the A/B wall-clock, not the profile share.

Guards: `SVGExtensionTest` — attributes must not bleed between two calls for the
same icon, and the same name in two dirs must not leak (both verified to fail
under the obvious wrong implementations), plus the `question` and FontAwesome
5→6 fallbacks, which the memo could otherwise poison.

## HtmlMinifier: not worth it (investigated, no change made)

Ranked #3 above on the assumption that its per-node
`str_replace($node->outerHtml(), …)` over the whole document (O(n·m)) was the
cost. **Measured on 421 real pages (68.4 KB avg), that was wrong:**

| Phase | ms/page | Share |
|---|---|---|
| **DomCrawler parse** | **1.14** | **45%** |
| regex passes (4 full-document) | 0.75 | 30% |
| DomCrawler serialize | 0.24 | 9% |
| **protect loop (the supposed O(n·m))** | **0.18** | **7%** |
| restore | 0.09 | 4% |
| strip comments | 0.05 | 2% |
| **total** | **2.53** | |

There are only **5.5 protected nodes per page**, so `n` never gets big enough for
the O(n·m) to matter. Optimising it would win ~7% of 2.5 ms.

The 45% parse is **load-bearing**: the round-trip changes the markup on 421/421
pages — it collapses whitespace inside tags, quotes bare attributes
(`crossorigin` → `crossorigin=""`, `data-rot=/` → `data-rot="/"`) and decodes
entities (`&raquo;` → `»`). The later regex passes also depend on it having
lowercased tag names. Replacing it with regex would be a large behaviour change
for ~1 ms.

**And the minifier earns its cost.** It is tempting to argue it is pointless
because the generator gzip/brotlis every page anyway, but measured:

| | raw | minified | saved |
|---|---|---|---|
| uncompressed | 68.4 KB | 58.8 KB | 14.1% |
| gzip -9 | 15.0 KB | 14.3 KB | **4.7%** |
| brotli -11 | 12.6 KB | 12.0 KB | **4.4%** |

~4.5% off every compressed response, paid once at build time for 2.5 ms/page. For
a static site that is a good trade. **Left alone.**

## Markdown: verdict on tempest/markdown

**Not worth it.**

| | Warm render | Cold render |
|---|---|---|
| `Markdown->parseMarkdown` (real CommonMark work) | **0.87%** | ~18–20% |
| Measured A/B delta | 0 ms | 8.2 ms (23%) |

A 2× faster engine would save ~4 ms/page on cold renders only (~11%), against:
losing six custom parsers (`EmailAutolink`, `PhoneAutolink`, `ObfuscatedLink`,
`Date`, …), the `Attributes`/`Table`/`TaskList` extension ecosystem, and
byte-identical output. TOC (32%) and the media index (8.4%) are strictly better
targets for strictly less risk.

Also note the markdown cache design has **improved** since May: only fragments
containing `![` mix the media version into their key
(`MarkdownParser::cacheKeyVersion()`), so a media write no longer invalidates
the whole markdown cache — the concern raised in `commonmark-profiling.md` is
resolved.

## Static generation context

- Per page ≈ **33 ms** (render + minify), plus write and gzip/brotli sidecars.
- Every page goes through a **full HTTP kernel request** (`PageGenerator.php:117`),
  including a second in-process kernel (`KernelTrait.php:17-25`).
- Already parallelised across subprocesses (`WorkerCountResolver`), so the
  per-page CPU cost above is what multiplies.

---

# Pass 2 (2026-07-17): 16.4 → 11.2 ms/page warm

Baseline reproduced on the same harness/corpus: **16.4 warm / 22.9 cold**.

## Method upgrade: Excimer, and what "ms/page" is made of

Sampling profiler this time (no xdebug bias): `php85-php-pecl-excimer` RPM
extracted as a plain user (no root), `.so` loaded via `-d extension=…` into
Fedora PHP 8.5.8. Two profiles: single-pass (loop 1 of a fresh process — what
`pw:static` pays) and steady-state (loops 2+, same process).

**Key structural finding:** the in-process marginal cost of a warm render is only
**~4.3 ms/page**. The other ~12 ms of the single-pass number is per-process,
first-touch cost — PHP file compiles (no CLI opcache), Twig template class
loads, Doctrine metadata — amortized over only 48 pages. Real `pw:static`
workers each pay it once per process. Steady state is dominated by
`LinkProvider::renderLink`/`UrlGenerator` (~16%) and lazy `translations`
ManyToMany hydration (~22%).

## Fix, applied — deterministic where-parameter names

`FilterWhereParser` named each DQL parameter `'m'.md5('a'.random_int(…))`. The
name is part of the DQL string, so **every process generated never-seen DQL**:
Doctrine's query cache could never hit across processes (~6% of a single-pass
render re-parsing DQL + ~3% writing useless cache entries), and the `system`
pool **grew by ~35 files per 48-page run, forever** (production disk leak, one
build at a time). Now `'w'.count($qb->getParameters())` — deterministic, unique.
Guard: `PageRepositoryTest::testWhereFilterProducesDeterministicDql`.

## Fix, applied — Twig filter gate matches real Twig tokens

`Filter\Twig::render()` bailed only when the block contained no `{` at all; a
block with markdown attributes (`{id=…}`, `{.class}`) paid the full
`createTemplate()` round-trip to render itself verbatim. Only `{{`, `{%`, `{#`
open a Twig token, so the gate now checks those three. Byte-identical by
construction (a token-less template renders as its source).
Guard: `TwigGateTest` (asserts createTemplate is never called for such blocks).

## Fix, applied — deterministic gallery ids (`page.uniqueGalleryId`)

The gallery template's id chain `gallery_id ?? page.uniqueGalleryId ??
random(10, 1000)` **always fell through to `random()`**: `uniqueGalleryId`
existed nowhere, and `renderGallery()` did not even pass `page` to the
template. So every render of a gallery page produced different bytes, which:

- invalidated the **markdown cache** for those fragments on every render
  (random id is inside the cached text) and grew the pool per run;
- made every rebuilt gallery page **rewrite + re-gzip/brotli** in `pw:static`
  (the `content === file_get_contents` skip never fired);
- would have defeated any downstream content-hash cache (see next fix).

`Page::uniqueGalleryId()` now exists (runtime counter, not persisted, restarts
per loaded entity) and `renderGallery()` passes `page`. Renders of an unchanged
page are now **byte-identical** — the only remaining nondeterminism on altimood
is its own `reviewList.html.twig` `random()` (app-level, not ours).
Guard: `PageTest::testUniqueGalleryIdIsSequentialAndRestartsPerEntity`.

## Fix, applied — cache the TOC heading fix by content hash

The remaining TOC cost (one Masterminds parse + serialize, ~24% of a warm
render, ~3.9 ms/page) is a **pure function of the content string**.
`SplitContent::fixHeadings()` now caches `[fixed html, toc headings]` in
`cache.pushword_markdown` keyed on `xxh3(content)` — no invalidation needed, a
content change changes the key. Worthless until the gallery-id fix above made
content deterministic (the first attempt missed on every render — measured,
then fixed the source of entropy rather than normalizing the key).
Guard: `SplitContentTocCacheTest` (hit must equal fresh computation).

## Results (48 pages, profiler-free, interleaved A/B for the small deltas)

| | Before | After | |
|---|---|---|---|
| Warm render | 16.4 ms | **11.2 ms** | **−32%** |
| Cold render (markdown+toc pool cleared) | 22.9 ms | 21.9 ms | −4% |
| `system` pool growth per run | +35 files | **0** | leak fixed |
| Re-render of unchanged page | differs | **byte-identical** | |

**Output equivalence:** 473 real pages (`us.` + `altimood.com`, redirects
included), normalized diff (`data-gallery`, `sh-`, `{#objectid}`) — identical.
All new guards verified-to-fail against the unfixed code.

## Open leads for a pass 3 (unmeasured unless noted)

- **`PagesGenerator::preloadMediaCache()` calls `bumpVersion()` on every build**,
  invalidating every image-bearing markdown fragment even when no media changed
  — every real build pays partial-cold markdown. It papers over a stale-empty-
  index bug (see the code comment); fixing the root cause would let incremental
  builds keep the fragment cache.
- **Lazy `translations` hydration**: ~22% of steady-state (~1 ms/page absolute,
  warm and cold) loading the ManyToMany per page (16 locales). A batched
  preload in `PagesGenerator` (before the EM-clear cadence) would kill ~48
  queries per host.
- **Per-process fixed costs** (~12 ms/page on a 48-page host): Twig block
  template class loads (~8% single-pass), no CLI opcache. Fewer, longer-lived
  worker processes or `opcache.enable_cli=1` for `pw:static` workers would cut
  most of it; measure before touching `WorkerCountResolver`.
- `LinkProvider::renderLink` + route generation ~16% of steady state; a
  per-request URL memo for repeated targets (footer/nav links) is the obvious
  candidate.
