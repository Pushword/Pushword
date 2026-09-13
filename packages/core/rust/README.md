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
shortcodes without PHP-provided values and obfuscated e-mail links. It also
declines inline code inside tables and a top-level fenced block immediately
following a paragraph, where the current PHP and Rust renderers differ.
Ordinary obfuscated links, e-mail autolinks and French phone numbers use the
core markup; the worker receives the current locale and whether an admin is
browsing a dynamic site. Conservative false positives cost PHP conversion;
unseen syntax or parser differences can still cause a mismatch. Treat this
backend as experimental until complete downstream page rendering is
differential-tested.

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
analysis and match PHP across every accessor. This is a test of that corpus,
not a claim of universal HTML compatibility.

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
php packages/core/rust/benchmarks/render-downstream.php /path/to/site /tmp/pushword-downstream.ndjson
php packages/core/rust/benchmarks/check-downstream.php /tmp/pushword-downstream.ndjson
taskset -c 2 php packages/core/rust/benchmarks/time-downstream.php /tmp/pushword-downstream.ndjson
```

The first script boots the downstream test kernel and reads Doctrine pages;
it does not synchronize Markdown or change site content. The output can contain
private page text, so keep it outside the repository. `check-downstream.php`
fails on any native decline, exception or field mismatch. Regenerate the
snapshot when the site data or rendering pipeline changes.

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

## Optional Tempest comparison

The historical standalone Tempest comparison used 1.2.2 in an isolated
directory. Current Pushword uses PHP 8.5 and a temporary 1.2.3 fork for its
compatible renderer. To reproduce the older parser-only comparison, install
the historical version in the ignored benchmark directory:

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

### Three-way Markdown conversion

The default benchmark generates 24,000 deterministic Markdown blocks from 28
patterns and uses the repository's demo test kernel. The patterns include titled
links, colspan and short table rows, three-level lists, escaped brackets,
approximate quantities, ratings, phone numbers, indented table syntax, hard
line breaks, headings, and horizontal rules. Three patterns cover date
shortcodes in plain text, link labels, and text next to inline code; the
runner requires Rust to accept each of those blocks. It
calculates CommonMark's reference HTML once, outside the timed passes. No
external site or corpus is needed. The raw snapshot is temporary; the detailed
report can stay in the ignored local benchmark directory:

```sh
mkdir -p packages/core/rust/benchmarks/local
php packages/core/rust/benchmarks/three-way-markdown-runner.php --cpu 2 --runs 3 --require-native-dates \
  > packages/core/rust/benchmarks/local/synthetic-result.json
```

The input Markdown is fixed. The generated reference snapshot changes when a
date shortcode's value changes, so compare the snapshot hash recorded in each
report before comparing periodic runs.

The following medians come from three alternating passes on CPU 2 with the
24,000-block corpus on 13 September 2026 (PHP 8.5.10). All three paths produced
byte-identical HTML.

| Renderer | Conversion time | Relative speed | Sampled peak process-tree RSS | Directly rendered blocks |
|---|---:|---:|---:|---:|
| CommonMark PHP | 1.401 s | 1.00× | 97.5 MiB | 24,000 |
| Tempest compatibility renderer | 0.786 s | 1.78× | 97.8 MiB | 21,429; 2,571 PHP fallbacks |
| Rust hybrid with PHP fallback | 0.282 s | 4.97× | 104.3 MiB | 21,429; 2,571 PHP fallbacks |

The generated input is fixed and synthetic, so these ratios are useful for
repeatable component comparisons, not predictions of a site's throughput.
Conversion is uncached. Rust time includes PHP preparation of date shortcode
values, persistent-worker IPC and the PHP fallback; the RSS figure includes the
Rust child. Snapshot decoding, site switching, kernel startup, Twig and
complete page rendering are excluded from the timed conversion calls. The
synthetic corpus SHA-256 is
`1972d6e29d065b680955159c6c693a1c639d5024d7a5039e90f6b6b4cd33bb17`;
the analyzer binary SHA-256 is
`9c99ad74044e890732bdefae7ef250c233b878e01236e4d0e21f9c6f669c583e`.
All 2,571 synthetic date blocks were accepted by Rust with byte-identical HTML.

New comparison runs also report `byte_different` separately from `different`.
`different` compares parsed HTML after Pushword's typography step: text,
elements, attributes, comments and raw script/style content must match.
Attribute order, entity spelling and whitespace between table structure tags
do not matter. This comparison does not erase text whitespace or skip the
typography step; an encoded apostrophe can prevent French curly-quote conversion.
The conversion timings still exclude comparison work. The direct renderer
integration test also checks attribute-order differences through the remaining
main-content filters. A semantic match is not proof that every site template or
external HTML consumer behaves identically.
In a 24,000-block synthetic comparison, Tempest produced 2,571 byte differences
and no differences under this contract; Rust produced neither. This count is
specific to that corpus and does not establish universal compatibility.

An installed downstream site can be measured with the same runner. Its private
snapshot and result remain in the ignored local directory:

```sh
php packages/core/rust/benchmarks/render-markdown-downstream.php \
  /path/to/site packages/core/rust/benchmarks/local/site.ndjson
php packages/core/rust/benchmarks/three-way-markdown-runner.php \
  --site /path/to/site \
  --snapshot packages/core/rust/benchmarks/local/site.ndjson \
  --cpu 2 --runs 3 --require-native-dates \
  > packages/core/rust/benchmarks/local/site-result.json
```

For site snapshots, the PHP baseline uses the site's installed CommonMark
converter. Tempest uses the current monorepo renderer and the site's services.
The runner verifies every output against the snapshot and checks the digest
across all samples. Its `dates` report field shows total, native and fallback
date blocks. `--require-native-dates` also fails when the snapshot has no date
blocks or any date block falls back to PHP; omit it if the site intentionally
uses date syntax that Rust declines. The snapshot writer refuses to overwrite
an existing file.

A September 13, 2026 check used the installed site code and content snapshots
from Altimood and GrandAngle. Three serial passes on CPU 2 measured uncached
conversion, including Rust transport and PHP fallbacks but excluding page
templates, database reads and output comparison:

| Site | Blocks | Directly rendered by Rust | PHP median | Rust median | Rust byte differences |
|---|---:|---:|---:|---:|---:|
| Altimood | 64,509 | 62,108 | 5.19 s | 1.21 s | 0 |
| GrandAngle | 154,988 | 149,674 | 12.17 s | 2.86 s | 0 |

The Altimood three-way runner passed. The GrandAngle three-way runner failed
because the current Tempest renderer differed semantically from the installed
CommonMark reference on 20 blocks with ambiguous Markdown; direct PHP and Rust
mode checks had identical digests, and Rust had zero byte differences. The
Tempest differences remain a separate rendering compatibility issue. These
figures measure block conversion, not the full `pw:page-scan` command.

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
Tempest now renders the compatible Markdown path in Pushword; it is not a backend
for HTML `SplitContent`. The raw Tempest output still differs from Pushword's
serialization, so the compatibility renderer owns the required adaptation.
Full-page parity and latency remain separate checks from this component benchmark.
