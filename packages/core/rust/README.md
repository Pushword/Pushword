# Content processing exploration

This crate is a measurement probe, **not an enabled Pushword backend**. It uses
[pulldown-cmark](https://github.com/pulldown-cmark/pulldown-cmark) to measure the
potential of native Markdown parsing. No production PHP class depends on it.
Installing Pushword or using shared hosting still needs only PHP.

The code lives with its prospective domain owner, `pushword/core`. No production
transport was duplicated: the test driver invokes this standalone probe in
batches. A shared Cargo workspace/transport should be extracted only when a
second production operation is ready, not merely because this experiment exists.

## Reproduce

From a full monorepo checkout with its development dependencies and fixtures:

```sh
make -C packages/core/rust build
make -C packages/core/rust test
make -C packages/core/rust audit
```

The tests exercise actual Pushword Markdown renderers and compare a precisely
defined subset byte for byte. They record the unsupported cases and time cached
and uncached PHP Markdown, the Rust batch, PHP TOC preparation and search text
extraction. There is no timing threshold in CI: shared-runner timing is noisy.
The Markdown probe never supplies rendered content to the site.

For comparable timings, pin `composer test-native-core` to one available CPU.
The test writes `target/content-probe.json`. The committed sample in
`benchmarks/2026-09-12.json` includes toolchain, input/binary hashes and methodology.
Rust checks run in debug and release, with Clippy/rustfmt/rustdoc and forbidden
crate-local unsafe code. The weekly repository security job audits the lockfile.

## Findings

Local medians in **milliseconds per document**, five samples of 20 documents,
one CPU, PHP 8.5.9/libxml 2.12.10, Rust 1.98.0:

| Synthetic input | PHP Markdown uncached | Rust Markdown batch | PHP Markdown cache hit | PHP TOC uncached | PHP TOC cache hit | PHP search text |
|---|---:|---:|---:|---:|---:|---:|
| Short, 244 B | 0.306 | 0.114 | 0.0012 | 0.207 | 0.0019 | 0.0065 |
| Article, 9.8 KB | 9.282 | 0.246 | 0.0024 | 6.274 | 0.0104 | 0.234 |
| Long, 99 KB, 800 unique headings | 94.217 | 1.720 | 0.0158 | 58.895 | 0.0943 | 2.283 |
| Long, 97.6 KB, 800 identical headings | 95.005 | 1.726 | 0.0142 | 229.508 | 0.1014 | 2.249 |

Inputs repeat a deliberately simple CommonMark paragraph. This is not a sample
of production traffic. PHP converters are already constructed; uncached means
conversion executes each time, not a cold PHP process. Cache hits use an
in-memory `ArrayAdapter`, not the production filesystem cache. The Rust time
includes one startup per batch, JSON, processing and PHP validation. Twig,
Doctrine queries, page rendering, HTTP, file publication and search indexing
are outside these timings. TOC measures `SplitContent` preparation/getBody(),
including heading-ID injection, not the final `getToc()` menu render.

### Markdown: large opportunity on cache misses, incomplete compatibility

The timed subset is byte-identical. The broader 11-case compatibility probe is
only 5/11 byte-identical: plain Markdown, lists, email, phone links and default
fenced code. Raw HTML, tables, task lists, attributes, obfuscated links and media
images differ. Some differences are formatting; attributes/media/obfuscation
change behavior. Dates, notices, table colspan, all site settings and downstream
extensions are not comprehensively covered by this probe.

The roughly 38x ratio on the 9.8 KB subset is therefore a parsing opportunity,
not a drop-in CMS speedup. Preserve the existing content cache. The next useful
prototype is a Rust parse stage with PHP retaining Pushword-aware rendering,
or a complete port of those renderers with explicit resolved dependencies.
Measure the cost of returning parser events/AST to PHP before choosing that split.
Do not enable a generic converter based only on these numbers.

### TOC: investigate the algorithm before replacing the language

`TOC\UniqueSlugger::makeSlug()` restarts a numeric suffix search for every heading
and uses `in_array()` over all previously used slugs. Repeated headings amplify
that work. The controlled unique/repeated comparison separates this effect
from HTML parsing. A collision-indexed slugger and then a compatible HTML
transformation are worth testing; preserve existing IDs, numeric-prefix rules,
transliteration, duplicate handling and stop/end-TOC markers. This turn makes
no change to that algorithm and does not claim a Rust TOC implementation.

### Search/scanning and admin

Plain-text extraction alone costs 0.234 ms on the article; its PHP regex/tag
operations already execute largely in native libraries. A standalone Rust call
needs to beat that plus transport. Parsing HTML once for headings, links and
search text could be useful where those consumers really share an input, but
must preserve their different exclusion and normalization rules.

Admin preview and static generation can share future content acceleration.
Admin list/search/forms, SQL, permissions and Twig remain separate workloads;
these measurements establish no improvement for them or for cached public hits.
