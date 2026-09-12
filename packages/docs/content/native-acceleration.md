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

The first opt-in integration is HTML minification in `pushword/static-generator`.
It defaults to PHP. The Rust backend is experimental: build and test it on the
deployment platform before enabling it. No native binaries are downloaded by Composer.

## Organization decision

Keep the first port in **`packages/static-generator/rust/`**, alongside its PHP
owner. Its source, protocol, differential corpus and build commands are versioned
in the Pushword monorepo and travel with the static-generator package.

| Organization | Benefit | Cost and decision |
|---|---|---|
| A new Composer package `pushword/rust` immediately | One place for native distribution and shared IPC | With one operation it adds a release/dependency boundary and gives a language, rather than a feature, ownership of behavior. Defer. |
| Rust directory inside each affected package | PHP and Rust behavior can change in one package release; optional packages stay optional | Chosen for the first port. Do not copy the worker transport into a second package. |
| Shared Cargo workspace with domain crates | A later executable can combine operations without duplicating parsers or IPC | Introduce when a second measured port needs it. Keep domain crates and parity tests with their PHP packages; extract only shared transport/build code then. |

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

Tests compare exact PHP/Rust outputs for fixed and generated cases and two real
development-site generation. They cover UTF-8, inline spacing, namespaces, URI
attributes, code blocks, templates and native failure handling. Testing PHP with
`proc_open` disabled verifies the shared-hosting path. The adapter and Rust binary
are experimental; regular PHP tests require no Rust tooling. The separate native
suite fails if its binary is absent and is run in the PHP 8.4/8.5 CI jobs on Node 24.

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

## Subsequent port candidates

| Area | Candidate boundary | PHP behavior that must be preserved |
|---|---|---|
| Public rendering, editor preview, static builds and search ingestion | Markdown conversion after Twig evaluation; later fuse adjacent pure content passes | Pushword attributes, links, media rendering, notices, code handling and cache versioning; a generic CommonMark converter is insufficient |
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
certification across all PHP/libxml versions. Public dynamic rendering, Markdown,
search and admin form processing still use their existing PHP implementations.
