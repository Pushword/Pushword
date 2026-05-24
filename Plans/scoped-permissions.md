# Plan — Scoped Permissions (per host / per subtree)

> Status: idea / draft. Coarse-grained scoping via Symfony voters — NOT Sulu's
> fine-grained per-object ACL.

## Goal

Restrict a non-super editor to a subset of content: a given **host** (locale/site)
or a **branch of the page tree**. Cover the realistic multisite need ("this editor
manages altimood.com only") without an object-level ACL layer or audit DB.

## Current state (baseline)

- Auth is Symfony role-based: `ROLE_USER`, `ROLE_EDITOR`, `ROLE_ADMIN`,
  `ROLE_SUPER_ADMIN` (`packages/core/src/Entity/User.php`, JSON `roles`).
- No per-content restriction: any editor can edit any page on any host.
- Pages already carry a `host` (`HostTrait`) and a parent/children tree
  (`PageParentTrait`).

## Design

### Store the scope on the User

Add an optional `allowedHosts` (array) and/or `allowedRootSlug`/`allowedRootPage`
(subtree root) to the User entity — via a trait so projects extending `User`
inherit it cleanly (the override pattern already documented in MEMORY).

- Empty scope = unrestricted (back-compat: existing editors keep full access).

### Enforce with a Voter

`PageVoter` (`Pushword\Core\Security\PageVoter`) implementing `EDIT`/`VIEW`/`DELETE`
on a `Page`:
- `ROLE_SUPER_ADMIN` / `ROLE_ADMIN` → always allow.
- Otherwise: allow only if `page.host ∈ user.allowedHosts` (when set) **and**
  the page is within `user.allowedRootPage`'s subtree (when set).

Subtree check: walk `getParentPage()` up to the root, or precompute a path —
reuse `PageParentTrait` traversal.

### Wire into EasyAdmin (`pushword/admin`)

- Filter the Page CRUD index query by the user's scope (override the
  `createIndexQueryBuilder` / repository query) so editors only *see* their pages.
- Gate the edit/delete actions through `isGranted('EDIT', page)`.
- Restrict the host selector and parent-page selector in the form to allowed values.

### Snippets / Media (optional, later)

Same voter pattern can extend to `Snippet` (if that package lands) and `Media`
(scope by `storeIn`/host), but v1 = pages only.

## Integration points

- `packages/core/src/Entity/User.php` (+ scope trait).
- New `packages/core/src/Security/PageVoter.php` (auto-registered as a service).
- `packages/admin` — index query filter + action gating + form field restriction.
- `config/users.yaml` sync (`pushword/flat`) — persist the scope so users defined
  in YAML carry their host/subtree restriction.

## Open questions

- Scope by **host list** (simplest, matches multisite reality) vs **subtree** vs
  both? Start with host list; add subtree only if asked.
- Where to edit the scope: User CRUD form fields (via `admin_user_form_fields`
  config, already extensible) or only `users.yaml`?
- Media scoping rules are fuzzier (shared library) — defer.

## Non-goals

- No per-page ACL, no per-field permissions, no permission inheritance matrix, no
  audit trail (those are Sulu's enterprise territory, explicitly out of scope).
