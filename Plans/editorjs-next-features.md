# Plan — Block editor: the next batch

## Goal

Work through the `[AdminBlockEditor] New features` roadmap list, ordered by what
the current code already makes cheap.

## Already landed

- **Checklists** — `@editorjs/list` v2 replaced `@editorjs/nested-list`, and the
  `- [ ]` / `- [x]` markers round-trip. The data migration this plan feared never
  happened: the tool was registered under our own `List` key, so no stored block
  type changed, and nested-list already stored `{content, items}` — v2 only adds
  `meta`.
- **Hyperlink rel** — the binary "Obfusquer" switch is a `rel` select fed by
  `availableRels`, next to `availableDesigns`. `obfuscate` stays exclusive:
  `HtmlObfuscateLink` matches `rel="obfuscate"` exactly, so it cannot be combined
  with a real rel.
- **Attaches / Images** — both blocks carry a button to drop the media they hold,
  and **Upload** opens the device file dialog (`editorJsHelper.onUploadInline`)
  instead of the media form in a modal iframe.

## Current state (baseline)

- `@editorjs/editorjs` **2.31.6** (latest), `@editorjs/list` **2.0.9**.
- Tools live in `packages/admin-block-editor/src/assets/tools/<Name>/` and are
  registered at **build time** in `editor.ts` — see `.claude/rules/admin-ui.md`
  before adding one; the committed `dist` is what ships.
- The markdown round-trip lives in each tool's `exportToMarkdown` /
  `importFromMarkdown` (e.g. `tools/Paragraph/Paragraph.ts:19-52`).

## 1. Paste Markdown into a paragraph

`Paragraph.ts` already has `importFromMarkdown()` (`:28`) and
`isItMarkdownExported()` (`:51`), and `MarkdownUtils` already converts inline
HTML ↔ Markdown. The missing piece is the paste hook: on paste into a paragraph,
sniff whether the clipboard text is Markdown, and if so run it through the
existing importer to produce real blocks instead of one paragraph.

The sniff is the risky part — a paragraph mentioning `*` or `#` must not be
reinterpreted. Bias towards requiring a block-level marker at the start of a line.

`remark`, `rehype-parse`, `rehype-remark` and `hast-util-to-mdast` used to sit in
`package.json`, imported by nothing, and were dropped as dead weight — so this
item starts by adding back whichever it actually needs.

## 2. Inline tools: escaping the tag at its border

Typing at the very edge of a `<b>`, `<i>`, `<s>`, `<u>`, a link or a marker keeps
the caret inside the tag, so what comes next inherits formatting nobody asked
for. Nothing implemented.

## 3. New blocks

- **Notices** — cheapest. A wrapper with a level; renders to a Twig component,
  and `core/src/templates/component/` has no equivalent yet, so it needs one.
- **Group** (div wrapper with anchor + class) — **shipped**, and cheaper than the
  roadmap implied. Its references were dead ends (serlo/backlog#83: archived
  unresolved investigation; editorjs-columns#6: rejected PR about column widths,
  and that plugin's nested-instance model can't round-trip our markdown anyway).
  No container needed: raw `<div id class>` / `</div>` lines already render
  markdown between them, and AnchorTune/ClassTune already covered per-block
  attributes. Implemented as a paired-marker tool (`tools/Group/`): groupStart /
  groupEnd export those lines and are claimed on import before Raw; deleting one
  marker deletes its partner (GroupRegistry pairs them by document order);
  in-editor grouping is CSS. Group-move as a unit is deferred to the outline
  panel.
- **Audio** — no demand recorded; leave last.
