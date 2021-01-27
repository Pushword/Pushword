---
title: "Standard Admin for Pushword : Admin User Interface"
h1: Admin
toc: true
parent: extensions
---

Create, edit, delete Page, Media, User with an interface built on top of Sonata Admin.

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
    new_page: true # Set false to disable block editor for new page (because new page does not have app yet)
```

Or you individually app by app :

```yaml
# set false to disable block editor (and get the default Mardown Editor) for this app
admin_block_editor: true

# permit to disable listener, if you want front-end works, see further in the docs
admin_block_editor_disable_listener: false

# permit to use all the blocks pre-configured in this extension
admin_block_editor_blocks: \Pushword\AdminBlockEditor\Block\DefaultBlock::AVAILABLE_BLOCKS

admin_block_editor_type_to_prose: [
        "paragraph",
        "image",
        "list",
        "blockquote",
        "code",
    ] # leave empty if you don't want a prose container around this blocks
```

### Disable Listener and use filter

This extension is built like an [entity filter](/entity-filter).

By default, a listener apply `Pushword\AdminBlockEditor\BlockEditorFilter` before the defined filters in your app's config only on `mainContent`.

If you want to reorder the filters, just disable `admin_block_editor_disable_listener` and add `Pushword\AdminBlockEditor\BlockEditorFilter` in you app's config.

### Override block template

You just find the block you want to override in [./src/templates/block](https://github.com/Pushword/Pushword/tree/main/packages/admin-block-editor/src/templates/block) and override it in your `templates/my-app.tld/block/my-block.html.twig`
