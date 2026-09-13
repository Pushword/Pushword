# Pushword content analysis and Markdown exploration

This crate contains two separate executables, owned by `pushword/core`:

- `pushword-content-analyzer`: opt-in HTML analysis used by `ContentSplitter`
  and experimental Markdown conversion in a reused worker process.
- `pushword-content-probe`: standalone [Comrak](https://github.com/kivikakk/comrak)
  Markdown compatibility and performance research. It is not a runtime backend.

PHP remains the default and complete backend, including on shared hosting.
The native HTML path is hybrid: PHP retains ICU slugging and Knp menu rendering.
It declines unsupported HTML and uses the existing PHP implementation for it.
Passing the differential corpus does not prove parity for arbitrary documents.

The domain code and tests stay inside `pushword/core`; the shared PHP process
transport is `Service/NativeWorker.php`, also used by the static generator's
minifier. Separate executables keep their deployment and dependencies optional.
No new Composer package or operation registry is needed.

## Enable native content analysis

Build on the deployment platform or a compatible machine:

```sh
make -C vendor/pushword/core/rust build
```

Copy `target/release/pushword-content-analyzer` to a trusted executable path:

```yaml
pushword:
    native_content_analyzer: '/opt/pushword/bin/pushword-content-analyzer'
    native_content_analyzer_timeout: 5.0
```

Clear the Symfony container cache in the rendering environment. Leave the path
unset or null on PHP-only hosting. Composer does not install a native binary.
The existing Twig `mainContentSplit(page)` function now uses this service;
public rendering, previews and static generation benefit wherever they use it.
Twig functions, media resolution and authorization remain in PHP. Markdown
conversion can also use Rust explicitly, with PHP handling site-dependent blocks.

## Enable native Markdown conversion

Build and deploy `pushword-content-analyzer` as above, then opt in separately:

```yaml
pushword:
    native_markdown_renderer: '/opt/pushword/bin/pushword-content-analyzer'
```

The default is null, including when native content analysis is enabled. Clear
the Symfony container cache after changing this path. The `Markdown` filter
prepares and expands blocks through the existing PHP/Twig pipeline, requests
uncached blocks as one Rust batch, then sends declined blocks to the existing
PHP converter. Inline Markdown remains PHP. The existing persistent Markdown
pool is shared, but native results have a separate versioned key namespace,
so disabling Rust cannot serve an old native result. Existing PHP cache hits
also bypass the worker. Missing or invalid executables,
timeouts, bad responses and oversized batches fall back to PHP and log one
warning until the service is reset. PHP-only hosting needs no binary or new
configuration.

The eligibility check conservatively declines notices, Markdown images, date
shortcodes and obfuscated e-mail links. Ordinary obfuscated links, e-mail
autolinks and French phone numbers use the core markup; the worker receives the
current locale and whether an admin is browsing a dynamic site. Conservative
false positives cost PHP conversion; unseen syntax or parser differences can
still cause a mismatch. Treat this backend as experimental until complete
downstream page rendering is differential-tested.

`ContentSplitter::split($html, $page)` returns the existing `SplitContent` object.
`splitMany([['html' => $html, 'page' => $page], ...])` sends all uncached documents
in one request, preserving order and independent heading-ID scopes. Inputs are
already rendered HTML. The existing `<!--break-->`, `<!--stop-toc-->` and
`<!--end-toc-->` behavior is preserved; there is no new block-marker syntax.
String, paragraph and menu accessors keep their PHP results and return types.

## Contract and fallback

The child process accepts newline-delimited JSON frames:

```json
{"version":1,"id":1,"operation":"split_content","documents":[{"html":"<h2>Title</h2><p>Text.</p>","toc":true}]}
```

The response repeats `version` and `id`, with an ordered `documents` list. Each
entry is null (declined) or an analysis containing `chapeau`, `segments`,
`headings`, `paragraphs` and `paragraphs_with_chapeau`. Headings carry `seed`,
`label`, `level` and `listed`. Segments surround complete heading-ID attribute
slots, so no artificial marker needs to be inserted into HTML. PHP validates
the whole response before using or caching any computed result.

Rust uses scraper/html5ever and serializes headings and HTML with the rules used
by the PHP HTML5 path. It handles picture/source, SVG, raw-text elements, nested
headings and breaks, empty attributes, common HTML repairs and unterminated
`&nbsp`. Paragraph fragments are parsed separately to preserve PHP's top-level
paragraph semantics. Traversal is bounded at depth 128. Ambiguous split markers
or heading text inside attributes, unterminated `&nbsp` in raw text, qualified
XLink attributes and controls that the parsers normalize differently still fall
back to PHP. This is an eligibility check, not HTML sanitization. The
`diagnose_split` worker operation reports a decline reason per document;
normal rendering uses `split_content`.

`render_markdown` uses the same frame envelope, with ordered documents of
`{"markdown":"...","fenced_code_pre_class":"...","locale":"fr","allow_obfuscated_links":true}`.
Each response entry is HTML or null (declined). PHP validates every entry
before using the batch.

On the current 70-case rendered Markdown corpus, all 70 documents use native
analysis and match PHP across every accessor. On a read-only snapshot of the
Altimood database, 1,474/1,474 rendered pages are accepted and match PHP,
including all 825 pages with a TOC. That establishes 100% coverage **for this
snapshot**, not for arbitrary HTML or future content.

Request and response frames, including their newline, are limited to 16 MiB.
Oversized responses are rejected before writing. The worker is reused until
Symfony resets the service. Missing executables, disabled `proc_open`, timeout
or invalid responses cause whole-batch PHP fallback and one warning, with no
retry until reset. A declined document alone does not disable the worker.

Validated aggregate results (including HTML TOC) and declines are cached in
`cache.pushword_markdown`, keyed by HTML, TOC presence and protocol cache version.
Menu objects are rebuilt per call to preserve their independent mutable state.
Cache failures cannot prevent rendering. The PHP backend also uses an indexed
unique slugger, avoiding repeated suffix searches while preserving ICU output
and PHP's numeric-string collision rules.

## Reproduce

From a full monorepo checkout with its development dependencies and fixtures:

```sh
make -C packages/core/rust build
make -C packages/core/rust test
make -C packages/core/rust audit
```

The native analyzer tests compare every accessor and the recursive Knp menu on
42 fixed HTML cases, the 70 rendered Markdown cases and 160 generated documents.
Generated supported documents must actually use Rust, so fallback cannot hide
a native failure. Tests cover batch/cache/reset behavior, malformed and oversized
protocol frames, large pipe responses, the Markdown/Twig pipeline and a PHP
subprocess with `proc_open` disabled. Native Markdown tests cover mixed batch
results, cache hits, missing-worker fallback and the PHP filter. The Comrak
probe separately records raw compatibility gaps and measures Markdown, TOC
and search extraction.

For comparable timings, pin `composer test-native-core` to one available CPU.
The test writes `target/content-probe-comrak.json`. The original
`benchmarks/2026-09-12.json` remains a pulldown-cmark baseline; the current
Comrak and Tempest measurements are in
`benchmarks/2026-09-12-comrak-tempest.json`.
Rust checks run in debug and release, including two property tests with 512
cases each, Clippy/rustfmt/rustdoc and forbidden crate-local unsafe code. The
weekly security job audits the lockfile. PHPStan includes the adapters, tests
and split benchmark. Continuous fuzzing, Miri and sanitizers have not been run.
There is no timing threshold in CI: shared-runner timing is noisy.

## Reproduce on downstream content

With a local downstream site and its test database available, render the actual
database pages into a private file outside the repository, then check every
`SplitContent` accessor against PHP:

```sh
php packages/core/rust/benchmarks/render-downstream.php ../altimood /tmp/pushword-altimood.ndjson
php packages/core/rust/benchmarks/check-downstream.php /tmp/pushword-altimood.ndjson
taskset -c 2 php packages/core/rust/benchmarks/time-downstream.php /tmp/pushword-altimood.ndjson
```

The first script boots the downstream test kernel and reads Doctrine pages;
it does not synchronize Markdown or change site content. The output can contain
private page text, so keep it outside the repository. `check-downstream.php`
fails on any native decline, exception or field mismatch. The committed
`benchmarks/2026-09-13-altimood.json` contains aggregate counts, hashes and three
timing passes, without page content. Regenerate the snapshot when the site data
or rendering pipeline changes.

## Complete split benchmark

```sh
taskset -c 2 php packages/core/rust/benchmarks/split.php
```

Choose an available CPU. The committed `benchmarks/2026-09-13-split.json` records
five shuffled samples of five documents, runtime versions, CPU affinity and
input/binary/lockfile hashes. Every timed output is checked against PHP. This
measures the complete string/list result, including HTML TOC and both paragraph
views, with a reused worker and PHP assembly included. It excludes Markdown,
Twig, SQL, HTTP and publication. The PHP baseline already uses the improved
slugger. Cache hits use an in-memory ArrayAdapter and the two backends cache
different amounts of work; they are not a filesystem-cache latency benchmark.

The older TOC-only table below has a narrower accessor set and the previous
slugger. Do not compare its timings directly with the aggregate benchmark.

Local medians in milliseconds per document, PHP 8.5.9/ICU 77.1, CPU 2:

| HTML workload | PHP, uncached | Rust, uncached | Rust, batch of 5 | PHP TOC cache hit | Rust aggregate cache hit |
|---|---:|---:|---:|---:|---:|
| Short, 421 B | 0.379 | 0.081 | 0.060 | 0.170 | 0.005 |
| Article, 15.9 KB | 10.620 | 1.529 | 1.523 | 4.934 | 0.015 |
| Long, 160 KB | 107.540 | 17.583 | 17.714 | 52.011 | 0.074 |
| 800 identical headings, 161 KB | 106.130 | 16.592 | 16.782 | 51.525 | 0.087 |

On these synthetic inputs the uncached aggregate is about 5–7 times faster.
The separate slugging microbenchmark drops from 152.3 ms to 0.081 ms for 800
identical headings; this gain also applies on PHP-only hosting. Neither ratio
is a whole-page or whole-site speedup.

On the Altimood snapshot, three uncached passes on one CPU have a median sum of
6.53 s in PHP versus 2.02 s with the native worker (3.23×). TOC pages have
median per-page time 5.83 ms versus 1.69 ms; pages without TOC, 0.82 ms versus
0.40 ms. These figures include PHP assembly and reused-worker IPC, but exclude
Markdown, Twig, SQL, HTTP, filesystem caching and complete page rendering.

## Optional Tempest comparison

Tempest is an optional PHP comparison. It is deliberately kept out of the
Pushword dependency graph because the package currently requires PHP 8.5 and
is still evolving. Install it in the ignored benchmark directory, then run:

```sh
mkdir -p packages/core/rust/target/tempest
composer init --working-dir=packages/core/rust/target/tempest \
  --name=pushword/tempest-benchmark --require=php:^8.5 --no-interaction
composer require --working-dir=packages/core/rust/target/tempest \
  --no-interaction --no-scripts tempest/markdown:1.2.2
php packages/core/rust/benchmarks/tempest.php
```

Set `PUSHWORD_TEMPEST_AUTOLOAD` when Composer uses another directory. The
script measures the same deterministic inputs and checks the shared PHP corpus;
it constructs `Tempest\Markdown\Markdown(null)` so code highlighting is not
part of the parser comparison. The stable package has no `parseMany()` yet.
The open [PR #24](https://github.com/tempestphp/markdown/pull/24) adds it as an
opt-in collection of independently parsed chunks separated by
`<!-- next -->` or `<!-- next: name -->`.

## Findings

Local medians in **milliseconds per document**, five samples of 20 documents,
one CPU, PHP 8.5.9/libxml 2.12.10, Rust 1.98.0:

| Synthetic input | PHP Markdown uncached | Comrak Markdown batch | PHP Markdown cache hit | PHP TOC uncached | PHP TOC cache hit | PHP search text |
|---|---:|---:|---:|---:|---:|---:|
| Short, 244 B | 0.400 | 0.058 | 0.0010 | 0.261 | 0.0024 | 0.0096 |
| Article, 9.8 KB | 8.086 | 0.408 | 0.0026 | 5.916 | 0.0104 | 0.2228 |
| Long, 99 KB, 800 unique headings | 89.507 | 2.968 | 0.0184 | 59.825 | 0.1057 | 2.254 |
| Long, 97.6 KB, 800 identical headings | 89.718 | 2.810 | 0.0141 | 228.182 | 0.1042 | 2.227 |

Inputs repeat a deliberately simple CommonMark paragraph. This is not a sample
of production traffic. PHP converters are already constructed; uncached means
conversion executes each time, not a cold PHP process. Cache hits use an
in-memory `ArrayAdapter`, not the production filesystem cache. The Rust time
includes one startup per batch, JSON, processing and PHP validation. Twig,
Doctrine queries, page rendering, HTTP, file publication and search indexing
are outside these timings. TOC measures `SplitContent` preparation/getBody(),
including heading-ID injection, not the final `getToc()` menu render.

### Markdown: large opportunity on cache misses, incomplete compatibility

The timed subset is byte-identical. The original 70-case corpus scored 67/70
with the custom Comrak formatter before the obfuscated-link and contact ports.
Link, block and list-item attributes, Unicode IDs, empty table heads and
colspan markers matched the PHP reference then. The older
`2026-09-12-comrak-tempest.json` is a historical 49/59 measurement; it has not
been rewritten as a current benchmark.

On the read-only Altimood snapshot, 58,997 of 64,509 post-Twig Markdown blocks
(91.46%) are byte-identical. Of 1,455 pages containing such blocks, 401 have
every block identical. The other 5,512 blocks all contain at least one known
site-dependent feature: obfuscated links, notices, images, phone or email
autolinks, or date shortcodes. Feature counts overlap. The downstream site has
its own installed Pushword version, so this is a site compatibility audit, not
a proof of parity against the current monorepo PHP renderer. Full aggregate
counts and hashes are in `benchmarks/2026-09-13-comrak-altimood.json`.

The raw Comrak ratio on the 9.8 KB subset remains a parsing opportunity, not
a whole-site speedup. The opt-in hybrid path declines unsupported blocks to
the PHP converter. A future typed batch of deferred rendering operations
could reduce the remaining notice and media fallbacks.

The subsequent obfuscated-link and contact-markup ports keep the PHP templates
for e-mail, telephone and obfuscated links fixed to Pushword's core components.
The 64,509-block Altimood snapshot remains byte-identical with PHP fallback:
61,967 blocks are accepted natively and 2,542 return to PHP. Locale is sent per
block so French telephone display matches the PHP renderer. This is a
post-Twig conversion check; full page and request parity remain unmeasured.
Three pinned single-CPU passes took a median 4.207 s with uncached PHP and
0.770 s with the hybrid path (5.47× directional conversion speedup). The
aggregate report is `benchmarks/2026-09-13-comrak-contact-altimood.json`;
Markdown memory was not measured.

To reproduce the downstream audit without committing private page content:

```sh
php packages/core/rust/benchmarks/render-markdown-downstream.php ../altimood /tmp/pushword-markdown.ndjson
python3 packages/core/rust/benchmarks/check-markdown-downstream.py /tmp/pushword-markdown.ndjson packages/core/rust/target/release/pushword-content-probe
python3 packages/core/rust/benchmarks/check-markdown-downstream.py /tmp/pushword-markdown.ndjson packages/core/rust/target/release/pushword-content-probe --supported-only
```

The first command writes a mode-0600 snapshot and refuses to overwrite one.
The second reports only aggregate counts and hashes. It measures compatibility,
not speed. Both commands use the downstream test kernel and its installed
Pushword version.

With `--supported-only`, the probe declines dynamic blocks and the audit
counts them as PHP fallbacks against the snapshot. On the current Altimood
snapshot, it accepts 58,758/64,509 blocks and declines 5,751; accepted Rust
output is byte-identical, and the PHP fallback makes all 1,455 pages with
Markdown blocks exact **at this conversion boundary**. The private local
runner also times the persistent PHP/Rust worker bridge on three CPU-2 passes:
4.031 s for uncached PHP conversion versus 1.034 s hybrid (3.90×), including
worker IPC and PHP fallback conversions. These are post-Twig block times, not
complete page or HTTP timings. Aggregate hashes and samples are in
`benchmarks/2026-09-13-comrak-hybrid-altimood.json`.

Tempest 1.2.2 renders the same article in about 1.5 ms per document in the
standalone PHP benchmark, versus the native Comrak batch measured by the probe;
the exact samples and environment are committed in the raw report. Tempest's
different output (for example, heading IDs and attribute serialization) is why
its corpus score is intentionally reported rather than folded into the
Pushword-compatible result.

### Local Altimood speed and memory trend

The private, workspace-only benchmark lives in the ignored
`benchmarks/local-altimood/` directory. Its two NDJSON snapshots, scripts,
parity mask and result files stay out of Git. After building the release
binaries, refresh the snapshots when site data or the installed Pushword PHP
implementation changes. The snapshot writers refuse to overwrite existing
files, so preserve old results and remove or rename the old private snapshots
before refreshing them. Run these commands from the monorepo root:

```sh
mkdir -p packages/core/rust/benchmarks/local-altimood
php packages/core/rust/benchmarks/render-downstream.php ../altimood "$PWD/packages/core/rust/benchmarks/local-altimood/split.ndjson"
php packages/core/rust/benchmarks/render-markdown-downstream.php ../altimood "$PWD/packages/core/rust/benchmarks/local-altimood/markdown.ndjson"
python3 packages/core/rust/benchmarks/local-altimood/run.py split --cpu 2 > packages/core/rust/benchmarks/local-altimood/split-result.json
python3 packages/core/rust/benchmarks/local-altimood/run.py markdown --cpu 2 > packages/core/rust/benchmarks/local-altimood/markdown-result.json
python3 packages/core/rust/benchmarks/local-altimood/run.py hybrid --cpu 2 > packages/core/rust/benchmarks/local-altimood/hybrid-result.json
```

Choose an available CPU or omit `--cpu`. Each runner performs three passes
and records snapshot, binary and revision hashes. `split` checks the SHA-256
digest of every complete PHP and Rust result, then samples peak RSS for the
PHP process and its child. `markdown` measures uncached downstream PHP
`transform()` calls and Comrak probe batches on post-Twig blocks. It reports
the full corpus and the subset that matched the snapshot PHP output byte for
byte; it also detects drift in the current PHP output. Its timings cover only
conversion: neither route runs the complete page renderer. The PHP source is
the version installed in `../altimood/vendor`, so update that dependency when
comparing changes to Pushword PHP itself.

On the 13 September 2026 local snapshots, with three passes pinned to CPU 2:

| Workload | PHP median | Rust median | Directional ratio | Output parity | Memory |
|---|---:|---:|---:|---|---|
| Complete split, 1,474 pages | 6.903 s | 2.164 s | 3.19× | 1,474/1,474 exact | Sampled tree peak 66.0 vs 73.2 MiB (+10.9%) |
| Markdown, all 64,509 blocks | 4.072 s | 0.420 s | 9.70× | 58,997/64,509 blocks exact | Not measured |
| Markdown, 58,997 matching blocks only | 3.173 s | 0.364 s | 8.71× | Exact blocks on this snapshot | Not measured |
| Markdown hybrid, 64,509 blocks | 4.031 s | 1.034 s | 3.90× | 64,509/64,509 blocks exact | Not measured |

The split snapshot SHA-256 is `3858ceac4e22b84479f3387730ec2359261aeb1b9984314b595c7d36bbc6ba6c`;
the Markdown snapshot SHA-256 is `538e6619ebaf6506131a7f18877fe85399a3d487181de75eef9966c773064d9e`.

The Markdown ratios are **component-level trends, not validated site gains**.
Raw Comrak differs for 5,512 blocks and only 401/1,455 pages have complete
raw block parity. The hybrid route returns those blocks and 239 other
conservative declines to PHP, so its post-Twig block output is fully exact on
this snapshot. It does not establish whole-page equivalence. PHP timing
excludes downstream kernel startup and snapshot decoding; the raw Rust timing
includes probe startup, IPC and JSON decoding per batch, while the hybrid
timing uses the reused `NativeWorker` plus PHP fallback calls. The split memory
figure is a sampled process-tree RSS peak, not PHP's Zend allocation counter.
Repeated results are meaningful only with the same snapshot hash, site version,
CPU affinity and binary.

### Whole-page Altimood rendering

The separate end-to-end runner compares the **current monorepo PHP source** with
the same source plus Rust Markdown and split analysis. It backs up Altimood's
SQLite database into temporary PHP/Rust site copies, snapshots the two Pushword
source trees and native binary, and leaves the original site untouched. Each
case checks HTTP 200 and byte-identical complete HTML after a cleared fragment
cache and again with warm fragments. Rust runs must start an observed child
process. The site copies add the OAuth and Snippet bundles required by the
current monorepo, since Altimood's installed packages are older.

```sh
python3 packages/core/rust/benchmarks/e2e-altimood.py --site ../altimood --cpu 5 --runs 3 \
  --url https://altimood.com/ \
  --url https://altimood.com/refuges-tour-du-mont-blanc \
  --url https://altimood.com/tour-du-mont-blanc \
  --url https://altimood.com/blog/randonnees-hautes-alpes \
  --url https://altimood.com/quiz-montagne-france \
  --url https://us.altimood.com/tour-du-mont-blanc-mountain-huts \
  > packages/core/rust/benchmarks/local-altimood/e2e-result.json
```

The 13 September 2026 aggregate report is
`benchmarks/2026-09-13-altimood-e2e.json`: six pages, three render paths,
three rounds, **108/108 exact HTTP 200 HTML pairs**. The sums below add the six
per-page medians; RSS is the median sampled peak of the PHP process plus child.

| Path | Fragment-cold PHP → Rust | Warm PHP → Rust | Tree RSS PHP → Rust |
|---|---:|---:|---:|
| Public request | 2,657.4 → 2,515.7 ms (1.06×) | 224.9 → 247.4 ms (0.91×) | 130.1 → 136.7 MiB |
| Preview `showPage()` | 1,433.2 → 1,175.6 ms (1.22×) | 27.2 → 21.8 ms (1.25×) | 123.4 → 131.7 MiB |
| Static page render | 1,314.4 → 1,169.4 ms (1.12×) | 25.8 → 22.1 ms (1.17×) | 123.5 → 131.9 MiB |

The long Markdown pages improve; the homepage and quiz do not. The public warm
path is slower with Rust on this sample. These are directional measurements in
a Symfony test kernel with debug disabled, not production HTTP measurements.
Preview measures the `showPage()` call used by admin editing, not the whole
authenticated admin request. Static render uses `StaticPageRenderer`, but does
not include file writes, minification, feeds or other export work. The sampled
RSS includes kernel boot and the native worker. The report records source,
binary and site hashes so later PHP changes can be compared on the same data.

### TOC: algorithmic improvement applies to PHP too

`TOC\UniqueSlugger::makeSlug()` restarts a numeric suffix search for every heading
and uses `in_array()` over all previously used slugs. Repeated headings amplify
that work. `IndexedUniqueSlugger` now caches ICU base slugs and indexes used
suffixes in both backends. A 2,000-assertion comparison with the upstream slugger
covers collisions, numeric strings, Unicode, empty slugs and reset. The aggregate
benchmark includes a separate 800-identical-heading slugging measurement to
distinguish this PHP algorithmic gain from native HTML analysis.

### Search/scanning and admin

Plain-text extraction alone costs 0.234 ms on the article; its PHP regex/tag
operations already execute largely in native libraries. A standalone Rust call
needs to beat that plus transport. Parsing HTML once for headings, links and
search text could be useful where those consumers really share an input, but
must preserve their different exclusion and normalization rules.

Admin preview and static generation share native analysis when using `mainContentSplit`.
Admin list/search/forms, SQL, permissions and Twig remain separate workloads;
these measurements establish no improvement for them or for cached public hits.

## Markdown blocks and Tempest PR #24

The proposed `parseMany()` API in [Tempest Markdown](https://github.com/tempestphp/markdown)
splits one source document on `<!-- next -->` or `<!-- next: name -->`, parses
each chunk independently and exposes a named collection. Pushword adopts the
idea of one aggregate result, **not these markers or independent Markdown
chunk parsing**. Its `SplitContent` boundary receives HTML after Markdown/Twig;
the new `splitMany` batches that operation without changing authors' documents.
Tempest is therefore retained as a Markdown benchmark, not added as another HTML
analyzer. Its configurable rules could support a future separate compatibility
effort, but its measured generic output is not a drop-in Pushword replacement.
