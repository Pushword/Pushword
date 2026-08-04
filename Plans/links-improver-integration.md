# Plan — LinksImprover: automatic internal linking

## Goal

Turn a keyword → URL map into internal links, inserted into rendered content
automatically. The roadmap entry is "Intégrer **LinksImprover** (+ UX)".

## What LinksImprover actually does

`piedweb/linksimprover` **^0.0.8** is already a root `composer.json` requirement
(`composer.json:46`) and installed in `vendor/` — and **no PHP in `packages/`
references it**. It is dead weight today: either this plan lands, or the
dependency should be dropped.

The library (`vendor/piedweb/linksimprover/src/`) is small and does one thing:

- **`LinksManager`** takes rows of `[url, keywords, force, counter]` — a CSV is
  the expected source — and builds `Link` objects. `Link::setKws()` splits the
  comma-separated keywords, `preg_quote`s each, expands `*` to `[^<]{0,10}`, and
  sorts them longest-first so the most specific anchor wins.
- **`LinksImprover::__construct($content)`** counts the words and indexes every
  `href` already present.
- **`improve($linksManager, $maxLink, $linkAttrToAdd)`** walks the links —
  ordered by `force` desc then `counter` asc, so under-used targets come first —
  and for each one: skips it if the URL is already linked in this content, finds
  the first keyword match, and inserts `<a href="…">anchor</a>` at that offset.
- **`canWeCreateALink($pos)`** is the safety net: refuses inside a tag, refuses
  unless the position sits in an allowed element (`p`, `span`, `b`, `strong`,
  `em`, `i`, `li` by default; `TAGS_EXTENDED` adds headings and `div`), and
  refuses immediately after a `</a>` so links never end up adjacent.
- **`$maxLink < 1` is read as a ratio of the word count** — `0.01` means one
  link per 100 words. `>= 1` is an absolute cap.
- `getAddedLinks()` returns the `[anchor, url]` pairs it inserted.
- `LinksImproverBBCode` is the same engine for BBCode; irrelevant here.

It is regex-over-HTML, not a DOM pass. That is fine for the constrained markup
Pushword renders, and it is why the tag guard exists.

## Where it would plug in

Rendered HTML, after Markdown and Twig, before the render cache — the same seam
`LinkCollector` and the unpublished-link filter already use
(`packages/core/src/Component/EntityFilter/Filter/`). Running it earlier would
mean re-running it on every cache hit; running it later would mean it never gets
cached.

Two integration questions to settle first:

1. **Where does the keyword map live?** The library wants CSV rows. Pushword has
   three plausible homes: a `links.csv` next to `media.csv` in the flat content
   dir (consistent with how flat sites are edited), a config key, or page tags.
   The CSV is the closest fit to the library and the least new UI.
2. **Is the result stored or ephemeral?** Ephemeral (decorate at render) keeps
   the source content clean and is reversible. Stored (write links back into
   `mainContent`) is what an editor can then review and correct — which is what
   "+ UX" in the roadmap probably means.

## Steps

1. Settle 1 and 2 above. They decide everything else.
2. `LinkImproverFilter` in the EntityFilter chain, opt-in per site via config —
   never on by default; it rewrites content.
3. Feed `existingLinks` from `LinkCollector`, which already tracks what the page
   linked. Without this the two features fight: the collector's
   `excludeAlreadyLinked` will not know about links the improver just inserted.
4. Cap with the ratio form (`0.005`–`0.01`), not an absolute — content length
   varies too much across a site.
5. **The UX half.** `getAddedLinks()` is the whole reporting surface: a page
   scanner check, or an admin panel, showing what was auto-linked and where.
   Without it the feature is invisible and unauditable, which is how automatic
   linking earns its bad reputation.

## Verification

- A page already linking a target does not get a second link to it.
- No link inserted inside `<code>`, `<pre>`, a heading (unless extended tags are
  configured), or immediately after another link.
- Render output stays byte-deterministic across two runs (the render cache
  depends on it) — the `counter` ordering must not make the output depend on how
  many pages were rendered before.
- Bump `MarkdownParser::CACHE_VERSION` — this changes render output.
