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

This is an experimental design and local PoC, not a released backend or a new
application configuration option. Current Pushword runtime behavior is unchanged.

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
Starting one process for every tiny filter or database getter is unlikely to be
useful. The first PoC uses batches; a persistent worker or extension would be a
separate measured deployment choice, never a requirement for PHP-only hosting.

## First implemented experiment: HTML minification

The local, separately versioned `PoC/` repository contains a Rust port of
`StaticGenerator\Generator\HtmlMinifier::compress`, plus a PHP adapter whose
default path calls the existing PHP implementation. It ports comment removal,
protected `pre`/`code`/`script`/`textarea` handling, whitespace reduction and
serialization compatibility rules. It uses an HTML parser library, not a
complete external site generator.

Tests compare exact PHP/Rust outputs for fixed and generated cases and two real
development-site pages. They cover UTF-8, inline spacing, namespaces, URI
attributes, code blocks, templates and native failure handling. Testing PHP with
`proc_open` disabled verifies the shared-hosting path. The adapter and Rust binary
are experimental and are not wired into the production generator.

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
and a distribution/update strategy for supported native platforms. The local
PoC does not yet provide production integration, cross-platform binaries or
compatibility certification across all PHP/libxml versions.
