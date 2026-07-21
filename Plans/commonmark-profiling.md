# CommonMark cold-render profiling — findings

**Date:** 2026-05-25
**Scope:** Validate the tempestphp/markdown#3 hypothesis (allocation/cloning-bound OO
parser) against `league/commonmark` 2.8.2 as used by Pushword's
`MarkdownParser`, on cold renders (first build / content changed).

## TL;DR

The tempest hypothesis is **refuted for league/commonmark**: cloning is ~0.6% of
cost, node allocation is intrinsic (~9%), and there is no per-block Parser/Lexer
clone. The real bottleneck was **self-inflicted, in our own
`EmailAutolinkParser`**: its inline-match definition fired at *every alphanumeric
character*, turning each cold parse into an O(n²) string scan.

Fixing the match definition gave a **5.6× cold-render speedup (45 ms → 8.0 ms per
~37 KB page), byte-identical output** (same md5, all 84 markdown tests green). No
upstream issue is warranted. The remaining cost is evenly spread intrinsic
commonmark work.

## Method

- Throwaway command in `packages/dev-app/` (now deleted): built the production
  converter (same extensions as `MarkdownParser`) with a **null cache** so every
  `transform()` is a cold parse+render; fed the heaviest real altimood page
  (`refuges-vercors.md`, 37 KB, frontmatter stripped — the p100 of the 982-file,
  median-9 KB / p90-17 KB corpus), 200 iterations.
- Phase split via a `DocumentParsedEvent` listener (fires after block+inline
  parse, before render).
- Real profiler: **Xdebug 3.5.1 profile mode** (built locally against PHP 8.5.6),
  cachegrind aggregated by self-time and call count.
- Output equivalence verified by md5 of rendered HTML before/after the fix.

## Profile — before fix (cold, 37 KB page)

| Metric | Value |
|---|---|
| ms / convert | **45.0 ms** |
| converts / s | 22 |
| parse (block+inline) | **97.4%** |
| render (html) | 2.6% |
| AST nodes / parse | 792 |

Top self-time (cachegrind, 40 iters):

| Function | self % | calls |
|---|---|---|
| `InlineParserEngine->parse` | 25.6% | 8.2 k |
| `InlineParserEngine->addPlainText` | 9.0% | 1.32 M |
| **`PushwordExtension … EmailAutolinkParser->parse`** | **8.4%** | **1.07 M** |
| `ParagraphParser->parseInlines` | 7.4% | 6.2 k |
| `Dflydev\DotAccessData\Data->has` | 5.6% | 1.31 M |
| `InlineParserEngine->matchParsers` | 5.2% | 8.2 k |
| `Safe\preg_match` | 3.2% | 1.30 M |
| `Cursor->advanceBy/advance/peek/getCurrentChar` | ~12% combined | ~4.4 M |
| `InlineParserContext->withMatches` (the **clone**) | 0.6% | 1.10 M |
| `HtmlRenderer->renderNode` | 0.34% | 30 k |

## Root cause

`EmailAutolinkParser::getMatchDefinition()` returned:

```php
return InlineParserMatch::regex('[A-Za-z0-9._+-]'); // one alphanumeric char
```

CommonMark's `InlineParserEngine` consults a parser at **every position its match
regex hits**. A single-character class matches ~27 000 positions in a 37 KB
document. At each, `EmailAutolinkParser::parse()` ran `peek(-1)`, two
`preg_match`, and **`Cursor::getRemainder()` — which copies the entire remaining
string tail** (~18 KB average). That is the O(n²): ~27 k × ~18 KB ≈ hundreds of MB
of string copying per parse, plus a regex over each copy. It also bloated
`matchParsers()`'s position array (and its `ksort`).

This single parser accounted for the bulk of the 1.07 M `Safe\preg_match` /
1.3 M-call hot path — i.e. roughly the entire anomaly that made markdown "43% of
page render time" on the original altimood profile.

## Fix (applied)

`packages/core/src/Service/Markdown/Extension/Parser/EmailAutolinkParser.php`:

```php
// only fire at plausible email starts (local part + '@' + domain),
// not on every character
return InlineParserMatch::regex('[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}');
```

The character classes mirror the existing anchored `EMAIL_REGEX` (which
`parse()` still re-validates), so the set of trigger positions is unchanged in
practice — only the *count* drops from "every char" to "every email-like span".

**Result:** 45.0 ms → **8.0 ms** per convert (5.6×); 22 → 125 converts/s; output
**byte-identical** (md5 `7b4a9d27…` unchanged); `composer test-filter Markdown` =
84/84 pass; `composer stan` = 0 errors.

## Profile — after fix

No dominant hotspot remains. Self-time is spread across genuine commonmark work:
`InlineParserEngine->parse` 7.0%, `MarkdownParser->parseLine` 6.4%,
`findBlockStart` 4.0%, `matchParsers` 2.2%, `HtmlRenderer->renderNode` 2.1%,
delimiter processing, `Cursor` construction. Total self-cost dropped ~8×.
Email/Phone/Date parsers and `Safe\preg_match` fell out of the top 120 entries.
The only residual Pushword cost is `ObfuscatedLinkProcessor` at 0.71% (runs once
per parse — fine).

## Classification vs tempestphp/markdown#3

| tempest#3 thesis | league/commonmark reality |
|---|---|
| ~1 800 token objects allocated per parse | Intrinsic node alloc (`new Text`) ~9%; not pathological |
| Parser+Lexer tree **cloned per block** for inline re-parse | Only lightweight clones: `Cursor` per block-start attempt, `InlineParserContext::withMatches` per candidate — **~0.6% combined** |
| Single-pass `strcspn`/`strpos` into by-ref buffer → 7–12× | commonmark *already* uses a `Cursor`-based scan; no equivalent rewrite available |

**Conclusion:** commonmark is **not** allocation/cloning-bound. Pushword's cost was
a self-inflicted O(n²) in our own inline parser. Different bottleneck class.

## Should we file an upstream issue?

**No.** commonmark internals are not the problem. The only upstream-flavoured
observation is a DX footgun — a single-character `InlineParserMatch` definition
silently makes a parser O(n·m) — but that is our misuse, not a commonmark bug.

## Secondary finding (not changed — surgical scope)

`PhoneAutolinkParser` has the same anti-pattern (`InlineParserMatch::oneOf('+',
'0')` → fires at every `0`/`+`, then `getRemainder()` + regex). After the email
fix it is **negligible** (`'0'`/`'+'` are far rarer than alnum and `PHONE_REGEX`
fails fast — it does not appear in the top 120 self-time entries). Left as-is. If
ever needed, narrow the match to require the leading shape, e.g.
`(?:\+33|0033|0)[\s.&xA0]*[1-9]`.

## Is this low-value because of the cache?

**No — cold renders are frequent, so the fix has real value.** The per-fragment
cache (`MarkdownParser::transform`) is invalidated on:

1. **Page save** — that page's fragments (expected).
2. **Full static build** after deploy / cache clear — *all* pages cold.
3. **Any media write** — `MediaCacheInvalidationListener::{postPersist,postUpdate,
   postRemove}` bumps `pw.media.version`, which `cacheVersion()` mixes into every
   markdown key → **the entire markdown cache is invalidated**, so the next render
   of *every* page is cold. On an active editorial site, a single image
   upload/crop blows the whole cache.

Impact estimate (altimood, ~1 145 rendered pages):

- Full cold static build markdown work: **~51 s → ~9 s** (≈42 s saved per build).
- markdown share of page render falls from ~43% to ~12%; cold page render ~1.5×
  faster overall — markdown is no longer the dominant cost.

Cost/risk of the fix: 2 lines, byte-identical output, all tests pass. **High
value, near-zero risk.**
