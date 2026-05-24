# Plan — Lightweight Editorial Workflow

> Status: **implemented** (core + admin + flat). A small, **config-driven** draft →
> review → approved flow built on `symfony/workflow`. NOT Sulu's full approval engine,
> but states/transitions are end-user customizable. **Fully backward compatible.**
>
> Deferred: per-transition email notification (the existing page-update-notifier is an
> interval digest, not transition-based — a review email would be a net-new channel,
> out of the lightweight scope). `reviewedBy`/`reviewedAt` are intentionally NOT written
> to flat frontmatter, consistent with `createdBy`/`editedBy` (git is the audit trail);
> only `workflowState` round-trips.

## Goal

Let small teams move a page through explicit editorial states and record who validated
it, while letting each project redefine the states/transitions to its own needs.
Capitalise on what already exists: `publishedAt` for publish visibility,
`pushword/version` for history, git for the flat-file audit trail.

## Current state (baseline, verified)

- Publish state is implicit: `Page::isPublished()` = `publishedAt` not null and ≤ now
  (`packages/core/src/Entity/Page.php:291`). Setter at `:244`; field at `:129`.
- `createdBy` / `editedBy` (ManyToOne `User`) at `Page.php:67-71`, auto-populated by
  `PageListener::updatePageEditor()` (`packages/core/src/EventListener/PageListener.php:77`)
  on Doctrine `prePersist`/`preUpdate`.
- Admin publish toggle: `PageCrudController::togglePublished()`
  (`packages/admin/src/Controller/PageCrudController.php:483`).
- `symfony/workflow` is **NOT** yet a dependency (absent from `core` and `skeleton`
  composer.json). Symfony 8 container already supports `framework.workflows`.
- No notion of "ready for review" or "who approved".

## Resolved design decisions

1. **Location: `packages/core`, via a trait — not a separate package.** Feature is
   opt-in through config, following the existing `PageTrait/` composition pattern.
2. **Mechanism: guarded state machine via `symfony/workflow`** (chosen over a
   hardcoded enum). States, transitions, and guards are declared in config, so end
   users customize the workflow without forking Pushword code.
3. **Block publishing unless `approved`: configurable, default OFF**
   (`require_approval_before_publish: false`).

## Backward compatibility

The change is additive and BC, provided we handle the existing-rows default:

- **New columns only.** `workflowState`, `reviewedBy`, `reviewedAt` are added; no
  existing column/method changes signature. `Page::isPublished()` is untouched.
- **Publish behaviour unchanged by default.** `require_approval_before_publish: false`
  means `setPublishedAt()` / `togglePublished()` keep working exactly as today. The
  gate is pure opt-in.
- **Existing-rows marking caveat (the one real BC trap).** A naive column default of
  `'draft'` would mark every already-published page as editorially `draft`. To stay
  BC, initialize existing/live content as `approved`:
  - column default `'draft'` for *new* pages, but a one-off backfill
    (`UPDATE page SET workflow_state = 'approved' WHERE published_at IS NOT NULL`)
    run from the schema-update step, or
  - derive the marking lazily: treat a null/empty marker as `approved` when
    `isPublished()` is true. Prefer the explicit backfill so the workflow component
    always sees a valid place.
- **Admin is additive.** The field is injected via the `load_field` event; projects
  that don't pull the new default keep their current form.
- **Flat is additive.** Frontmatter without `workflowState` deserializes to the
  default — old `.md`/index files round-trip unchanged.
- **New dependency.** Adding `symfony/workflow` to `packages/core/composer.json` is a
  composer update, not a code break.

## Design

### Persisted marker on Page

A single string property backs the workflow (the marker store); the *values* and
*transitions* are config-driven, so the column stays generic.

- `workflowState: string` (Doctrine column, default `'draft'`), added via a small
  `PageTrait/WorkflowTrait`. Existing live pages backfilled to `approved` (see BC).
- `reviewedBy` (ManyToOne `User`, nullable), `reviewedAt` (datetime, nullable) —
  mirroring the `createdBy`/`editedBy` field style.

### Workflow definition (customizable)

Pushword ships a default `symfony/workflow` definition (state_machine type):

- places: `draft`, `in_review`, `approved`
- transitions: `submit` (draft→in_review), `approve` (in_review→approved),
  `request_changes` (in_review/approved→draft)
- `marking_store`: `method` on `workflowState`
- guards: e.g. `approve` requires `ROLE_EDITOR` (guard expression)

End users override/extend by redefining the workflow in their app config
(`config/packages/workflow.yaml` or `framework.workflows`) — add places, split
states, change guards — with no core change.

### Set reviewer on transition

A workflow event subscriber (on the `approve` / `completed` transition) sets
`reviewedBy = current user` and `reviewedAt = now`, mirroring `updatePageEditor`.

### Gate publishing (optional)

When `pushword.require_approval_before_publish: true`, enforce in `PageListener`
(`prePersist`/`preUpdate`, alongside `updatePageEditor`) so it covers admin,
flat-sync, and API writes uniformly — clear `publishedAt` (or reject) unless
`workflowState === approved`. `publishedAt` stays the source of truth for
*visibility*; `workflowState` is the *editorial* layer on top.

### Admin (`pushword/admin`)

- Add the state field + transition buttons in the EasyAdmin page form, gated by
  the workflow guards (role). Inject via the existing `admin_page_form_fields`
  config + `pushword.admin.load_field` event (`PushwordEvents::ADMIN_LOAD_FIELD`,
  dispatched in `AdminFormFieldManager::getFormFields()`) — no core fork.
  New `PageWorkflowStateField` extending `AbstractField`.
- A filter/column to list pages "in review".

### Flat-file (`pushword/flat`)

- Persist `workflowState` / `reviewedBy` in the page frontmatter so editorial state
  round-trips through git like everything else.

### Notifications (`pushword/page-update-notifier`)

- Optionally fire an email when a page enters `in_review` (reuse existing subscriber,
  hooked to the workflow `entered` event for that place).

### Version (`pushword/version`)

- Already snapshots on update — transitions become part of version history for free.
  No change needed.

## Config additions

```yaml
# packages/core Configuration.php — new node under `pushword`
pushword:
    require_approval_before_publish: false   # default

# default workflow shipped by core (state_machine), user-overridable
framework:
    workflows:
        page_editorial:
            type: state_machine
            marking_store: { type: method, property: workflowState }
            supports: [Pushword\Core\Entity\Page]
            initial_marking: draft
            places: [draft, in_review, approved]
            transitions:
                submit:          { from: draft,            to: in_review }
                approve:         { from: in_review,        to: approved }
                request_changes: { from: [in_review, approved], to: draft }
```

## Integration points (file map)

| Concern | File |
|---|---|
| Entity fields | `packages/core/src/Entity/Page.php` + new `PageTrait/WorkflowTrait.php` |
| Publish gate + reviewer set | `packages/core/src/EventListener/PageListener.php` |
| Config flag | `packages/core/src/DependencyInjection/Configuration.php` |
| Default workflow | `packages/core/config/` (ship a `workflow.yaml`) |
| Admin field | `packages/admin/src/FormField/PageWorkflowStateField.php` + `DEFAULT_ADMIN_PAGE_FORM_FIELDS` |
| Flat round-trip | `packages/flat` (de)serialization |
| Notification | `packages/page-update-notifier` subscriber |
| Dependency | add `symfony/workflow` to `packages/core/composer.json` |

## Implementation order (each step verifiable)

1. Add `symfony/workflow` dep + `WorkflowTrait` (fields) → `doctrine:schema:update --force`,
   schema has columns; backfill live pages to `approved`.
2. Ship default workflow definition + `require_approval_before_publish` config →
   `debug:config pushword` shows flag; `console workflow:dump page_editorial` renders.
3. Reviewer subscriber + publish gate in `PageListener` → unit test: approving sets
   `reviewedBy/At`; with flag on, non-approved page cannot get `publishedAt`; with flag
   off, publishing behaves exactly as before (BC test).
4. Admin field + transition buttons via `load_field` event → functional test / browser check.
5. Flat (de)serialization → round-trip test (`pw:flat:sync`).
6. Optional notifier hook.

## Open questions (resolved)

- ~~Core trait vs separate package?~~ → **core trait.**
- ~~Free enum vs guarded state machine?~~ → **symfony/workflow state machine;
  states/transitions/guards user-customizable via config.**
- ~~Block publishing unless approved?~~ → **configurable, default false.**

## Non-goals

- No parallel review assignments, no comment/annotation threads on revisions, no
  scheduled-publish UI (explicitly dropped in discussion).
