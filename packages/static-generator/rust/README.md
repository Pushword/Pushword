# Pushword HTML minifier

Experimental, optional Rust implementation owned by `pushword/static-generator`.
PHP remains the default and complete implementation, including shared hosting
without `proc_open`, Cargo, FFI or a native executable. No Hugo or Zola dependency.

## Layout and ownership

- `src/lib.rs`: the port of `../src/Generator/HtmlMinifier.php`, including the
  legacy libxml serialization rules used by PHP after HTML5 parsing.
- `src/main.rs`: a private stdin/stdout worker; no HTTP server, filesystem writes,
  database access or publication policy.
- `../src/Generator/HtmlMinification.php`: PHP selection and fallback; bounded
  process transport is shared with content analysis in `Core\Service\NativeWorker`.
- `tests/corpus.json`: 233 fixed and seeded examples shared by differential tests.
- `tests/`: the explicit native suite, including actual static publication.
- `../tests/Generator/HtmlMinificationTest.php`: PHP-only transport/failure tests
  using a small fake executable; normal Pushword tests require no Rust build.

Keep behavioral changes and regressions in this package. The shared PHP worker
does not require a combined executable or a generic operation registry; core
content analysis and static minification remain independently configurable.
The older `PoC/` is a frozen research history, not required for any command here.

## Automated Rust checks

`make test` runs debug **and release** tests, Clippy on all targets with warnings
denied, rustfmt, and documentation generation with warnings denied. Crate-local
`unsafe` code is forbidden by Cargo lint configuration (this does not forbid
unsafe implementations inside third-party dependencies).

Three property tests run 512 generated cases each per profile: protected Unicode
code/whitespace, malformed markup assembled from tags and arbitrary text, and
the optimized HTML escape against the original replacement rules. Proptest
shrinks failures and records a replay seed. This supplements the PHP differential
corpus and publication tests; it is not continuous fuzzing or a coverage target.

`make audit` runs [cargo-audit](https://github.com/rustsec/rustsec/tree/main/cargo-audit)
against the pinned lockfile with warnings denied. Install its checked tool version
with `cargo install cargo-audit --version 0.22.2 --locked`. The repository security
workflow audits both Rust crates on pushes, pull requests and its weekly schedule;
an unavailable advisory database or a reported warning fails the job. The check
identifies known dependency advisories, not all possible defects.

On 2026-09-12, both lockfiles passed against advisory DB commit
`b50980aad8b8f14f77e25a97b32dd94bf008b0af` (1,243 advisories), without suppressions.
No Miri, sanitizer run, continuous fuzzing or cross-platform binary validation
has been performed. These remain separate checks before widening distribution.

## Build and enable

From a Pushword checkout with Composer dependencies installed:

```sh
make -C packages/static-generator/rust build
make -C packages/static-generator/rust test
composer test-filter 'HtmlMinificationTest|HtmlMinifierTest'
```

Use a current stable Rust toolchain with Clippy and rustfmt for development.
`Cargo.lock` pins dependencies. Build produces `target/release/pushword-html-minifier`;
deploy this executable for the target OS/architecture. Cargo is not required on
the PHP host if a compatible binary is supplied. No binary release/download
automation is provided yet.

In the application's bundle configuration:

```yaml
static_generator:
    native_html_minifier: '/opt/pushword/bin/pushword-html-minifier'
    native_html_minifier_timeout: 5.0
```

Clear the container cache in the deployment environment after changing the
configuration. The path must be trusted and executable by PHP. PHP launches an
argument array, without shell interpolation. Never take this path from page
content or site custom properties. Null or empty disables native execution.

## Protocol version 1

One UTF-8 JSON object per line on stdin, with a newline terminator:

```json
{"version":1,"id":1,"operation":"minify_html","documents":["<p>text</p>"]}
```

One flushed response line, preserving document count and order:

```json
{"version":1,"id":1,"documents":["<p>text</p>"]}
```

The worker serves requests sequentially until EOF. Invalid version, operation,
types, unknown request fields, invalid UTF-8 or unterminated input fail the
process. There is no native publishing side effect. Request IDs let PHP reject
stale responses. PHP validates framing, version, ID, count, list shape and each
string before returning any native output.

Requests are capped at 16 MiB including JSON and newline. PHP also caps response
plus stderr bytes at 16 MiB and enforces a per-request timeout. These are transport
limits, not a 16 MiB total-memory promise: DOM parsing and serialization allocate
additional memory. Large or invalid inputs retain the PHP behavior through
fallback. Native failures stop the child and log a warning once per service
lifetime/reset; subsequent calls use PHP. This avoids paying the same failing
startup/timeout for every page. A Symfony reset permits a fresh attempt.

Structural response validation is not proof of HTML equivalence. Differential
tests compare exact output with PHP, including a second pass, Unicode boundaries,
attributes/namespaces, protected code, malformed HTML and large pipe transfers.
The integration test performs two fresh static builds and compares HTML hashes,
including localized error pages. Fresh render services keep gallery/quiz instance
IDs deterministic between builds. A separate process test disables `proc_open`.

## Measurements

Run against your own already rendered pages, preferably with CPU affinity:

```sh
php -d opcache.enable_cli=1 -d xdebug.mode=off \
  packages/static-generator/rust/bench.php page.html another-page.html
```

The harness checks output equality, refuses PHP fallback, shuffles three modes
and reports five samples each. PHP is warmed by the reference computation; every
Rust sample starts a fresh worker and then reuses it. Timing includes JSON,
startup, processing and validation, and excludes fixture loading, checking,
shutdown, Twig rendering, writes, sidecars and network delivery.

Local medians, one CPU, PHP 8.5.9/libxml 2.12.10, Rust 1.98.0, repeating two
development HTML pages of 14,051 and 13,627 bytes:

| Documents | PHP calls | Reused worker, one call/page | One native batch |
|---:|---:|---:|---:|
| 1 | 0.64 ms | 1.48 ms | 1.49 ms |
| 100 | 47.88 ms | 31.94 ms | 27.45 ms |
| 1,000 | 470.22 ms | 291.26 ms | 257.07 ms |

Raw samples, input/binary hashes and machine metadata are in
`benchmarks/2026-09-12.json`. This demonstrates a minification gain, not a 38%
whole-build speedup. At the earlier profile's roughly 12% minification share,
a 38% reduction would save around 4.6% of total time if other costs stayed equal.
No gain is established for single-page requests, dynamic page rendering or admin
forms. Test representative downstream HTML and measure whole builds before
enabling the experiment on a production deployment.

## Allocation experiment

The serializer now appends escaped text directly to its destination and reserves
the initial HTML buffer. Whitespace passes reuse the owned input when a regex
finds no replacement. These changes preserve the PHP serialization contract.
The generated escape test also compares with the original three replacements.

An interleaved experiment on the same two pages (1,000 documents, one CPU,
nine samples per binary) measured 242.20 ms before and 237.33 ms after: about
2.0% less time in the native process, with eight of nine paired samples faster.
This includes native startup and stdin/stdout collection, but excludes the PHP
adapter. Separate PHP-adapter runs did not establish a reliable additional gain;
do not treat this as a further whole-build speedup.

See `benchmarks/2026-09-12-allocations.json` for raw samples and binary hashes.
Reproduce the native-only comparison on Linux with
`php packages/static-generator/rust/compare.php baseline-binary candidate-binary page.html another.html`.
To compare a previously built binary through the PHP adapter, set
`PUSHWORD_BENCH_BINARY=/path/to/baseline` when invoking `bench.php`.
