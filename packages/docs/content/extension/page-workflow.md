---
title: Page Workflow
h1: 'Editorial workflow & pending modifications'
publishedAt: '2026-06-01 09:00'
name: 'Page Workflow'
parent: extensions
---

`pushword/page-workflow` covers two distinct editorial scenarios:

1. **New page lifecycle** (`page_editorial` workflow) — a page that has never been published goes through `draft → in_review → approved`. Once approved, it can be published.
2. **Pending modification on a published page** (`page_pending_modification` workflow) — an editor prepares a change to a live page in isolation. A reviewer compares the proposed payload against the live page and approves or rejects. Approval applies the payload atomically to the live row.

The two workflows are independent. The live page row is **never** in `in_review` because of a pending change: only the `PendingModification` carries that workflow state, while the published row keeps serving.

## Architecture

- `PageEditorialState` — companion entity (1:0..1 to `Page`), holds `workflowState`, `reviewedBy`, `reviewedAt` for the new-page workflow. Lives in its own table; the `Page` entity stays clean.
- `PendingModification` — value object snapshotting the editorial fields (`h1`, `mainContent`, `title`, `name`, `metaRobots`) that an editor proposes. Carries its own workflow state.
- `PendingModificationStorageInterface` — persistence boundary. Two implementations:
  - `FilePendingModificationStorage` (default): JSON snapshot under `var/page-workflow/{id}/pending.json`.
  - `FlatPendingModificationStorage` (auto-active when `pushword/flat` is installed): writes `{slug}.pending.md` next to the live `{slug}.md` so git captures the proposed change.

## Configuration

```yaml
# config/packages/pushword_page_workflow.yaml
pushword_page_workflow:
  editorial_workflow: true              # register page_editorial + page_pending_modification (default true)
  require_approval_before_publish: false # block publishedAt until the page_editorial state is 'approved'
```

When `editorial_workflow: false`, neither workflow is registered and the admin UI hides itself.

## Admin UI

In the EasyAdmin Page edit view:

- **Workflow transition buttons** — appear for whichever transitions are currently enabled on the page's editorial state.
- **Edit (pending)** — opens a form pre-filled with the live content. Saving writes to the `PendingModification` storage, never to the live row.
- **Compare pending** — side-by-side diff between live and proposed values. Available only when a pending modification exists. From there the reviewer can submit, approve, request changes, or discard.

The page index lists each page's current editorial state as a column.

## Hotfix while a pending exists

The system does not block direct edits to the live row while a `PendingModification` is in review. If both happen, the compare view shows the diff between the **current** live and the pending payload — the reviewer sees exactly what approval would change, including any concurrent hotfix that would be overwritten.

## Flat overlay specifics

When `pushword/flat` is installed:

- The pending lives in `{slug}.pending.md` (YAML frontmatter + Markdown body). Git tracks every save.
- `pw:flat:sync` and the file watcher skip `*.pending.md` — only the live `{slug}.md` round-trips with the database.
- On approval: the payload is applied on the Page row, the live `{slug}.md` is rewritten by `PageExporter`, and `{slug}.pending.md` is removed. Git records both operations.

## Permissions

The workflow guards inherit the existing security model:

- `approve` (on either workflow) requires `ROLE_EDITOR` by default.
- `submit` and `request_changes` are open to any authenticated admin.

Override by redeclaring the workflow under `framework.workflows.page_editorial` (or `page_pending_modification`) in your application config — the bundle's defaults are skipped when an entry with the same key already exists.
