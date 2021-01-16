---
title: Template Editor with Pushword CMS
h1: Template Editor
toc: true
parent: extensions
---

Edit view file online in the [admin](https://pushword.piedweb.com/extension/admin).

## Install

```shell
composer require pushword/template-editor
```

Add Routes

```yaml
template_editor:
  resource: '@PushwordTemplateEditorBundle/TemplateEditorRoutes.yaml'
```

Add routes via 1 command line :

```
sed -i '1s/^/template_editor:\n    resource: "@PushwordTemplateEditorBundle\/TemplateEditorRoutes.yaml"\n/' config/routes.yaml
```
