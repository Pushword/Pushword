# Plan — Block editor: the next batch

## Goal

Work through the `[AdminBlockEditor] New features` roadmap list, ordered by what
the current code already makes cheap. Three items are much closer to done than
the roadmap suggests; two need a data migration.

## Current state (baseline)

- `@editorjs/editorjs` **2.31.6** (latest). `@editorjs/list` **1.10.0**, plus a
  separate `@editorjs/nested-list` **1.3** (`packages/admin-block-editor/package.json:15,17`).
- Tools live in `packages/admin-block-editor/src/assets/tools/<Name>/` and are
  registered at **build time** in `editor.ts` — see `.claude/rules/admin-ui.md`
  before adding one; the committed `dist` is what ships.
- The markdown round-trip lives in each tool's `exportToMarkdown` /
  `importFromMarkdown` (e.g. `tools/Paragraph/Paragraph.ts:19-52`).

## 1. Checklists — `@editorjs/list` v2 (the roadmap's own entry)

**Available now**: v1.10.0 installed, **2.0.9** published, and v2 is where
checklists live.

Not a version bump though:

- v2 **absorbs `@editorjs/nested-list`** — that dependency goes away, and every
  block currently saved by it changes tool name.
- The saved shape changes: `items: string[]` becomes
  `items: {content, meta, items}[]`, with `meta.checked` carrying the checkbox.
- So `ConvertBlockFormatCommand` / the markdown converter need a migration path,
  and existing content must be readable before and after.

Steps: upgrade both packages → collapse `list` + `nested-list` into one tool →
teach `importFromMarkdown`/`exportToMarkdown` the `- [ ]` / `- [x]` syntax →
write the converter for stored v1 blocks → `upgrade/next-release.md` note.

## 2. Paste Markdown into a paragraph

**Most of the groundwork is already committed and unused.**

- `remark`, `rehype-parse`, `rehype-remark`, `hast-util-to-mdast` are all in
  `package.json` — and **nothing under `src/` imports any of them**.
- `Paragraph.ts` already has `importFromMarkdown()` and
  `isItMarkdownExported()` (`:28-52`), and `MarkdownUtils` already converts
  inline HTML ↔ Markdown.

So the missing piece is only the paste hook: on paste into a paragraph, sniff
whether the clipboard text is Markdown, and if so run it through the existing
importer to produce real blocks instead of one paragraph. The sniff is the risky
part — a paragraph mentioning `*` or `#` must not be reinterpreted. Bias towards
requiring a block-level marker at the start of a line.

## 3. Hyperlink: custom rel, target, class

**Half built.** `tools/Hyperlink/Hyperlink.ts` already has the target-blank
switch (`:83`), a design `<select>` fed by `availableDesigns` (`:88-96`,
`:32`), a URL suggester over `window.pagesUriList` (`:70-80`), and it already
sanitizes `rel`/`target`/`class` (`:220-232`).

What is missing is only the `rel` **value**: today it is a binary "Obfusquer"
switch writing `rel="obfuscate"` (`:285-291`). The roadmap wants a real rel
input (`nofollow`, `sponsored`, `ugc`, …) with suggestions. The select and the
suggester next to it are the pattern to copy.

## 4. Attaches / Images: delete button, inline uploader

Nothing yet — `Image.ts:99` only removes the node on a failed load. Both are new
UI on existing tools; no data-shape change. Smallest of the batch.

## 5. New blocks

- **Notices** — cheapest. A wrapper with a level; renders to a Twig component,
  and `core/src/templates/component/` has no equivalent yet, so it needs one.
- **Group** (div wrapper with anchor + class) — the roadmap's own references
  (serlo#83, editorjs-columns#6) are the prior art. Bigger: it is a container
  block, which EditorJS does not natively model.
- **Audio** — no demand recorded; leave last.

## Not planned

"Migrate to tiptap (lol)" — noted as a joke in the roadmap, kept as one.
