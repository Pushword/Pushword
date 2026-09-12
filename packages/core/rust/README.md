# Pushword content analysis and Markdown exploration

This crate contains two separate executables, owned by `pushword/core`:

- `pushword-content-analyzer`: opt-in HTML analysis used by `ContentSplitter`.
  It prepares heading slots, labels and paragraph views in one HTML parse.
- `pushword-content-probe`: standalone [Comrak](https://github.com/kivikakk/comrak)
  Markdown compatibility and performance research. It never renders site content.

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
Markdown, Twig functions, media resolution and authorization remain in PHP.

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

Rust uses scraper/html5ever, accepts only balanced canonical fragments whose
serialization is byte-identical, and bounds traversal depth at 128. Common
paragraphs, root headings, lists, inline formatting, figures and explicit table
sections are supported. Foreign elements, scripts, templates, repaired markup,
nested headings/breaks and ambiguous literal split markers fall back to PHP.
This is a deliberate eligibility check, not HTML sanitization. In the current
59-case rendered Markdown corpus, 49 documents use native analysis; all 59
produce the same complete result through the hybrid service.

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
34 fixed HTML cases, the 59 rendered Markdown cases and 160 generated documents.
Generated supported documents must actually use Rust, so fallback cannot hide
a native failure. Tests cover batch/cache/reset behavior, malformed and oversized
protocol frames, large pipe responses, the Markdown/Twig pipeline and a PHP
subprocess with `proc_open` disabled. The Comrak probe separately records its
compatibility gaps and measures Markdown, TOC and search extraction.

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
| Short, 421 B | 0.400 | 0.066 | 0.035 | 0.173 | 0.006 |
| Article, 15.9 KB | 10.756 | 0.944 | 0.934 | 5.020 | 0.015 |
| Long, 160 KB | 109.097 | 11.631 | 11.605 | 52.447 | 0.077 |
| 800 identical headings, 161 KB | 108.618 | 10.116 | 10.716 | 53.022 | 0.081 |

On these synthetic inputs the uncached aggregate is about 6–11 times faster.
The separate slugging microbenchmark drops from 153.7 ms to 0.081 ms for 800
identical headings; this gain also applies on PHP-only hosting. Neither ratio
is a whole-page or whole-site speedup.

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

The timed subset is byte-identical. The current corpus is 49/59 byte-identical
with the custom Comrak formatter. The ten recorded gaps cover Pushword-specific
dynamic links, media/notices, block attributes, Unicode IDs, table edge cases
and other extension details. The corpus is a compatibility map, not a promise
that a generic CommonMark converter can replace Pushword's renderer.

The Comrak ratio on the 9.8 KB subset is a parsing opportunity, not a drop-in
CMS speedup. Preserve the existing content cache. The next useful prototype is
a Rust parse stage with PHP retaining Pushword-aware rendering, or a complete
port of those renderers with explicit resolved dependencies. Measure the cost
of returning parser events/AST to PHP before choosing that split. Do not enable
a generic converter based only on these numbers.

Tempest 1.2.2 renders the same article in about 1.5 ms per document in the
standalone PHP benchmark, versus the native Comrak batch measured by the probe;
the exact samples and environment are committed in the raw report. Tempest's
different output (for example, heading IDs and attribute serialization) is why
its corpus score is intentionally reported rather than folded into the
Pushword-compatible result.

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
