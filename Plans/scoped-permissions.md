# Plan — Scoped Permissions (per host)

## Goal

Restrict a non-super editor to a subset of content: a given **host** (locale/site).
Cover the realistic multisite need ("this editor manages altimood.com only")
without an object-level ACL layer or audit DB.

v1 is **host-list only**. Subtree (branch-of-tree) scoping is deferred — see
"Deferred" below.

## Current state (baseline)

- Auth is Symfony role-based: `ROLE_USER`, `ROLE_EDITOR`, `ROLE_ADMIN`,
  `ROLE_SUPER_ADMIN` (`packages/core/src/Entity/User.php`, JSON `roles`).
- No per-content restriction: any editor can edit any page on any host.
- Pages already carry a `host` (`HostTrait`) and a parent/children tree
  (`PageParentTrait`, plain `parentPage` FK — no materialized path).
- `User` is a `MappedSuperclass`; projects extend it (e.g. `App\Entity\User`).
- `User` has no relation to `Page` today.

## Scope of enforcement (read this first)

This is a **UI guard for interactive editors**, not a server-wide authorization
boundary. The Voter only fires where `isGranted` is called (EasyAdmin). Other
write paths bypass it: `FlatFileSync` (filesystem/webhook sync, runs as CLI with
no user), the Media/page API, block-editor autosave. Scoping prevents a logged-in
editor from seeing/editing off-host pages in the admin; it does not lock down the
data layer.

## Design

### Store the scope on the User

Add an optional `allowedHosts` (array/JSON column) to `User` via a
`ScopedAccessTrait`, so projects extending `User` inherit it through the
MappedSuperclass.

- Empty `allowedHosts` = unrestricted (back-compat: existing editors keep full
  access). **Fail-open** — surface the "Unrestricted" state in the User CRUD form
  so it is never silent.
- A dedicated column (not the existing `customProperties` JSON bag) because the
  index filter needs `WHERE host IN (...)`, which is awkward against SQLite JSON.
- No `User → Page` relation (no subtree root in v1), so no `resolve_target_entities`
  and no `doctrine.associationType` PHPStan noise.

### Enforce with a Voter

`PageVoter` (`Pushword\Core\Security\PageVoter`) supporting `VIEW`/`EDIT`/`DELETE`
on a `Page`:

- `ROLE_SUPER_ADMIN` / `ROLE_ADMIN` → always allow.
- Empty `allowedHosts` → allow.
- Otherwise allow only if `page.host ∈ user.allowedHosts`.

First voter in the codebase — confirm it auto-registers via the autoconfigured
`Security\Voter` tag.

### Wire into EasyAdmin (`pushword/admin`)

- `PageCrudController::createIndexQueryBuilder` → append
  `WHERE entity.host IN (:allowedHosts)` when the current user is scoped (alongside
  the existing redirection/cheatsheet filters), so editors only _see_ their pages.
- `configureCrud` → `->setEntityPermission('EDIT')` so EasyAdmin runs `PageVoter`
  against the subject for detail/edit/delete automatically (the detail/edit fetch
  by ID bypasses the index query, so the Voter is what stops direct-URL access).
- Restrict the host selector in the form to `allowedHosts` for scoped users.

### `users.yaml` sync (`pushword/flat`)

`UserSync::createUser`/`updateUser` hardcode the array shape
`{email, roles?, locale?, username?}` (PHPStan docblocks + create/update logic).
Extend both methods and the shape annotations to carry `allowedHosts`, so
YAML-defined editors get their scope.

### Tests

- `PageVoter` unit test: scoped editor (in/out of host), unscoped editor, admin.
- Functional: a scoped editor's index excludes off-host pages, and a direct edit
  URL for an off-host page is denied.

## Integration points

- `packages/core/src/Entity/User.php` (+ `ScopedAccessTrait`).
- New `packages/core/src/Security/PageVoter.php`.
- `packages/admin` — index query filter + `setEntityPermission` + host selector
  restriction.
- `packages/flat/src/Sync/UserSync.php` — extend the user shape.

## Open questions

- Where to edit the scope: User CRUD form fields (via `admin_user_form_fields`
  config, already extensible) and/or `users.yaml`? Default: both.

## Deferred

- **Subtree scoping.** The page tree is a plain `parentPage` FK with no
  materialized path, so "show only pages under root X" can't be a simple `WHERE` —
  it needs a recursive CTE or a path column. The per-entity Voter check is easy
  (walk `getParentPage()`), but the index filter is not. Defer until a path column
  exists; store the root as a slug string (`allowedRootSlug`), not a `User → Page`
  relation, to avoid coupling and `resolve_target_entities`.
- **Snippet / Media scoping.** Same voter pattern could extend to `Snippet` and
  `Media` (scope by `storeIn`/host), but rules are fuzzier (shared library).

## Non-goals

- No per-page ACL, no per-field permissions, no permission inheritance matrix, no
  audit trail .
- Audit trail is already handled by design: `reviewedBy`/`reviewedAt` are editorial
  metadata kept in DB, and git is the audit trail for flat content
  (`packages/flat/src/Exporter/PageExporter.php`).

## Out of scope, related follow-up (not part of this plan)

Controlling **who may approve a page** is a workflow concern, not a permission
concern. `reviewedBy`/`reviewedAt` are not editable fields — they are stamped
automatically by `PageWorkflowSubscriber::onApproved` when a page enters the
`approved` place of the `page_editorial` workflow. To restrict approvals, add a
`workflow.page_editorial.guard.approve` listener keyed on role/scope; the stamping
already exists, only the guard is missing. This is deliberately separate from
host-scoping and does not reopen the per-field-ACL non-goal.
