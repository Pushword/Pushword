---
paths:
  - "packages/admin/**"
  - "packages/admin-block-editor/**"
  - "packages/*/src/Admin/**"
  - "packages/*/src/Editor/**"
  - "packages/*/src/templates/**"
---

# Admin UI (EasyAdmin + EditorJS)

- **Header buttons go in `{% block page_actions %}`**, not `{% block actions %}`. In
  templates extending `@EasyAdmin/page/content.html.twig`, a wrongly-named block is
  silently dropped: no error, route still 200, buttons simply absent. Assert on rendered
  HTML, never on status alone.
- **EditorJS tools are registered at build time, not runtime.** A tool's `.ts` class must
  live in `packages/admin-block-editor/src/assets/tools/<Name>/` and be imported into the
  `editorjsTools` map in `editor.ts`, then rebuilt with `yarn build` in that package —
  the committed `dist` is what ships. A package activates its tool by shipping a PHP
  `EditorJsToolProviderInterface` implementation (tagged
  `pushword.editorjs_tool_provider`), which `AppExtension::editorjs_extra_tools` merges
  into the widget template.
- **Do not use form themes for editor wiring.** EasyAdmin's per-CRUD `addFormTheme` makes
  form-theme overrides lose the precedence fight; the Twig-function merge above is the
  supported seam.
- **Testing an inline tool by hand: click the toolbar button, not `Ctrl+K`.**
  `@codexteam/shortcuts` never calls `preventDefault()` — editor.js does, but only inside
  `currentBlock && currentBlock.tool.enabledInlineTools`. With no focused block the event
  falls through to Chrome, which opens its omnibox search, and the tool looks broken when
  it is not. Automation cannot reach these tools at all: editor.js relocates a
  programmatic caret back to the first block, so a scripted selection never survives —
  assert on the tool class instead (`Hyperlink.test.ts` is the pattern).
- **An inline tool must write its input through `setAttribute('value', …)`, never
  `input.value =`.** `checkState()` runs on every `selectionchange`, which editor.js
  listens to on the document; assigning the live property moves the caret, re-raising the
  event, and the toolbar rebuilds in a loop as soon as the caret is inside a link
  (codex-team/editor.js#2821, fixed upstream in 2.31.4 as `defaultValue`).
- **`useEntryCrudForm()` collections need the stacked-field override.** EasyAdmin's
  `fields.css` forces every `.form-group` inside a collection accordion into a
  horizontal `label 20% | widget flex:1` split, which assumes a plain `setEntryType()`
  form spanning the accordion body. An entry CRUD form brings its own `col-*` grid, so
  the split runs inside each cell and squeezes labels to a few characters. `adminForm.css`
  re-stacks them, scoped to `.form-fieldset` — the marker only a CRUD-form entry emits, so
  plain-entry collections (page redirects, media creators) keep the horizontal layout.
  Entry fields also need explicit `setColumns()`, or EasyAdmin gives each one its own row.
- **Monaco is fetched on demand, and only where a field needs it.** `admin.js`
  (`admin.monacoLoader.js`) injects `window.pwMonacoUrl` — published by
  `DashboardController::configureAssets()` — when the page holds a `textarea[data-editor]`;
  the block editor injects the same URL for its markdown/JSON modes and shares the
  in-flight promise through `window.pwMonacoLoading`. The `<script>` in
  `@pwAdmin/layout.html.twig` covers only the custom tool pages: EasyAdmin CRUD pages do
  not use that layout, so nothing there loads Monaco on its own. Build order matters —
  `packages/admin`'s vite build empties `src/Resources/public/`, so
  `admin-monaco-editor` must be built *after* it or `monaco/` disappears.
- **Monaco's clipboard events must be caught on `document`, in the capture phase.**
  Since 0.52 Monaco types through a native `EditContext` div and swallows `paste` on its
  way down, so a listener on `editor.getDomNode()` never fires even though the event
  target sits inside it. Filter with `node.contains(event.target)` to tell two editors
  apart, and register against an `AbortSignal` — `editor.dispose()` does not reach
  listeners bound outside the editor (same for `ResizeObserver` and window `resize`).
  Synthetic `new ClipboardEvent('paste', {clipboardData})` is not dispatchable in Chrome:
  test with `grantPermissions(['clipboard-write'])` + `navigator.clipboard.writeText` +
  a real `Control+v`.
- **`MarkdownUtils.chunkMarkdown()` is the one splitter — parser, outline rail and
  clipboard all cut blocks with it, so a rule added on one side must go there, not into
  the caller.** It splits on blank lines *except inside a fenced code block*: without that
  exception a fence holding a blank line is cut in two, and the halves are classified
  independently, so a `## ` line in the code becomes a real heading — the rail then shows
  a phantom section owning the closing fence, and deleting it opens the fence over the
  rest of the document. Chunks carry `separatorAfter`, the blank-line run that followed
  them; rewrite through `MarkdownUtils.joinChunks()` rather than `join('\n\n')`, or every
  edit normalises spacing across the whole field.
- **An outline row may only edit through the render it was drawn with.** Rows carry block
  indices captured at render time, and `scheduleRefresh()` is debounced 300 ms — so
  between an edit and the rebuild, every button on screen is live and holding numbers the
  document has moved past. `OutlinePanel` bumps a `generation` on each `refresh()`;
  handlers capture it and call `actsOnCurrentRender()`, which flushes a pending debounce
  and drops the action when the numbers are stale. A rail-initiated edit must therefore
  call `refresh()` (synchronous), never `scheduleRefresh()` — the debounce is for change
  notifications coming *from* the editor, where a rebuild per keystroke would be waste.
  Any new rail action owes the same guard, or a double-click deletes twice: once on the
  row aimed at, once on whatever slid into its place.
- **`chunkTool()` is order-dependent — feed it one `GroupNesting` per document.** Markdown
  chunks are classified in document order and the classifier carries state: a lone
  `</div>` is a `groupEnd` only when the `<div>` it closes was imported as a `groupStart`.
  Classify a chunk on its own, or share a nesting across two documents, and a `</div>`
  closing hand-written HTML becomes a marker again — deleting a group then cascades onto
  the user's own tag. The rule is symmetry: a closing tag is a marker exactly when its
  opener was, so any new tool claiming a bare closing line owes the same pairing.
- **A group has a *kind*, and the two kinds never close each other.** `div` wraps in
  `<div>`; `showMore` wraps in the collapsible block, spelled either
  `{{ startShowMore(…) }}` or the legacy `<!--start-show-more-->` — one kind, two
  syntaxes, because both render the same wrapper. `GroupNesting` counts them apart and
  `computePairs` refuses to pair across kinds, so crossed ranges leave both markers
  unpaired instead of making one delete the other. All the patterns live in
  `GroupSyntax.ts`: add a spelling there, never in a tool. Pairing reads the kind off
  `data-pw-group-kind` in the rendered marker (block data is not reachable
  synchronously), so a `render()` that forgets the attribute silently pairs as `div` —
  and toggling the checkbox must push the new kind onto the partner via
  `GroupRegistry.updatePartnerOf()`, since the closing marker is a block of its own.
- **A group's `class` field means two different things.** On a plain group it dresses the
  `<div>` wrapping the blocks; on a collapsible one it dresses the `.show-more` wrapper,
  whose three children are the toggle, the content and the button — a `grid` there lays
  those out as cells. The placeholder says which, and layout on a collapsible belongs to
  a plain group nested inside.
- **An editor that rewrites the field it feeds owes the recovery handshake.** The block
  editor's initial parse writes its own normalisation into the bound textarea and fires
  `input`, which unsaved-changes recovery (`admin.unsavedChanges.js`, run on window
  `load`) reads as typing — a draft nobody typed, stored over the real one, on every open
  of a page whose markdown does not round-trip. `editor.ts` sets
  `data-pw-baseline-pending` on the field synchronously and clears it with a bubbling
  `pw:baseline-ready` at the same point Undo re-baselines; recovery defers its whole init
  until then, so the flag must be in the DOM before `load` and the event must fire even
  if nothing changed.
- **Rebuild core assets after editing js-helper.** `packages/core/src/Resources/public/app.js`
  is compiled — run `yarn build` in `packages/core` after touching `helpers.js`.
- **`pw_auth=1` is a ROLE_EDITOR-only hint.** Cookie set, heal, and 403-clear paths must
  all keep the same role gate; a mismatch makes customer sessions dead-POST the admin
  fragment on every page view. The `admin_buttons` block is wrapped in
  `{% if not isStatic|default(false) %}` so static export omits an endpoint that is
  unreachable behind the static host's allow-list.
- **`liveBlock()` must drop `data-live` on a failed fetch** — it re-runs on every
  DOMChanged, so a retained attribute means an infinite re-fetch loop.
- **Headerless tables need an empty header row.** CommonMark only renders a table when a
  delimiter (hence a header) is present, so a headerless source table is stored as
  `withHeadings=true` with an empty first row; the front's `EmptyTableHeadProcessor` drops
  the all-empty `<thead>` at render. Simple `<table>` HTML converts to a Table block;
  complex tables (colspan/rowspan, nested, block-level cells, non-rectangular) stay Raw.
- Changing render output means bumping `MarkdownParser::CACHE_VERSION`.
