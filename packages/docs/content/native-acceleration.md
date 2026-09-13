---
title: 'Optional Rust acceleration'
h1: 'Porting Pushword functionality to Rust while keeping PHP'
publishedAt: '2026-09-12 00:00'
---

The intended architecture keeps **one Pushword feature set with PHP and optional
Rust implementations of selected expensive operations**. PHP remains sufficient
for shared hosting: installing, editing, previewing and publishing must not
require Cargo, a native executable, FFI, a PHP extension or a background daemon.
Using Hugo or Zola as a replacement publisher is outside this direction.

Opt-in integrations cover HTML minification in `pushword/static-generator`,
aggregate `SplitContent` analysis and experimental Markdown conversion in
`pushword/core`. All default to PHP.
The Rust backends are experimental: build and test them on the
deployment platform before enabling it. No native binaries are downloaded by Composer.

## Organization decision

Keep domain implementations in **`packages/<owner>/rust/`**, alongside their PHP
owners. Source, protocols, differential corpora and build commands travel with
the owning Composer package. Share only the PHP worker transport in core.

| Organization | Benefit | Cost and decision |
|---|---|---|
| A new Composer package `pushword/rust` | One place for native distribution | Adds a release/dependency boundary and gives a language, rather than a feature, ownership of behavior. Not needed for the current integrations. |
| Rust directory inside each affected package | PHP and Rust behavior change in one package release; optional packages stay optional | Chosen. `Core\Service\NativeWorker` shares bounded process transport without duplicating it. |
| Shared Cargo workspace with domain crates | A later executable can combine operations | Defer until a combined executable has a measured benefit. Current binaries have different parsers and independent activation. |

`HtmlMinifier` remains the PHP reference API. The injected `HtmlMinification`
service chooses the backend; normal pages, pagination and localized error pages
use that service. The Rust crate exposes a library plus the
`pushword-html-minifier` executable. The ignored, separately versioned `PoC/`
repository is historical research, not a runtime dependency or another source
of maintained production implementations.

Build instructions, the wire contract, tests and measured results live in
`packages/static-generator/rust/README.md`.

## Enable the experiment

Build on the target platform (or a compatible build machine):

```sh
make -C vendor/pushword/static-generator/rust build
```

Copy `target/release/pushword-html-minifier` from that directory to a trusted,
executable deployment path, then configure the bundle:

```yaml
static_generator:
    native_html_minifier: '/opt/pushword/bin/pushword-html-minifier'
    native_html_minifier_timeout: 5.0
```

Rebuild the Symfony container for the environment used by generation workers
(`php bin/console cache:clear --env=prod`). Leave `native_html_minifier` unset or
null on PHP-only hosting. The path is deployment configuration, not an editable
page/site property. Existing static files are unaffected until regenerated.

## The compatibility boundary

Port deterministic operations with explicit inputs and outputs before application
services. PHP continues to own publication policy, host and locale resolution,
authorization, Doctrine, Twig functions and extension hooks. A Rust operation
receives already resolved data; it does not independently reinterpret who may
publish a page or access another site's content.

The PHP implementation is a maintained backend, with the same regression corpus
as Rust. Native execution is explicitly enabled only where deployment supports
it. Pure operations can fall back to PHP for the whole input if the executable is
absent, execution is disabled, a deadline expires or the native response fails
validation. Partial native output must not be published. Such fallback behavior
does not establish semantic parity: differential tests must compare successful
outputs as well.

Rust should be called at an operation boundary that amortizes communication.
The integrated service lazily starts a child process and reuses it across pages;
it also accepts batches. This is a child of the PHP process, not a daemon to
install. A Symfony service reset stops it. A failed request logs one warning,
returns the PHP result for the whole batch and disables native retries until
reset. Single-page writes and incremental-state updates keep their existing order.

## First implemented port: HTML minification

The static-generator package contains a Rust port of
`StaticGenerator\Generator\HtmlMinifier::compress`. It ports comment removal,
protected `pre`/`code`/`script`/`textarea` handling, whitespace reduction and
serialization compatibility rules. It uses an HTML parser library, not a
complete external site generator.
The adapter checks a URI serialization sample against the running PHP/libxml
version before using native output. If it differs, that service uses PHP until
reset so libxml's URI escaping does not change the published output.

Tests compare exact PHP/Rust outputs for fixed and generated cases and two real
development-site builds. They cover UTF-8, inline spacing, namespaces, URI
attributes, code blocks, templates and native failure handling. Testing PHP with
`proc_open` disabled verifies the shared-hosting path. The adapter and Rust binary
are experimental; regular PHP tests require no Rust tooling. The separate native
suite fails if its binary is absent and is run in the PHP 8.5 CI jobs on Node 24.

A local run on PHP 8.5.9/libxml 2.12.10 and Rust 1.98.0 measured 470 ms for PHP
versus 291 ms for a reused Rust worker across 1,000 calls (about 38% less time).
This repeats two already rendered development pages on one CPU, with five
samples, and includes native startup and IPC. A single call was slower in Rust:
1.48 ms versus 0.64 ms. These are minification timings, not whole-site build or
HTTP latency gains; the raw samples are committed with the crate.

The initial measured build attributed around 12% of its time to HTML minification.
Even a large gain on that component therefore saves only a fraction of the full
build. Whole-operation measurements must include PHP serialization, native
startup, processing and result validation. The PoC records these costs instead
of reporting only a Rust inner-loop timing.

Rust checks now include debug/release tests, three property tests with 512 cases
per profile, Clippy and rustdoc with warnings denied, rustfmt, forbidden
crate-local unsafe code and a weekly RustSec dependency audit. This supplements
the PHP parity, protocol failure and shared-hosting tests. Miri, sanitizers and
continuous fuzzing have not been run; the crate README records the exact scope.

## Second implemented port: aggregate content analysis

`ContentSplitter` integrates `packages/core/rust/` with the existing Twig
`mainContentSplit(page)` function. One native worker request prepares heading slots,
TOC labels and both paragraph views. `splitMany` sends all uncached documents in
one request. PHP retains exact ICU slugging and Knp menu output; validated
aggregate results are cached. The indexed slugger also improves the PHP backend.

Build `vendor/pushword/core/rust`, deploy `target/release/pushword-content-analyzer`,
then configure:

```yaml
pushword:
    native_content_analyzer: '/opt/pushword/bin/pushword-content-analyzer'
    native_content_analyzer_timeout: 5.0
```

Clear the Symfony container cache in the rendering environment. Leave this unset
on PHP-only hosting. No existing Markdown, Twig or block-marker syntax changes.

The native implementation handles the HTML structures found in a rendered
Altimood database snapshot, including SVG, picture/source, raw text and repaired
markup. It declines ambiguous documents before using their output. Declines
fall back per document; worker/protocol failures fall back for the whole batch.
The shared-hosting test disables `proc_open`. Differential tests compare all
accessors, including menu hierarchy, on 42 HTML cases, 70 rendered Markdown cases
and 160 generated supported documents. All 70 rendered Markdown cases now use
native analysis. On 1,474 actual database pages rendered through Altimood's PHP
pipeline, all 1,474 are accepted and byte-identical to PHP across the complete
`SplitContent` result. The original conservative analyzer declined 1,191 pages
(80.8%), principally due to its element whitelist, empty or SVG attributes and
normal HTML repairs. The corpus result is 100% for that snapshot, not a claim of
universal HTML compatibility or an entire CMS port.

`packages/core/rust/README.md` documents the supported boundary, protocol, build
and checks. `benchmarks/2026-09-13-split.json` records the full split measurement,
including native IPC and PHP assembly, against the improved PHP implementation.
The benchmark checks every output before reporting performance. Three uncached
passes over the Altimood snapshot take a median 6.53 s in PHP and 2.02 s with
Rust (3.23×) on one CPU. Public requests, admin forms, SQL and complete static
builds are outside those measurements. The downstream check and timing scripts
and aggregate report live beside the synthetic benchmark.

## Markdown and other follow-up exploration

The standalone `pushword-content-probe` remains a research tool. The optional
`pushword-content-analyzer` now batches eligible Markdown blocks and returns
the rest to PHP. Its README and committed aggregate reports document the
conversion boundary and compatibility gaps.

- A synthetic 9.8 KB CommonMark subset takes about 8.09 ms per document in the
  existing uncached PHP converter versus 0.408 ms in the Comrak batch, including
  startup/JSON/validation. The in-memory PHP cache hit takes 0.0026 ms: keep
  the cache and investigate acceleration of misses.
- The Comrak formatter supports ordinary obfuscated links, e-mail autolinks
  and French phone numbers using fixed core markup and the current locale.
  Media, notices, date shortcodes and obfuscated e-mail links still return to
  PHP. On 64,509 post-Twig Altimood blocks, 61,967 use Rust and 2,542 use PHP;
  all rendered blocks match the downstream PHP snapshot byte-for-byte. Three
  single-CPU passes have an uncached conversion median of 4.044 s in PHP versus
  0.741 s hybrid (5.46×), including worker IPC and PHP fallback. The aggregate
  report is in `packages/core/rust/benchmarks/2026-09-13-comrak-contact-altimood.json`.
  Complete page and request parity remain unmeasured.
- The earlier TOC probe exposed repeated list scans for duplicate IDs. The new
  indexed PHP slugger removes repeated suffix searches; the separate aggregate
  split benchmark measures native parsing against that improved baseline.
- Search text extraction takes about 0.234 ms on the article. A native standalone
  operation must beat that plus transport; investigate shared HTML parsing only
  where consumers actually process the same content.

These are single-CPU component probes, not public/admin request benchmarks.
The optional Tempest benchmark uses the same corpus and workloads. Tempest
1.2.2 has no `parseMany()` method; [PR #24](https://github.com/tempestphp/markdown/pull/24)
proposes named chunks split by `<!-- next -->` markers. Pushword takes the idea
of an aggregate result, without introducing those markers. Existing
`<!--break-->` behavior remains intact. Tempest parses Markdown rather than
already rendered HTML, so it remains a benchmark candidate for a separate
Markdown compatibility effort and is not a backend for this split operation.
The next Markdown step is to differential-test full page rendering and measure
the opt-in filter in the actual site pipeline. A typed batch of parser events
for PHP-owned rendering could later reduce whole-block fallbacks.

## Subsequent port candidates

| Area | Candidate boundary | PHP behavior that must be preserved |
|---|---|---|
| Public rendering, editor preview, static builds and search ingestion | Resolve declined Markdown constructs through typed Rust/PHP batches after Twig evaluation; later fuse adjacent pure content passes | Pushword attributes, links, media rendering, notices, code handling and cache versioning must remain identical |
| Public rendering, editor preview and static builds | Investigate Twig template rendering through [Tera](https://github.com/Keats/tera) or [Askama](https://github.com/askama-rs/askama), starting with representative site templates and measured cache misses | Template inheritance, includes, escaping, filters, functions, extension hooks, localization and site-defined templates must match before enabling a native path; retain PHP for shared hosting |
| Static publication | Batched minification, asset metadata and incremental output planning | URL mapping, published snapshots, removals, redirects and atomic file replacement |
| Scanning and indexing | Extract links, headings and searchable text from rendered content in one pass | Exclusions, anchor rules, index fields and host/locale separation; external network checks are another workload |
| Flat-file processing | Parsing and comparison of input batches | Validation, revisions, relations, conflict handling and transaction ownership remain with the CMS |
| Admin | Reuse the accelerated content operations and reduce synchronous save work where semantics permit | Forms, sessions, permissions, validation and extension behavior remain identical |

Media conversion and gzip/Brotli already use native libraries or executables in
parts of the PHP implementation. Profile them before rewriting orchestration.
Public static cache hits already bypass PHP execution; native generation alone
does not make those hits faster.

Before enabling a port in production, require representative downstream-site
parity, meaningful end-to-end savings, bounded memory use, the PHP-only test path,
and a distribution/update strategy for supported native platforms. The opt-in
integration does not yet provide cross-platform release binaries or compatibility
certification across all PHP/libxml versions. PHP still orchestrates dynamic
rendering, Markdown, search and admin forms; only the selected operations use Rust.
