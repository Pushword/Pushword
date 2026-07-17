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

3. **`HtmlMinifier` — 5.3 ms/page.** For each `pre|code|script|textarea` node it
   does `str_replace($node->outerHtml(), …)` over the **whole document** →
   O(n·m), plus 4 more full-document regex passes.

4. **`SVGExtension::getSvg` — 1.8%, 949 calls (~20/page), zero caching.**
   Every `svg()` call does `file_exists` + `mime_content_type` (libmagic — opens
   the file) + `file_get_contents`. The same icon is re-read every time. Fix: a
   static memo keyed on `(name, dir)`.

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
