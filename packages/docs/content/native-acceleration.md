---
title: 'Optional Rust acceleration'
h1: 'Porting Pushword functionality to Rust while keeping PHP'
publishedAt: '2026-09-12 00:00'
---

Pushword keeps **one feature set with PHP and optional Rust implementations of selected
expensive operations**. PHP stays sufficient for shared hosting: installing, editing,
previewing and publishing never require Cargo, a native executable, FFI, a PHP extension
or a daemon. Replacing the publisher with Hugo or Zola is out of scope.

Three opt-in integrations exist, all defaulting to PHP and all experimental — build and
test them on the deployment platform before enabling them. Composer downloads no native
binaries.

| Package | Operation | Executable |
|---|---|---|
| `pushword/static-generator` | HTML minification | `pushword-html-minifier` |
| `pushword/core` | aggregate `SplitContent` analysis, experimental Markdown conversion | `pushword-content-analyzer` |
| `pushword/page-scanner` | rendered-page fact extraction | `pushword-page-facts` |

## Enable an integration

Build on the target platform (or a compatible machine), copy the executable from
`target/release/` to a trusted path, configure it, then clear the container cache of the
environment that runs it (`php bin/console cache:clear --env=prod`). Leave the key unset
on PHP-only hosting. Each `*_timeout` defaults to 5 seconds. The path is deployment
configuration, not a page or site property.

```sh
make -C vendor/pushword/static-generator/rust build
make -C vendor/pushword/core/rust build
cargo build --release --manifest-path vendor/pushword/page-scanner/rust/Cargo.toml
```

```yaml
static_generator:
    native_html_minifier: '/opt/pushword/bin/pushword-html-minifier'
    native_html_minifier_timeout: 5.0

pushword:
    native_content_analyzer: '/opt/pushword/bin/pushword-content-analyzer'
    native_content_analyzer_timeout: 5.0

pushword_page_scanner:
    native_page_facts: '/opt/pushword/bin/pushword-page-facts'
    native_page_facts_timeout: 5.0
```

Existing static files are unaffected until regenerated. No Markdown, Twig or block-marker
syntax changes.

## Organization

Rust code lives in **`packages/<owner>/rust/`**, beside its PHP owner: source, protocol,
differential corpora and build commands ship with the owning Composer package. Only the
PHP worker transport (`Core\Service\NativeWorker`) is shared, in core.

| Option | Benefit | Decision |
|---|---|---|
| A `pushword/rust` package | One place for native distribution | Rejected: adds a release boundary and gives a language, not a feature, ownership of behaviour. |
| A `rust/` directory per package | PHP and Rust change in one release; optional packages stay optional | Chosen. |
| A shared Cargo workspace | A later executable could combine operations | Deferred until a combined executable shows a measured benefit. |

The ignored, separately versioned `PoC/` repository is historical research, not a
runtime dependency.

## The compatibility boundary

Port deterministic operations with explicit inputs and outputs. PHP keeps publication
policy, host and locale resolution, authorization, Doctrine, Twig functions and extension
hooks; a Rust operation receives already resolved data.

The PHP implementation is a maintained backend sharing Rust's regression corpus. When the
executable is absent, disabled, times out or returns output that fails validation, the
whole input falls back to PHP — partial native output is never published. Fallback does
not prove parity: differential tests compare successful outputs too.

The integrated service lazily starts one child process of PHP, reuses it across pages and
accepts batches. A Symfony service reset stops it. A failed request logs one warning,
returns the PHP result for the whole batch and disables native retries until reset.

## HTML minification

The crate ports `StaticGenerator\Generator\HtmlMinifier::compress` (still the PHP
reference API): comment removal, protected `pre`/`code`/`script`/`textarea`, whitespace
reduction and serialization rules, on an HTML parser library. The injected
`HtmlMinification` service picks the backend for normal, paginated and localized error
pages. Before using native output it checks a URI serialization sample against the
running PHP/libxml; on a mismatch it uses PHP until reset.

Measured locally (PHP 8.5.9/libxml 2.12.10, Rust 1.98.0): 470 ms in PHP versus 291 ms
for a reused Rust worker over 1,000 calls, IPC included; a single call is slower in Rust
(1.48 ms versus 0.64 ms). Minification was ~12% of the measured build, so even a large
gain saves a fraction of it. `packages/static-generator/rust/README.md` holds the build
instructions, wire contract, test scope and raw samples.

## Aggregate content analysis

`ContentSplitter` backs the Twig `mainContentSplit(page)` function with
`packages/core/rust/`. One worker request prepares heading slots, TOC labels and both
paragraph views; `splitMany` sends every uncached document in one request. PHP keeps ICU
slugging and Knp menu output; validated results are cached.

The worker handles SVG, picture/source, raw text and repaired markup, and declines
ambiguous documents. Declines fall back per document; worker or protocol failures fall
back for the whole batch. The analyzer also batches eligible Markdown blocks and returns
the rest to PHP.

`packages/core/rust/README.md` documents the boundary, protocol, build and checks;
`benchmarks/2026-09-13-split.json` records the split measurement against the PHP
implementation.

## Page facts

The page-scanner worker prepares links from rendered HTML — `data-rot` decoding,
responsive-image URLs, crawlability — and extracts missing-alt labels, same-page anchors
and unresolved date shortcodes. PHP still does URL checks, database lookups and report
formatting. `packages/page-scanner/rust/README.md` records the protocol, corpus parity
checks and measurement limits.

## Exploration results

Single-CPU component probes, not request benchmarks:

- A synthetic 9.8 KB CommonMark document: ~8.09 ms in the uncached PHP converter versus
  0.408 ms in the Comrak batch (startup, JSON and validation included). A PHP cache hit
  takes 0.0026 ms — keep the cache, accelerate misses.
- A 24,000-block synthetic corpus: CommonMark 1.381 s, Tempest (now the default PHP
  renderer) 0.390 s, Rust with PHP fallback 0.166 s, byte-identical HTML. Rust accepts
  22,000 blocks and falls back for 2,000.
- Search text extraction takes ~0.234 ms per article; a native operation must beat that
  plus transport.

## Port candidates

| Area | Candidate boundary | PHP behaviour to preserve |
|---|---|---|
| Rendering, preview, static builds, search ingestion | Resolve declined Markdown constructs in typed Rust/PHP batches after Twig; later fuse adjacent pure content passes | Pushword attributes, links, media rendering, notices, code handling, cache versioning |
| Rendering, preview, static builds | Twig templates through [Tera](https://github.com/Keats/tera) or [Askama](https://github.com/askama-rs/askama), starting with measured cache misses | Inheritance, includes, escaping, filters, functions, extension hooks, localization, site templates; PHP stays for shared hosting |
| Static publication | Batched minification, asset metadata, incremental output planning | URL mapping, published snapshots, removals, redirects, atomic file replacement |
| Scanning and indexing | Links, headings and searchable text in one pass | Exclusions, anchor rules, index fields, host/locale separation |
| Flat files | Parsing and comparing input batches | Validation, revisions, relations, conflicts, transactions |
| Admin | Reuse accelerated content operations | Forms, sessions, permissions, validation, extensions |

Media conversion and gzip/Brotli already use native libraries; profile before rewriting
their orchestration. Static cache hits already bypass PHP.

Before enabling a port in production, require downstream-site parity, end-to-end savings,
bounded memory, the PHP-only test path and a distribution strategy for native platforms —
there are no cross-platform release binaries yet.
