---
title: 'Page Versioning for Pushword CMS'
h1: Version
publishedAt: '2025-12-21 21:55'
toc: true
---

Keeps a history of every page (and snippet) save, with compare and restore in the
[admin](/extension/admin).

## Install

```shell
composer require pushword/version
```

Register the routes:

```yaml
pushword_version:
  resource: '@PushwordVersionBundle/VersionRoutes.yaml'
```

## Usage

Each create or update stores a serialized snapshot. The page form links to the page's
version list (under the *created at* field), where you compare two versions side by side
or restore one.

Snapshots live in `var/log/version/{id}/` for pages and `var/log/version/{type}/{id}/`
for other entities (`pushword_version.storage_dir`). History is pruned on save: every
version younger than 30 days is kept, then one per day up to 90 days, then one per week.

When [`pushword/snippet`](/extension/snippet) is installed, snippets are versioned too
(a **Versions** action in the Snippet admin).

### Activity log

Every create, update and restore is also recorded, with its author, in the
`version_log` table, shown newest first in the read-only **Activity log** admin page
(filters: type, editor, action, host). The author is empty for `pw:flat:sync` and other
CLI writes. The log is never pruned automatically:

```shell
php bin/console pw:version:log:clear           # wipe the whole log
php bin/console pw:version:log:clear --days=90 # only entries older than 90 days
```

### With [Flat](/extension/flat)

Versioning works alongside flat files; restoring a version needs the admin. Without it,
use Git history.
