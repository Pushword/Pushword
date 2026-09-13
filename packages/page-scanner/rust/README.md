# Native rendered-page scanning

This is an experimental, opt-in page-scanner backend. Normal `pw:page-scan`
still uses PHP. The worker accepts already rendered HTML and returns
seven kinds of facts the scanner currently extracts or prepares separately:

- `<a href>` values for the link graph;
- labels of images without alt text;
- `id` and `name` attribute values for same-page anchor checks;
- deduplicated links from `href`, `src`, `data-rot`, `data-img`, `data-bg`
  and responsive-image attributes, with `data-rot` decoded and the existing URL
  filter applied;
- which of those links are crawlable;
- clear `mailto:` and `tel:` links that need an obfuscation warning;
- distinct unresolved `date(...)` shortcodes outside literal HTML blocks.

All but anchor collection follow the existing regex rules; anchors use the
`html5ever` tokenizer without building a DOM for ordinary pages. Ambiguous
structures such as duplicate document tags, orphan table tags and framesets
fall back to the existing HTML5 DOM parser. The scanner uses the facts for
same-page targets, image-alt findings, link-graph edges, prepared link
candidates and unresolved date-shortcode findings.
If the worker or protocol fails, the scanner uses its PHP implementations for the
remainder of the service lifetime.
The linked-attribute extractor preserves the current PHP regex's unusual handling
of empty quoted values, which can span markup until the next quote. Changing that
rule should be a separate PHP-and-Rust behaviour fix. The benchmark compares all
fact arrays against the PHP scanner rules on every page it reads. PHP still owns
URL resolution, database lookups, external requests, translations, ignore rules
and report formatting. No site content or static output is changed by the benchmark.

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
| altimood | 1,219 | 93.7 MiB | 3.273 s | 1.788 s | 1.712 s |
| GrandAngle | 1,731 | 452.6 MiB | 14.375 s | 7.465 s | 8.199 s |

All returned facts matched on both corpora. A separate same-process comparison
on 200 sampled pages per site measured the previous Rust worker plus PHP link
preparation against the new prepared-link worker, including JSON transport:
0.343–0.366 s versus 0.325–0.337 s on altimood and 1.066–1.200 s versus
1.006–1.137 s on GrandAngle across three runs. Link preparation is a modest
incremental gain. These are component timings, not a `pw:page-scan` speedup: the
command also renders pages, checks links against the
database, and may make network requests. Static HTML is representative output,
but the scanner may render different pages at another time. The PHP reference
collects all anchor attributes in one DOM query; the current scanner performs
queries per anchor target, so its exact cost can differ. Across five local runs
of the development app's 16 published pages, a warmed scan service with external
checks disabled took 0.019–0.020 s with PHP and 0.012–0.013 s with the native worker
(four alternating rounds per run, full reports and link graph equal). This is a
small service-level sample,
not a `pw:page-scan` command measurement, and does not predict results for larger
or network-bound sites.

## Streaming anchor extraction

A same-process comparison of the previous full-DOM worker and the streaming
worker ran four alternating rounds over 200 sampled pages per site. Inputs were
preloaded; the timings cover all Rust fact extraction but exclude JSON transport.
All fact arrays were identical:

| Corpus | Full DOM, median | Streaming anchors, median |
|---|---:|---:|
| altimood | 0.245 s | 0.200 s |
| GrandAngle | 0.828 s | 0.678 s |

The full-corpus harness also found exact PHP/Rust parity for all 2,950 HTML
files. Unit tests compare anchor extraction with the previous DOM path on
malformed cases and 10,000 reproducible mixed-markup documents. On the
development app's 32 pages, a warmed `pw:page-scan --skip-external` took
0.25–0.27 s with either worker; this small command-level sample shows no
stable end-to-end gain.

## Whole-site PHP phase profile

On September 13, 2026, the installed PHP scanners on Altimood and GrandAngle
were instrumented during full `pw:page-scan --skip-external` runs across all
hosts. Both sites had cached Symfony containers and warm application caches;
GrandAngle's stale dev container was cleared before measuring. These installed
scanner versions predate the native facts adapter, so this is a workload profile,
not a PHP-versus-Rust command benchmark. External HTTP checks were excluded.

| Site | Pages | Rendered HTML | Total | Render | Link scan | DOM construction | Link extraction | Link validation |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Altimood | 1,458 | 139.1 MB | 24.0 s | 11.8 s | 11.3 s | 3.1 s | 2.8 s | 5.3 s |
| GrandAngle | 2,479 | 702.2 MB | 133.1 s | 89.0 s | 41.1 s | 16.8 s | 16.6 s | 7.5 s |

DOM construction, extraction and validation are parts of the link-scan column,
not additional time. Internal target resolution took only 0.54 s on Altimood
and 1.20 s on GrandAngle; media lookups within that took 0.11 s and 0.65 s.
The DOM plus link extraction consumed roughly one quarter of each warm scan.
That fraction is an upper bound on the possible command gain from replacing
those PHP steps, before paying for native transport and parsing. Rendering
accounted for 49% of Altimood's warm run and 67% of GrandAngle's. Altimood's
first cold run took 41.3 s, primarily because rendering took 28.9 s rather
than 11.8 s.

The instrumented and normal Altimood scans reported identical findings. On
GrandAngle, only broken-derivative findings varied between repeated runs as
image-cache files were generated; the other finding counts were stable. The
following A/B experiment uses the current scanner source in disposable site
copies.

## Whole-site PHP/Rust A/B

On September 13, 2026, copy-on-write snapshots of both sites received this
package's current `src/` tree and `Core\Service\NativeWorker`; the original site trees
were not changed. The snapshots kept their other installed packages, so this
measures the current scanner in those sites, not a complete Composer upgrade.
An environment variable selected either the PHP fallback or the release worker.
Each site had a full PHP warm-up, then three alternating PHP/Rust pairs, run
serially on CPU 22 with `--skip-external --limit=100000 --format=agent --no-debug`.
The figures below are medians of the three command runs, with timing counters
inside the scanner and user-plus-system CPU time from `/usr/bin/time`:

| Site | PHP command | Rust command | Command gain | PHP CPU | Rust CPU | PHP link scan | Rust facts + link scan |
|---|---:|---:|---:|---:|---:|---:|---:|
| Altimood | 34.9 s | 22.8 s | 34.7% | 35.0 s | 23.2 s | 16.4 s | 3.4 + 1.9 s |
| GrandAngle | 166.5 s | 129.1 s | 22.5% | 99.3 s | 63.0 s | 52.4 s | 14.1 + 4.5 s |

Altimood's PHP command varied from 32.4 to 48.5 s, while its Rust command
ranged from 21.5 to 24.0 s; the median is more useful than one paired result.
GrandAngle was steadier: 165.7–167.1 s in PHP and 128.9–129.6 s with Rust.
Rendering still took a median 16.7 s on Altimood and 108.7 s on GrandAngle
with Rust. Native facts were returned for 1,202 and 2,399 pages respectively.

All six Altimood reports had identical findings. All six GrandAngle reports
matched after excluding `image-derivative-broken` findings, whose count moved
between runs as cache files were generated; every other error code and message
matched. Rendered HTML size was identical across Altimood runs and varied by
less than 0.01% across GrandAngle runs. The worker process was observed during
the Rust scans.

Median PHP-process peak RSS from `/usr/bin/time` was about 495 MiB on Altimood
and 566 MiB on GrandAngle. A separate Rust run sampled the combined PHP and
worker RSS every 20 ms at about 498 MiB and 565 MiB respectively, with the
worker itself peaking near 8 MiB. These are different RSS measurements, and
summed RSS can count shared pages twice; they show no material memory change,
not an exact physical-memory saving. The next deployment check is a complete
compatible package upgrade on staging, followed by the same parity and timing
comparison.

## Rendering follow-up

A sampled render of all 2,479 GrandAngle pages, with the current core media
repository loaded into the installed site, still spent about one third of its
template samples in `MediaRepository::findBySearch()`. The repository already
caches repeated search terms, but its 16-entry bound evicted product codes that
recurred later in the corpus. Raising that bound to 256 reduced template time
from 52.4 to 50.0 seconds in one serial, CPU-pinned A/B pair without sampling;
the sampled pair independently showed a 2.5-second reduction. Both variants
peaked at 304 MiB of PHP memory in the unsampled pair. This is a rendering
component measurement, not an additional measured `pw:page-scan` gain.
Whole-page hashes differed between repeated site runs, so the experiment does
not establish output parity; the repository's cached query results are covered
by regression tests.
