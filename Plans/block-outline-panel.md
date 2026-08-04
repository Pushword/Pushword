# Plan — Block outline panel (left rail)

Roadmap line (`packages/docs/content/roadmap.md`):

> [Admin] / [AdminBlockEditor] Avoir un block à gauche de l'éditeur pour afficher la
> liste des blocs utilisés, pouvoir déplacer ces blocs facilement en sélectionnant un
> bloc, ou un groupe de blocs naturellement groupés sous un header, fonctionne depuis
> le markdown ou depuis l'editorjs

## Decisions (Robin, 2026-08-04)

- **Scope**: EditorJS mode **and** the Monaco source views (markdown + JSON) of the
  block editor. NOT the EasyMDE editor — the whole feature lives in
  `packages/admin-block-editor`, sites with `admin_block_editor: false` get nothing.
- **Drag**: two handles per header entry — one moves the header block alone, one moves
  the whole section (header + every block until the next header of same-or-higher
  level; an H2 carries its H3 subsections). Non-header entries have one handle.
- **Actions**: click an entry → scroll to + highlight the block; collapse/expand
  sections; delete a block or a whole section. No duplicate.
- **Placement**: left rail fixed to the viewport, sticky over the EasyAdmin sidebar
  navigation (z-index above it), collapsible to a slim toggle. Collapse restores
  access to the admin menu; state persisted in localStorage.
- **Groups** (added same day, after the Group feature landed): `groupStart`/`groupEnd`
  marker pairs (`tools/Group/`) are first-class containers in the outline, on par
  with header sections.

## Current state — seams to build on

- Entry chain: `admin-block-editor.ts` → `editorJs.initEditor()` (`editor.ts:110`),
  instances in `window.editors[holderId]`, holder ↔ input via `data-input-id`
  (`editor.ts:203 boundInputOf()`).
- `onReady` (`editor.ts:156-171`) already wires DragDrop, the vendored `Undo`,
  PasteLink, ClipboardManager — the panel plugs in there.
- Mode switching: `EditorModeManager` (`EditorModeManager.ts`) swaps the input node
  (`createTextarea`/`createHiddenInput`) and mounts Monaco via
  `window.monacoHelper.transformTextareaToMonaco()`. The panel must re-resolve the
  input by id after every toggle and be told which data source is active.
- Markdown block unit: blank-line-separated chunk —
  `EditorJsParseMarkdown.parseMarkdown()` normalizes `\n\s*\n+` → `\n\n` then splits;
  each chunk is classified by the tools' static `isItMarkdownExported()`. Headers
  match `/^#{2,6}\s/` (`tools/Header/Header.ts`).
- Tunes serialize as `{#anchor .class}` attributes — strip with
  `MarkdownUtils.retrieveMarkdownWithoutTunes()` before showing a label.
- EasyAdmin sidebar: `.sidebar`; precedent for suppressing it is
  `body.pw-inline-window` setting `--sidebar-max-width: 0` (`admin.css:268,284`).
- The vendored `Undo` (`tools/utils/Undo/Undo.ts`) re-baselines on `onChange` and
  restores by full `blocks.render()` — a snapshot restore invalidates every DOM
  reference and index the panel holds.

## Spec

### Outline model (shared, pure)

One derivation, two producers, one consumer (the panel UI):

```
OutlineEntry { index, type, level: number|null, label }
OutlineNode  { entry, children, spanEnd }   // spanEnd inclusive; == entry.index for leaves
```

Tree building (one pure function, `buildOutlineTree`):

- **Groups first**: stack-match `groupStart`/`groupEnd` by document order — the same
  rule as `GroupRegistry.computePairs`, so both can never disagree. A matched pair
  becomes a container node spanning start..end; the end marker is absorbed (no own
  entry). Unmatched markers stay leaf entries, exactly as the registry keeps them.
- **Headers second, per container**: within one container (top level, or a group's
  interior), a header owns the following siblings until the next header of
  same-or-higher level *in that container*. A group is atomic at the level where it
  starts: the next-header scan never looks inside it, a header inside a group never
  terminates a section outside it, and no section extends past its container's end.
  Result: spans always nest cleanly; moving one can never tear a group pair apart.
- Section span of a header node = its index .. spanEnd of its last descendant.

- **EditorJS producer**: walk `api.blocks.getBlocksCount()` /
  `getBlockByIndex(i)` — gives `.name`, `.id`, and `.holder` (DOM node). Label =
  truncated `holder.textContent`; header level from the rendered `h2/h3/h4` tag.
  No `saver.save()`: the block API keeps empty paragraphs, so panel indices always
  equal editor indices (the exact trap documented in `Undo.ts:10-20`).
- **Markdown producer** (Monaco markdown mode): chunk the model value with the SAME
  code as `EditorJsParseMarkdown` — extract the normalize+split into a shared
  utility both import, so the two can never diverge. Type via
  `isItMarkdownExported()` (paragraph fallback), label from the chunk with tunes
  stripped, and each entry keeps its start/end line for navigation and rewrite.
- **JSON producer** (Monaco JSON mode): `JSON.parse(model.getValue()).blocks`;
  navigation targets the line of the block's `"id"` occurrence.

Section-span computation is one pure function over `OutlineEntry[]`, identical for
all three producers. Unit-test it directly (vitest, `Hyperlink.test.ts` pattern).

### Panel UI

- One panel per editor holder, created in `initEditor()`'s `onReady`, registered on
  `editorJsHelper` next to the mode managers.
- Rendered list: icon (tool `toolbox.icon`) + label; header entries indented by
  level with a collapse caret. Collapsed state keyed by section header index,
  reset on structural change is acceptable (keep it simple).
- Group entries: labeled "Group" + anchor/class when set, children indented,
  collapsible like sections. ONE handle (a group is atomic — its markers live and
  die together, so "move the start alone" has no meaning); delete removes the whole
  span, content included. The `GroupRegistry` partner-cascade is a deferred no-op
  when both markers vanish in the same operation, so span deletes are safe.
- **Click** → scroll block into view (`holder.scrollIntoView` / Monaco
  `revealLineInCenter` + cursor at chunk start) + transient highlight class.
- **Delete** → trash button per entry; on a header entry a second control deletes
  the whole section. No confirm dialog: EditorJS undo (and Monaco undo) covers
  restoration. EditorJS: `api.blocks.delete(i)` loops from the end of the span.
- **Drag**: native HTML5 DnD inside the panel list. Drop indicator between
  entries; a section drop target excludes its own interior. EditorJS side:
  sequence of `api.blocks.move()` calls for the span. Monaco side: reorder the
  chunk array and write back via `pushEditOperations` (NOT `setValue`) so Monaco's
  undo stack keeps the move as one step.
- Keyboard parity: entries focusable; ArrowUp/Down walks the list, Enter
  navigates, Alt+Arrow moves the block (Alt+Shift+Arrow moves the section).

### Sync

- EditorJS mode: rebuild from the editor's `onChange` (already emitted; the config
  hook in `editor.ts`), debounced (~300ms). A rebuild is a full re-render of the
  panel list — cheap at page scale, no diffing.
- Monaco modes: rebuild from `model.onDidChangeContent`, same debounce.
- Mode toggle: `EditorModeManager.switchTo/switchFrom` notify the panel to swap
  producer; panel re-resolves the input node by id (the node was replaced).
- Undo restore (`blocks.render()`): lands as `onChange` → normal rebuild. Never
  cache holder DOM nodes across rebuilds.

### Placement & chrome

- Fixed left rail, ~260px, full viewport height below the admin header, z-index
  above `.sidebar`. Slim collapsed state (icon button column); toggle persisted in
  localStorage (`pw-outline-collapsed`).
- Default: open when the viewport leaves room (≥ xl), collapsed below. When open
  on narrow screens it overlays content (drawer), never pushes the form.
- Bootstrap/EasyAdmin context: apply DesignGuidelines concepts (hierarchy,
  spacing, color roles, accessibility), not Tailwind classes.
- The 60px left gutter the block editor already reserves
  (`admin-block-editor/src/assets/admin.css:14-19`) stays as-is; the rail is
  viewport-fixed, not in-flow.

### i18n

Panel strings (tooltips: move block, move section, delete, collapse, toggle
panel) go through the widget config like the rest of the editor i18n
(`editorjs_widget.html.twig`), with keys in
`packages/admin-block-editor/translations/messages.{en,fr}.yaml` — camelCase,
alphabetical.

## Status

Shipped 2026-08-04, steps 1–6 all done (commits 27bd3b35a → outline rail,
80ea49cbf → drag, 062e7759e/6c6232112 → layout + opener, 0fa4bd53e → Monaco
views, then keyboard + docs). Verified in-browser on the kitchen-sink page:
navigate, fold, section drag (one undo step), Monaco markdown/json round-trip.
Labels asked by Robin along the way: heading rows say "heading", not "block";
opener pinned top-left (Gutenberg spot); title field capped to the content
column so the rail can widen (clamp 260px–400px).

## Implementation order

1. **Shared model** — extract the markdown chunker from `EditorJsParseMarkdown`
   into a shared utility (line-aware, so the Monaco producer gets chunk → line
   ranges for free); `OutlineEntry` + `buildOutlineTree`; unit tests (chunking
   equivalence with the old normalize+split, H2/H3 nesting, span edges: leading
   blocks before any header, trailing section, adjacent headers, groups: nested,
   sequential, unmatched markers, header/group interleaving).
2. **Panel on EditorJS mode** — rail chrome, render, collapse, click-navigate,
   delete; `onChange` sync.
3. **Drag** — two handles, span moves, undo interplay verified by hand.
4. **Monaco markdown mode** — producer + navigation + reorder/delete via
   `pushEditOperations`; mode-toggle handoff.
5. **Monaco JSON mode** — same, JSON producer.
6. **Polish** — i18n, keyboard, `yarn build` committed output, dev-browser
   screenshots (ui-debug skill), doc page update
   (`packages/docs/content/extension/` block-editor page), remove the roadmap
   line.

No upgrade note: nothing is asked of upgrading sites — the panel ships with the
compiled assets.

## Watch-outs

- `blocks.move()` fires `onChange` per call — a section move is N events; debounce
  makes the panel rebuild once, and the vendored Undo's own debounce should fold
  it into one history step. Verify; if it splits, batch via `Undo` pause/resume.
- Fenced code blocks containing blank lines: whatever `EditorJsParseMarkdown`
  does today, the panel does identically (shared chunker). If the parser has a
  fence bug it is pre-existing — flag, don't fix here.
- Multiple editors on one page (`window.editors` is a map): the rail binds to the
  main-content editor only; a second editor gets no panel (nothing speculative).
- `?disableEditorJs` / EasyMDE pages: the panel never loads (it lives in the
  block-editor bundle's assets).
