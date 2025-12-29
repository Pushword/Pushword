---
title: 'Template Editor with Pushword CMS'
h1: 'Template Editor'
id: 25
publishedAt: '2025-12-21 21:55'
parentPage: extensions
toc: true
---

Edit view file online in the [admin](https://pushword.piedweb.com/extension/admin).

## Install

```shell
composer require pushword/template-editor
```

That's it ! If you have a custom installation (not used the [default installer](/installation)),
you may have a look inside `vendor/pushword/admin/install.php`.


## Configuration

In your config folder, you can add two configuration parameters :

```shell
pushword_template_editor:
  disable_creation: false
  can_be_edited_list:
    - "/pushword.piedweb.com/page/_footer.html.twig"
```

* `disable_creation` permit to disable creation for ROLE_ADMIN
* `can_be_edited_list` permit to limit list to the defined template files with their relative path in the template dir.