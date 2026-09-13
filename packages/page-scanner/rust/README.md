# Rendered-page scanner prototype

This is a measurement prototype, not a configured page-scanner backend. Normal
`pw:page-scan` still uses PHP. The worker accepts already rendered HTML and returns
three kinds of facts the scanner currently extracts separately:

- `<a href>` values for the link graph;
- labels of images without alt text;
- `id` and `name` attribute values for same-page anchor checks.

The first two follow the existing regex rules; the third uses an HTML5 parser.
The benchmark compares the complete fact arrays against the PHP scanner rules
on every page it reads. PHP still owns URL resolution, database lookups, external
requests, translations, ignore rules, and report formatting. No site content or
static output is changed by the benchmark.

## Build and measure

From the repository root:

```sh
cargo test --manifest-path packages/page-scanner/rust/Cargo.toml
cargo build --release --manifest-path packages/page-scanner/rust/Cargo.toml
php -d opcache.enable_cli=1 -d xdebug.mode=off \
  packages/page-scanner/rust/bench.php ../altimood/static ../GrandAngle/Code/GrandAngle/static
```

The harness reuses one worker across documents and another across batches of
eight. It measures PHP fact extraction and Rust requests, including worker
startup, JSON transport, parsing, and response validation. File reads and
comparison are outside the timers. An output mismatch aborts the run. The
16 MiB `NativeWorker` request/response cap still applies; these two corpora fit.

One local run on PHP 8.5.10 and Rust 1.98.0:

| Corpus | HTML | Bytes | PHP | Rust, one page/request | Rust, eight pages/request |
|---|---:|---:|---:|---:|---:|
| altimood | 1,219 | 93.7 MiB | 2.025 s | 1.186 s | 1.140 s |
| GrandAngle | 1,731 | 452.6 MiB | 9.393 s | 5.917 s | 6.527 s |

All returned facts matched on both corpora. These are component timings, not a
`pw:page-scan` speedup: the command also renders pages, checks links against the
database, and may make network requests. Static HTML is representative output,
but the scanner may render different pages at another time. The PHP reference
collects all anchor attributes in one DOM query; the current scanner performs
queries per anchor target, so its exact cost can differ. The next decision is
whether an opt-in scanner adapter saves meaningful end-to-end time on a full
scan with external requests disabled. Keep the PHP path for shared hosting.
