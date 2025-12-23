---
title: 'Markdown Block : Markdown adapted for WYSIWYG block editor'
h1: 'Markdown Block'
id: 47
publishedAt: '2025-12-21 21:55'
parentPage: homepage
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

## Example

```markdown
{#example-content}

## Example Content

{#mainParagraph}
This is a paragraph.

{#mainGallery}
{{ gallery({'piedweb-logo.png': '', '1.jpg': '', '2.jpg': '', '3.jpg': ''}) }}
```