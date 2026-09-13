# Native rendered-page scanning

This is an experimental, opt-in page-scanner backend. Normal `pw:page-scan`
still uses PHP. The worker accepts already rendered HTML and returns
six kinds of facts the scanner currently extracts separately:

- `<a href>` values for the link graph;
- labels of images without alt text;
- `id` and `name` attribute values for same-page anchor checks;
- raw `href`, `src`, `data-rot`, `data-img` and `data-bg` attributes for link checks;
- raw `srcset`, `imagesrcset` and `data-srcset` values for responsive image checks;
- distinct unresolved `date(...)` shortcodes outside literal HTML blocks.

All but anchor collection follow the existing regex rules; anchors use an HTML5
parser. The scanner uses the facts for same-page targets, image-alt findings,
link-graph edges, link candidates and unresolved date-shortcode findings. If the
worker or protocol fails, the scanner uses its PHP implementations for the
remainder of the service lifetime.
The linked-attribute extractor preserves the current PHP regex's unusual handling
of empty quoted values, which can span markup until the next quote. Changing that
rule should be a separate PHP-and-Rust behaviour fix. The benchmark compares all
fact arrays against the PHP scanner rules on every page it reads. PHP still owns
URL resolution, database lookups, external requests, translations, ignore rules,
deobfuscation, classification, deduplication and report formatting. No site content
or static output is changed by the benchmark.

To enable the worker after building it, configure the page-scanner bundle and
clear the Symfony container cache:

```yaml
pushword_page_scanner:
    native_page_facts: '/opt/pushword/bin/pushword-page-facts'
    native_page_facts_timeout: 5.0
```

Deploy the executable at that path on a compatible platform. Leave the option
unset on PHP-only hosting. A missing or failing binary logs one warning and
falls back to PHP; `ResetInterface::reset()` permits a later retry.

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
| altimood | 1,219 | 93.7 MiB | 3.109 s | 2.086 s | 2.018 s |
| GrandAngle | 1,731 | 452.6 MiB | 14.578 s | 9.253 s | 10.232 s |

All returned facts matched on both corpora. These are component timings, not a
`pw:page-scan` speedup: the command also renders pages, checks links against the
database, and may make network requests. Static HTML is representative output,
but the scanner may render different pages at another time. The PHP reference
collects all anchor attributes in one DOM query; the current scanner performs
queries per anchor target, so its exact cost can differ. Across five local runs
of the development app's 16 published pages, a warmed scan service with external
checks disabled took 0.024–0.084 s with PHP and 0.013–0.084 s with the native worker
(four alternating rounds per run, full reports and link graph equal). One run
showed no gain amid variable local load. This is a small service-level sample,
not a `pw:page-scan` command measurement, and does not predict results for larger
or network-bound sites.
