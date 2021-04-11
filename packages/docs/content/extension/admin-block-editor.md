---
title: "Admin block editor to supercharge the default markdown admin with a rich text editor"
h1: Admin Block Editor
toc: true
parent: extensions
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

# permit to disable listener, if you want front-end works, see further in the docs
admin_block_editor_disable_listener: false

# permit to use all the blocks pre-configured in this extension
admin_block_editor_blocks: \Pushword\AdminBlockEditor\Block\DefaultBlock::AVAILABLE_BLOCKS

admin_block_editor_type_to_prose: ["paragraph", "image", "list", "blockquote", "code"] # leave empty if you don't want a prose container around this blocks
```

### Disable Listener and use filter

This extension is built like an [entity filter](/component/entity-filter).

By default, a listener apply `Pushword\AdminBlockEditor\BlockEditorFilter` before the defined filters in your app's config only on `mainContent`.

If you want to reorder the filters, just disable `admin_block_editor_disable_listener` and add `Pushword\AdminBlockEditor\BlockEditorFilter` in you app's config.

**Performance** : It's recommanded to use only `BlockEditorFilter` to increase speed on generating page.

### Override block template

You just find the block you want to override in [./src/templates/block](https://github.com/Pushword/Pushword/tree/main/packages/admin-block-editor/src/templates/block) and override it in your `templates/my-app.tld/block/my-block.html.twig`

### Customize editor

You want to add a custom block ? This is the path to follow :

1. Create a new [editor.js plugin](https://editorjs.io/the-first-plugin). There are a few examples in #[admin-block-editor-tools](https://github.com/Pushword/Pushword/tree/main/packages/admin-block-editor-tools)

2. [Override](https://symfony.com/doc/current/bundles/override.html) [@PushwordAdminBlockEditor/editorjs_widget.html.twig](https://github.com/Pushword/Pushword/blob/main/packages/admin-block-editor/src/templates/editorjs_widget.html.twig) to add your custom plugin

I recommend you to import `@PushwordAdminBlockEditor/editorjs_widget.html`.twig and to create only the block **editorjs_block_to_add_new_plugin**

3. Add in your _app_ configuration with **admin_block_editor_blocks**

Your configuration may looks like

```yaml
admin_block_editor_blocks: [
      'paragraph',
      'list',
      'header',
      'raw',
      'quote',
      'code',
      'list',
      'delimiter',
      'table',
      'image',
      'embed',
      'attaches',
      'pages_list',
      'gallery',
      '\App\Block\MyCustomBlock`
  ]
```

4. Create `\App\Block\MyCustomBlock` wich implements #[BlockInterface](https://github.com/Pushword/Pushword/tree/main/packages/admin-block-editor/src/Block/BlockInterface.php) or inherit #[AbstractBlock](https://github.com/Pushword/Pushword/tree/main/packages/admin-block-editor/src/Block/AbstractBlock.php)
