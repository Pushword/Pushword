---
title: 'Page Versioning for Pushword CMS'
h1: Version
publishedAt: '2025-12-21 21:55'
toc: true
---

Versioning pages with Pushword CMS.

## Install

```shell
composer require pushword/version
```

Add Routes

```yaml
pushword_version:
  resource: '@PushwordVersionBundle/VersionRoutes.yaml'
```

## Usage

This extension automatically (_event subscriber_ on update and persist) stores a serialized version of the current entity.

It adds a new [admin](/extension/admin) (reachable for each page under the _created at_ field) which lists all versions for a page.

Then you can switch from one version to another (identified by its _h1_ and the _updated date_), or compare two versions side by side.

### Activity journal

Beyond the per-page history, the extension keeps a global **activity log**: every
create / update / restore is recorded with its actor and timestamp into a
`version_log` table, exposed as a read-only admin page (menu **Activity log**)
ordered newest-first. Filter by type, editor, action or host; each row shows the
entity on two lines (H1/title then slug), links to its edit screen when it still
exists, and to the diff.

The log is a denormalized index built at write time, so the page renders from
plain SQL without reopening any snapshot file. The editor is captured for
authenticated admin/API edits and left empty for `pw:flat:sync` / CLI writes.
Unlike the snapshot history (which is pruned over time), the journal is kept as
an audit trail — clear it with:

```shell
php bin/console pw:version:log:clear          # wipe the whole journal
php bin/console pw:version:log:clear --days=90 # only entries older than 90 days
```

### Versioned entities

Pages are versioned out of the box. When [`pushword/snippet`](/extension/snippet)
is installed, content snippets are versioned too: the Snippet admin gains a
**Versions** action opening the same list / compare / restore views. Each entity
type is namespaced on disk (`var/log/version/{type}/{id}/`, pages keeping the
flat `var/log/version/{id}/` path) so ids never collide.

#### Does it work with [Flat](/extension/flat) ?

Yes, but you need to install admin extension to able to restore a previous version from a page. Else you can use a Versioning tool like git.