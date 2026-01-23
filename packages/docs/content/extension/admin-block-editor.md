---
title: 'Admin block editor to supercharge the default markdown admin with a rich text editor'
h1: 'Admin Block Editor'
publishedAt: '2025-12-21 21:55'
toc: true
---

Supercharge default admin with a rich text editor wich managed blocks.

## Install

```shell
composer require pushword/admin-block-editor
```

That's it ! If you have a custom installation (not used the [default installer](/installation)),
you may have a look inside `vendor/pushword/admin/install.php`.

Block editor is now ready.

## Configuration

If you want to go forward than a default install, you can override default parameters in your config :

```yaml
admin_block_editor:
  new_page: true # Set false to disable block editor for new page (because new page does not have an associated `app` yet)
```

Or you individually app by app :

```yaml
# set false to disable block editor (and get the default Mardown Editor) for this app
admin_block_editor: true
```

### Customize editor

You want to add a custom block ? This is the path to follow :

1. Create a new [editor.js plugin](https://editorjs.io/the-first-plugin). There are a few examples in #[admin-block-editor/src/assets/tools](https://github.com/Pushword/Pushword/tree/main/packages/admin-block-editor/src/assets/tools)

2. [Override](https://symfony.com/doc/current/bundles/override.html) [@PushwordAdminBlockEditor/editorjs_widget.html.twig](https://github.com/Pushword/Pushword/blob/main/packages/admin-block-editor/src/templates/editorjs_widget.html.twig) to add your custom plugin

I recommend you to import `@PushwordAdminBlockEditor/editorjs_widget.html`.twig and to create only the block **editorjs_block_to_add_new_plugin**

## Storage Format

Content is stored as **markdown** in the database. The editor converts EditorJS blocks to markdown on save, and converts markdown back to EditorJS blocks when loading the editor.

## Usage

### Pages List Block

The search input permit to perfom action like :

- `CHILDREN` will search for children page
- `parent_children` will search for children page from the parent page
- `slug:hellow-world` will search for page with slug being exactly `tagada`
- `slug:hellow-world OR slug:hello-me` ... or operator
- `slug:hellow-world OR pizza OR comment:HELLO-YOU` ...
- `slug:%page%` containing `page` in slug
- `exampleTag` will search in pages's tags for `exampleTag` (case sensitive)
- `comment:HELLO-YOU` will search in pages's main content for `<!--HELLO-YOU-->` (case sensitive)