---
title: 'Standard Admin for Pushword : Admin User Interface'
h1: Admin
toc: true
parent: extensions
---

Create, edit, delete Page, Media, User with an interface built on top of Sonata Admin.

## Install

```shell
composer require pushword/admin
```

Add Routes

```yaml
admin:
  resource: '@PushwordAdminBundle/AdminRoutes.yaml'
```

or via cli

```
sed -i '1s/^/admin:\n    resource: "@PushwordAdminBundle/AdminRoutes.yaml"\n/' config/routes.yaml
```
