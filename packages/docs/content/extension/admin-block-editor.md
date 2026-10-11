---
title: 'Admin block editor: configuration and custom tools'
h1: 'Admin Block Editor'
publishedAt: '2025-12-21 21:55'
toc: true
---

Replaces the Markdown editor of the [admin](/extension/admin) with an [Editor.js](https://editorjs.io) block editor. Content is still stored as **Markdown**: blocks are converted on save and parsed back when the editor loads.

## Install

```shell
composer require pushword/admin-block-editor
```

Custom installations (not the [default installer](/installation)): see `vendor/pushword/admin/install.php`.

## Configuration

```yaml
admin_block_editor:
  new_page: true # false: new pages open in the Markdown editor (their site is not known yet)
  admin_block_editor: true # false: Markdown editor everywhere
```

`admin_block_editor` can also be set per site in the app configuration.

### Add a block

1. Write an [Editor.js plugin](https://editorjs.io/the-first-plugin) — examples in [src/assets/tools](https://github.com/Pushword/Pushword/tree/main/packages/admin-block-editor/src/assets/tools).
2. [Override](https://symfony.com/doc/current/bundles/override.html) [`@PushwordAdminBlockEditor/editorjs_widget.html.twig`](https://github.com/Pushword/Pushword/blob/main/packages/admin-block-editor/src/templates/editorjs_widget.html.twig): extend `@!PushwordAdminBlockEditor/editorjs_widget.html.twig` and fill only the `editorjs_block_to_add_new_plugin` block.

Bundles can instead contribute tool configuration per host by implementing
`Pushword\AdminBlockEditor\Editor\EditorJsToolProviderInterface` (the snippet and quiz
bundles do); the `className` must name a tool already shipped in the editor bundle.

Custom tools must keep `exportToMarkdown(data, tunes)` deterministic and free of
side effects: the editor reuses exports while a block's type, data and tunes are
unchanged.

### Heading levels

The heading tool offers H2 to H6. To offer fewer, set the list after
`window.editorjsConfig` is defined and before the editor initializes:

```javascript
window.editorjsConfig.tools.header.config.levels = [2, 3, 4]
```

A heading already at a level the list leaves out keeps it.

### Normalize undo snapshots

Custom tools can exclude transient UI data from undo history with an optional
callback. Set it after `window.editorjsConfig` is defined and before the editor
initializes:

```javascript
window.editorjsConfig.undo = {
  normalizeBlockData(data, { id, type }) {
    if (type === 'customBlock') delete data.uiState
    return data
  },
}
```

The synchronous callback receives a deep copy of a captured block's data and its
`{ id, type }`, and must return block data. Its result is copied into history and
used to restore the block on undo or redo. Capturing leaves the live tool, `id`,
`type` and `tunes` unchanged.

Keep the callback deterministic, idempotent and free of side effects. Unchanged
blocks reuse their normalized snapshots during typing; restored data may be
captured again on later edits.

History captures each block with `block.save()`, retaining empty and temporarily
invalid blocks. A cleanup attached only to `editor.saver.save()` therefore does
not affect history: reuse that cleanup in this callback if needed. Without a
callback, history keeps the saved block data as is.

### Link tool

The link tool's config (`link` in `editorjsConfig.tools`) takes three keys:

- `availableDesigns` — label => class, the *Style* list (defaults: button, button
  outline, discreet);
- `availableRels` — label => rel value, the *Rel* list (defaults: obfuscate,
  nofollow, nofollow sponsored, nofollow ugc);
- `options` — `false` shows the address field alone, without the *New tab* switch,
  *Rel* or *Style* (default: `true`). An existing link keeps the target, rel and
  class it already carries when only its address is edited.

## Usage

### Outline panel

A collapsible left rail lists the blocks in use — in the block editor and in
its Markdown/JSON source views alike. Headings nest their sections, group
markers nest their content, and each row offers:

- click to scroll to the block (highlighted on arrival),
- a caret to fold or unfold a section,
- drag handles — headings carry two, one for the heading alone and one for the
  whole section; keyboard: `Alt+Arrow` moves a block one row, `Alt+Shift+Arrow`
  swaps a section with the neighbouring one, bodies included,
- delete, for the block or the whole section/group (undo restores everything).

The rail overlays the admin navigation; collapse it (state is remembered) to
get the menu back — the opener stays pinned top-left.

### Pages List block

Its search field takes the same syntax as the `pages_list` Twig function: see
[Page lists](/pages-list).
