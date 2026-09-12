# Content processing exploration

This crate is a measurement probe, **not an enabled Pushword backend**. It uses
[Comrak 0.55](https://github.com/kivikakk/comrak), with a custom formatter for
the tested Pushword subset, to measure the potential of native Markdown
parsing. No production PHP class depends on it.
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

The tests exercise actual Pushword Markdown renderers and compare a 59-case
corpus byte for byte. They record the unsupported cases and time cached and
uncached PHP Markdown, the Comrak batch, PHP TOC preparation and search text
extraction. There is no timing threshold in CI: shared-runner timing is noisy.
The Markdown probe never supplies rendered content to the site.

For comparable timings, pin `composer test-native-core` to one available CPU.
The test writes `target/content-probe-comrak.json`. The original
`benchmarks/2026-09-12.json` remains a pulldown-cmark baseline; the current
Comrak and Tempest measurements are in
`benchmarks/2026-09-12-comrak-tempest.json`.
Rust checks run in debug and release, with Clippy/rustfmt/rustdoc and forbidden
crate-local unsafe code. The weekly repository security job audits the lockfile.

Tempest is an optional PHP comparison. It is deliberately kept out of the
Pushword dependency graph because the package currently requires PHP 8.5 and
is still evolving. Install it in the ignored benchmark directory, then run:

```sh
mkdir -p packages/core/rust/target/tempest
composer init --working-dir=packages/core/rust/target/tempest \
  --name=pushword/tempest-benchmark --require=php:^8.5 --no-interaction
composer require --working-dir=packages/core/rust/target/tempest \
  --no-interaction --no-scripts tempest/markdown:1.2.2
php packages/core/rust/benchmarks/tempest.php
```

Set `PUSHWORD_TEMPEST_AUTOLOAD` when Composer uses another directory. The
script measures the same deterministic inputs and checks the shared PHP corpus;
it constructs `Tempest\Markdown\Markdown(null)` so code highlighting is not
part of the parser comparison. The stable package has no `parseMany()` yet.
The open [PR #24](https://github.com/tempestphp/markdown/pull/24) adds it as an
opt-in collection of independently parsed chunks separated by
`<!-- next -->` or `<!-- next: name -->`.

## Findings

Local medians in **milliseconds per document**, five samples of 20 documents,
one CPU, PHP 8.5.9/libxml 2.12.10, Rust 1.98.0:

| Synthetic input | PHP Markdown uncached | Comrak Markdown batch | PHP Markdown cache hit | PHP TOC uncached | PHP TOC cache hit | PHP search text |
|---|---:|---:|---:|---:|---:|---:|
| Short, 244 B | 0.400 | 0.058 | 0.0010 | 0.261 | 0.0024 | 0.0096 |
| Article, 9.8 KB | 8.086 | 0.408 | 0.0026 | 5.916 | 0.0104 | 0.2228 |
| Long, 99 KB, 800 unique headings | 89.507 | 2.968 | 0.0184 | 59.825 | 0.1057 | 2.254 |
| Long, 97.6 KB, 800 identical headings | 89.718 | 2.810 | 0.0141 | 228.182 | 0.1042 | 2.227 |

Inputs repeat a deliberately simple CommonMark paragraph. This is not a sample
of production traffic. PHP converters are already constructed; uncached means
conversion executes each time, not a cold PHP process. Cache hits use an
in-memory `ArrayAdapter`, not the production filesystem cache. The Rust time
includes one startup per batch, JSON, processing and PHP validation. Twig,
Doctrine queries, page rendering, HTTP, file publication and search indexing
are outside these timings. TOC measures `SplitContent` preparation/getBody(),
including heading-ID injection, not the final `getToc()` menu render.

### Markdown: large opportunity on cache misses, incomplete compatibility

The timed subset is byte-identical. The current corpus is 49/59 byte-identical
with the custom Comrak formatter. The ten recorded gaps cover Pushword-specific
dynamic links, media/notices, block attributes, Unicode IDs, table edge cases
and other extension details. The corpus is a compatibility map, not a promise
that a generic CommonMark converter can replace Pushword's renderer.

The Comrak ratio on the 9.8 KB subset is a parsing opportunity, not a drop-in
CMS speedup. Preserve the existing content cache. The next useful prototype is
a Rust parse stage with PHP retaining Pushword-aware rendering, or a complete
port of those renderers with explicit resolved dependencies. Measure the cost
of returning parser events/AST to PHP before choosing that split. Do not enable
a generic converter based only on these numbers.

Tempest 1.2.2 renders the same article in about 1.5 ms per document in the
standalone PHP benchmark, versus the native Comrak batch measured by the probe;
the exact samples and environment are committed in the raw report. Tempest's
different output (for example, heading IDs and attribute serialization) is why
its corpus score is intentionally reported rather than folded into the
Pushword-compatible result.

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

## Markdown blocks and Tempest PR #24

The proposed `parseMany()` API in [Tempest Markdown](https://github.com/tempestphp/markdown)
splits one source document on `<!-- next -->` or `<!-- next: name -->`, parses
each chunk independently and exposes a named collection. That is a useful
model for an explicit `MarkdownDocument` to `MarkdownBlock[]` boundary. It is
not yet in the stable 1.2.2 package, and Pushword currently has no such marker
syntax: Twig runs first and `SplitContent` operates on rendered HTML. A future
block format would need an opt-in syntax and a compatibility decision before it
could affect public content, previews or static generation.
