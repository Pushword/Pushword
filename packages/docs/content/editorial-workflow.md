---
title: 'Editorial Workflow - Draft, Review, Approved'
h1: Editorial Workflow
publishedAt: '2026-05-24 12:00'
toc: true
---

A lightweight, config-driven editorial flow (draft → in review → approved) built on [symfony/workflow](https://symfony.com/doc/current/workflow.html). It lets small teams move a page through explicit states and record who validated it. It is **not** a full multi-stage approval engine — but the states, transitions, and guards are fully customizable, and the whole feature is opt-out.

## What it adds

Three fields on `Page`:

- `workflowState` (string, default `draft`) — the editorial place, backed by a `symfony/workflow` state machine.
- `reviewedBy` (User, nullable) — who approved the page.
- `reviewedAt` (datetime, nullable) — when it was approved.

`publishedAt` stays the source of truth for *visibility*; `workflowState` is the *editorial* layer on top.

## Default workflow

Pushword ships a `page_editorial` state machine:

- **places**: `draft`, `in_review`, `approved`
- **transitions**: `submit` (draft → in_review), `approve` (in_review → approved, guarded by `ROLE_EDITOR`), `request_changes` (in_review/approved → draft)

When a page enters `approved`, a subscriber records `reviewedBy` (current user) and `reviewedAt`, mirroring the `createdBy`/`editedBy` convention.

In the admin, the page form shows the current state plus the transition buttons the current user is allowed to apply (guards are enforced). The page list gains a `workflowState` column and filter.

## Customizing the workflow

Redefine `page_editorial` in your app config (`config/packages/workflow.yaml` or `framework.workflows`) — add places, split states, change guards. When an app already defines a `page_editorial` workflow, Pushword skips its default, so there is no core fork:

```yaml
framework:
    workflows:
        page_editorial:
            type: state_machine
            marking_store: { type: method, property: workflowState }
            supports: [Pushword\Core\Entity\Page]
            initial_marking: draft
            places: [draft, in_review, approved]
            transitions:
                submit:          { from: draft,                to: in_review }
                approve:         { from: in_review,            to: approved, guard: "is_granted('ROLE_EDITOR')" }
                request_changes: { from: [in_review, approved], to: draft }
```

## Requiring approval before publishing

Off by default. When enabled, a page can only stay published (`publishedAt`) once its `workflowState` is `approved` — enforced in `PageListener` so it covers admin, flat-sync, and API writes uniformly:

```yaml
pushword:
    require_approval_before_publish: true   # default: false
```

## Disabling the feature

```yaml
pushword:
    editorial_workflow: false   # default: true
```

When `false`, the default `page_editorial` workflow is not registered and the admin hides its workflow field, column, and filter. The `workflowState` column stays in the schema (inert, defaults to `draft`). Keep `require_approval_before_publish` at `false` when disabling, otherwise publishing would be blocked with no way to reach `approved`.

## Flat files

`workflowState` round-trips through the page frontmatter like other content. `reviewedBy` and `reviewedAt` are intentionally **not** written to flat files — consistent with `createdBy`/`editedBy`, git is the audit trail.

## Versioning

The [version extension](/extension/version) already snapshots on update, so transitions become part of the page's version history for free.
