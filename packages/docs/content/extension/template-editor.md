---
title: 'Template Editor with Pushword CMS'
h1: 'Template Editor'
publishedAt: '2025-12-21 21:55'
toc: true
---

Edit Twig templates online from the [admin](/extension/admin).

## Install

```shell
composer require pushword/template-editor
```

Custom installations (not the [default installer](/installation)): see `vendor/pushword/admin/install.php`.

## Configuration

```yaml
pushword_template_editor:
  disable_creation: false
  can_be_edited_list:
    - '/pushword.piedweb.com/page/_footer.html.twig'
```

- `disable_creation` — forbid creating templates.
- `can_be_edited_list` — restrict editing to these paths, relative to the templates
  directory (empty: every template).

Both restrictions apply to every admin except `ROLE_SUPER_ADMIN`.
