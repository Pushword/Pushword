---
title: 'Page Versioning for Pushword CMS'
h1: Version
toc: true
parent: extensions
---

Versioning pages with Pushword CMS.

## Install

```shell
composer require pushword/version
```

Add Routes

```yaml
admin:
  resource: '@PushwordVersionBundle/VersionRoutes.yaml'
# or do it in 1 command line
# $ sed -i '1s/^/version:\n    resource: "@PushwordVersionBundle\/VersionRoutes.yaml"\n/' config/routes.yaml
```

## Usage

This extension automatically (_event sucriber_ on page update and persist) store a serialized version of the current page.

It adds a new [admin](/extension/admin) (reachable for each page under _created at_ field) wich list all versions for a page.

Then you can switch from one or an other version (identified by it _h1_ and the \*_updated date_).

#### Does it work with [Flat](/extension/flat) ?

Yes, but you need to install admin extension to able to restore a previous version from a page. Else you can use a Versioning tool like git.
