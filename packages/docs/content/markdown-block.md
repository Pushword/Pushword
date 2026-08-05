---
title: 'Markdown Block : Markdown adapted for WYSIWYG block editor'
h1: 'Markdown Block'
publishedAt: '2025-12-21 21:55'
toc: true
---

Which Markdown specification is used in Pushword — _CommonMark_, _GFM_, or _something else_?

The default Markdown implementation in **Pushword** is based on **CommonMark**, with a few customizations designed to make it easy to switch between Markdown and a WYSIWYG block editor.

## For users

The difference is almost invisible — your usual Markdown syntax will continue to work as expected.

## For developers

Markdown content is **parsed block by block**, rather than as a single document.
Blocks are **separated by two blank lines**.

**Attributes** can be defined using the syntax `{#attribute-name}`, placed on a separate line just **before** the Markdown block it applies to. _This is conflicting with Prettier Markdown._

Advanced content types such as **galleries**, **attachments**, or **page lists** are supported through **Twig functions**.

_You can use twig syntax inside markdown inline code or markdown code block, it will not be parsed by twig. If you want to use twig inside inline code or code block, use html directly (`<pre></pre>`)._

### Twig filters

Two filters expose the parser in templates:

- `{{ text|markdown }}` — full block-level rendering (paragraphs, headings, lists, tables…). The output is wrapped in block tags: a one-liner becomes `<p>…</p>`.
- `{{ text|markdown_inline }}` — inline-only rendering, for text injected inside existing markup (a component lede, a caption, a subtitle): `<p class="lede">{{ lede|markdown_inline }}</p>`. Links, emphasis, inline code, strikethrough, `{attributes}` and Pushword inline shortcodes render exactly as with `markdown`, but no block tag is ever emitted. Block syntax (`#`, `-`, `>`, tables…) stays literal text, and blank lines don't create paragraphs — meant for one-line inputs.

Both filters mark their output HTML-safe and pass raw inline HTML through as-is.

## Groups

Consecutive blocks can be wrapped in a single `<div>` by placing an opening and a
closing tag as blocks of their own (blank-line separated). The markdown between the
two lines is rendered normally — CommonMark passes the wrapper through as-is:

```markdown
<div id="pricing" class="grid md:grid-cols-2">

## Plan A

## Plan B

</div>
```

The block editor shows the pair as **Group** markers carrying the anchor and the
classes; the markers are inserted and deleted together. It only claims a `<div>`
line whose attributes are `id` and/or `class` — anything richer stays a Raw block.

## Notices

A blockquote whose first line is a `> [!label]` marker renders as a notice — the
syntax [DocFX](https://dotnet.github.io/docfx/) introduced and GitHub adopted, with
Obsidian's tolerance: the label is case-insensitive, and a title may follow it on the
same line.

```markdown
> [!warning] Version
>
> Last updated: August 2026. Corrections welcome via [GitHub issues](/contribute).
```

- **The label is free.** `note`, `tip`, `important`, `warning` and `caution` ship with
  a palette; any other label (`sponsored`, `deprecated`…) renders neutral and is yours
  to style — the wrapper always carries `notice notice-<label>`.
- **The title is optional.** Without one, the label is displayed instead (`[!note]` →
  "Note"), so the level never rests on colour alone.
- **The body is ordinary Markdown** — paragraphs, lists, links, images.
- An `{#anchor .class}` line placed above the notice applies to it, as for any block.
- Rendering goes through `/component/notice.html.twig`: override it in your theme to
  change the palette, add icons, or drop the wrapper entirely.

A blockquote that does not open with a marker stays a plain blockquote, so quoting
someone is unaffected.

## Tables

Standard GFM table syntax is supported. You can merge cells horizontally using `->` as the cell content — it merges into the preceding cell via `colspan`.

```markdown
| Service         | Identifiant | ->             |
| --------------- | ----------- | -------------- |
| Authentication  | auth        | oauth.provider |
```

Renders as a table where "Identifiant" spans two columns. Multiple `->` cells can follow each other to span more columns.

_Note: `->` in the first cell of a row has no effect (no preceding cell to merge into)._

### Column alignment

Per-column alignment uses the GFM delimiter row: `:---` (left), `:--:` (center), `---:` (right). The block editor's column menu writes these markers for you.

```markdown
| Name  | Price | Qty  |
| :---- | ----: | :--: |
| Apple | 1.20  | 3    |
```

### Sticky header

Add the `{.table-sticky-header}` block attribute on the line before a table to pin its heading row while scrolling (in the editor and on the front). The block editor's "Sticky heading" toggle adds it for you.

```markdown
{.table-sticky-header}

| Col 1 | Col 2 |
| ----- | ----- |
| Val   | Val 2 |
```

## Example

```markdown
{#example-content}

## Example Content

{#mainParagraph}
This is a paragraph.

{#mainGallery}
{{ gallery(['piedweb-logo.png', '1.jpg', '2.jpg', '3.jpg']) }}
```