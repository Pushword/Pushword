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