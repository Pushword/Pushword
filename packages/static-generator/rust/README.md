# Pushword HTML minifier

Experimental, optional Rust implementation owned by `pushword/static-generator`.
PHP remains the default and complete implementation, including shared hosting
without `proc_open`, Cargo, FFI or a native executable. No Hugo or Zola dependency.

## Layout and ownership

- `src/lib.rs`: the port of `../src/Generator/HtmlMinifier.php`, including the
  legacy libxml serialization rules used by PHP after HTML5 parsing.
- `src/main.rs`: a private stdin/stdout worker; no HTTP server, filesystem writes,
  database access or publication policy.
- `../src/Generator/HtmlMinification.php`: PHP selection, transport and fallback.
- `tests/corpus.json`: 233 fixed and seeded examples shared by differential tests.
- `tests/`: the explicit native suite, including actual static publication.
- `../tests/Generator/HtmlMinificationTest.php`: PHP-only transport/failure tests
  using a small fake executable; normal Pushword tests require no Rust build.

Keep behavioral changes and regressions in this package. If another package
needs native execution, consider a Cargo workspace and shared transport then;
do not duplicate this service or create a generic operation registry in advance.
The older `PoC/` is a frozen research history, not required for any command here.

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
