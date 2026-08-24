# Plan — Host-scoped editor access in Admin and API

## Goal

Allow an end user with `ROLE_EDITOR` to manage only an explicit list of sites,
using either the admin or the API token attached to the same `User`.

Example:

```yaml
users:
  - email: editor@altimood.com
    roles: [ROLE_EDITOR]
    allowedHosts: [altimood.com]
```

For this user, `altimood.com` is the only editable and visible host in both
interfaces. In particular:

- admin lists, filters, menus, form choices and association pickers do not expose
  another host;
- direct admin URLs and custom admin actions for another host are denied;
- API list totals and rows are restricted, even when the `host` query parameter is
  omitted;
- API reads, creates, updates, deletes and operational actions for another host are
  denied;
- changing an allowed entity's `host` to a forbidden host is denied before flush;
- the editor can browse and use the entire media library and upload new media, but
  cannot mutate a media referenced by a Page on a forbidden host;
- the bearer token needs no separate scope configuration because it resolves the
  same `User` as the admin login;
- existing unrestricted editors and administrators keep their current access.

This is an authorization boundary for authenticated admin and `/api` HTTP entry
points. Public site reads, CLI commands, Messenger jobs and trusted filesystem sync
do not run as an interactive editor and are not changed by this plan.

## Why the previous design is insufficient

The earlier draft described an EasyAdmin UI guard and explicitly left the API as a
bypass. That does not meet the goal. The current repository has more bypasses to
cover:

- `/api` uses `ApiTokenAuthenticator` to load the same `User`, but the firewall only
  checks `ROLE_EDITOR`; it has no host authorization;
- `PageApiController` can search, preview, create, read, update and delete pages;
- page-backed redirections and raw Markdown page writes are separate controllers;
- optional bundles dynamically contribute API controllers for snippets,
  conversations, reviews, notifications, quiz results, repurposing, scans, static
  generation and newsletter data;
- some endpoints address a host in the route, some in a query or JSON body, and
  others load a host-bearing entity by numeric ID;
- all-host operations currently use an omitted host;
- EasyAdmin entity permission does not cover standalone routes such as inline page
  updates, publication toggles, page locks, block previews or frontend admin
  fragments;
- several extension CRUD controllers do not extend Pushword's
  `AbstractAdminCrudController`.

Hiding rows is useful UX, but every read and mutation path still needs an explicit
authorization check.

## Permission model

### Store the scope on `User`

Add a nullable JSON `allowedHosts` property directly to the mapped superclass
`Pushword\Core\Entity\User`:

- `null` means unrestricted;
- `[]` means no host access;
- a non-empty list means access to exactly those configured canonical main hosts;
- `ROLE_ADMIN` and `ROLE_SUPER_ADMIN` remain unrestricted regardless of the stored
  value. Host scope is an editor restriction, not a replacement for the existing
  role hierarchy.

Using `null` for unrestricted avoids the dangerous "empty means everything" rule
and makes it possible to disable host access without changing the user's role.
Existing rows receive `null`, preserving current behaviour after the schema update.

Use one setter to trim, deduplicate and sort the list. The admin form and
`users.yaml` sync use `SiteRegistry` to canonicalize aliases and reject unknown hosts
rather than silently storing typos. Authorization remains an exact comparison with
the stored canonical host; a stale host removed from site configuration grants
nothing.

Do not add a `User -> Page` relation or store this in `customProperties`. The scope
is a first-class security setting and list filtering needs a normal value that works
consistently on SQLite, PostgreSQL and MariaDB.

### One policy used everywhere

Add a small core service, `Pushword\Core\Security\HostScope`, as the single source
of truth. It should expose operations equivalent to:

```php
public function isRestricted(UserInterface $user): bool;

/** @return list<string>|null null means unrestricted */
public function allowedHosts(UserInterface $user): ?array;

public function allows(UserInterface $user, string $host): bool;
```

The policy must account for Symfony's role hierarchy when applying the
`ROLE_ADMIN` bypass. Controllers must not duplicate checks against raw role arrays.

Add a `HostVoter` with a namespaced attribute such as `HOST_ACCESS`. Its subject is
either a host string or an entity implementing
`Pushword\Core\Entity\SharedTrait\HostInterface`. This supports:

- entity checks after a row has been loaded;
- route/body host checks before creating anything;
- EasyAdmin `setEntityPermission()` for direct entity URLs and association choices.

The voter abstains for unsupported subjects. Query filtering remains explicit; a
voter cannot remove forbidden rows from a collection query.

Do not use a request-enabled Doctrine filter. It would be difficult to reason about
in workers and CLI processes, would not stop off-host creates or filesystem side
effects, and would not cover entities whose owning field is named `mainHost` or
`hosts`.

## Enforcement rules

Apply these rules consistently in admin and API:

1. **Collections:** append an `IN (:allowedHosts)` predicate before count and
   pagination. An explicit forbidden `host` filter is a `403`, not an empty success.
2. **Existing items:** authorize the loaded entity before serializing it or invoking
   any action. Forbidden direct access returns `403`.
3. **Creates:** authorize the route/body host before constructing or persisting the
   entity.
4. **Updates that can move an entity:** authorize both the original entity and the
   requested destination host before any service that flushes. This includes Page
   frontmatter and raw Markdown uploads.
5. **Deletes and side effects:** authorize before lock acquisition, file writes,
   background dispatch, rendering, mail scheduling or database mutation.
6. **Global host values:** an empty host (for example a global Snippet) affects every
   site and is therefore unrestricted-only.
7. **All-host operations:** when a restricted user calls an operation whose omitted
   host means "all sites", require an explicit allowed host. Do not silently turn a
   whole-site operation into several jobs with different semantics.
8. **Responses:** API denials return a stable JSON `403` response such as
   `{"error":"forbidden_host"}`. Document it in OpenAPI. Admin denials use Symfony's
   normal access-denied page.

The scope check must happen before `PageWriter`, because that service currently
flushes a valid update internally and Page frontmatter can change `host`.

## Admin implementation

### Pages and redirections

In `pushword/admin`:

- filter `PageCrudController::createIndexQueryBuilder()` by the current editor's
  hosts, alongside the existing redirection and cheatsheet predicates;
- apply `HOST_ACCESS` as the EasyAdmin entity permission to Page and redirection
  CRUD;
- restrict the host filter, host form field and host/locale menu branches to allowed
  hosts;
- authorize the submitted host again on persist/update so a crafted form POST cannot
  move a page off-scope;
- restrict Page association fields (`parentPage`, `variantOf`, translations and any
  extension field selecting Pages) to allowed entities;
- authorize clone, promote-variant, publication/hold toggles and inline updates;
- authorize `PageLockController`, `PageBlockController` and
  `AdminFragmentController` after resolving their Page. A new-page block preview
  must carry and authorize its host explicitly;
- hide and deny the global cheatsheet for a restricted editor because its empty host
  is not site-owned.

The index predicate is not a substitute for `setEntityPermission()`: EasyAdmin
loads edit/detail/delete entities by ID without using the index query.

### Media: authorize mutations from tracked usage

Do not add a host column to `Media`. Media is a shared library, and Pushword already
tracks its Page references in `media_usage`. Add a query such as
`MediaUsageRepository::findHostsForMedia()` that selects the distinct `Page.host`
values for a media.

For a scoped editor, define:

```text
usageHosts(media) = distinct hosts of Pages in media_usage
canMutate(media) = usageHosts(media) is a subset of user.allowedHosts
```

Consequently:

- every editor can list, search, inspect, resolve and select every media in the
  shared library;
- every editor can upload/create a media through the Media CRUD, multi-upload,
  block editor and Media API;
- an unreferenced media has no forbidden usage and can be modified or deleted;
- a media used only on allowed hosts can be renamed, replaced, rotated, have its
  metadata changed or be deleted;
- a media used on both an allowed and a forbidden host cannot be modified or
  deleted;
- a media used only on forbidden hosts remains visible and selectable, but is
  read-only for that editor;
- administrators and unrestricted editors keep full mutation access.

Put this rule in one `MediaUsageScope`/mutation-guard service used by admin and API.
The guard returns the sorted list of blocking hosts rather than only a boolean. The
admin must display an explicit translated message such as "This media is used on
hosts you cannot access: example.com. It cannot be modified or deleted." The API
returns `403` with a stable shape such as:

```json
{
  "error": "media_used_outside_scope",
  "blockingHosts": ["example.com"]
}
```

Check the guard before every side effect: EasyAdmin update/delete, file replacement,
rename, rotate, inline metadata/license update, multi-delete and API metadata
POST/PATCH or DELETE. Upload remains allowed; when deduplication returns an existing
Media it must not silently mutate that existing row.

The media detail "used on" panel must show full Page details only for allowed hosts.
For forbidden usages it shows the blocking host names needed to explain the denial,
not off-scope Page titles or slugs.

`MediaMultiUploadController` is currently class-gated by `ROLE_PUSHWORD_ADMIN`.
Change its coarse requirement to `ROLE_EDITOR`, then protect its mutation routes with
the same usage guard. This makes upload available without making inline update or
delete unconditional.

The existing tracker covers Page main images, Markdown body references and Page
custom properties. It deliberately does not scan Twig templates, so template-only
references cannot participate in this host decision. Keep that limitation explicit:
"no tracked Page usage" does not mean "safe to delete from every possible runtime
consumer". Rebuilding `media_usage` is a deployment prerequisite before enabling
scoped permissions.

### Other editor-visible admin resources

Every admin resource reachable by `ROLE_EDITOR` must be classified during this
change; leaving it unchanged would preserve a cross-host escape.

| Resource | v1 behaviour for a scoped editor |
| --- | --- |
| Conversation and Review | Filter lists, authorize items and custom inline/toggle/media actions, restrict host field |
| Snippet | Filter to allowed hosts; exclude and deny global (`host = ''`) snippets; authorize create/update/delete |
| QuizResult | Filter list and detail by host |
| AdminNotification | Filter list and authorize detail/delete by its `host` field |
| SocialPost / Repurpose | Filter/authorize host-owned rows; also guard studio and export routes that load by ID |
| Media | Show the complete library and allow upload; authorize update/delete from tracked Page usage hosts |
| Newsletter Audience | Filter and authorize by canonical `mainHost` |
| Other Newsletter resources | Deny and hide; contacts, campaigns, automations, deliveries and recipients have indirect or multi-host ownership |
| User management and site-wide tools | Keep their existing admin-only role requirement; scope does not grant access |

Prefer a small reusable query helper for the repeated `IN` predicate, but keep
authorization visible in each CRUD/controller. Do not introduce a generic ACL
framework.

Menu filtering is only presentation. Direct controller and entity checks remain
mandatory even when a menu item is hidden.

## API implementation

Keep `ROLE_EDITOR` as the coarse firewall/controller requirement, then apply host
scope inside every data or operational endpoint.

### Host-aware endpoints to adapt

| API area | Host source | Required behaviour |
| --- | --- | --- |
| Page search | optional query | Implicitly filter rows and `total`; reject an explicit forbidden host |
| Page preview | JSON body | Require an allowed host for restricted users |
| Page create/item | route, plus update frontmatter | Authorize route host and any destination host before `PageWriter` |
| Redirection list/create/item | query, route or loaded Page | Filter list; authorize every item and mutation |
| Raw page file create/update | route, plus uploaded frontmatter | Authorize route and destination before `PageWriter` |
| Content snapshot | optional query | Restricted users must supply one allowed host; never stream the base directory |
| Flat lock/unlock/status | JSON body or query | Apply the scope after the controller's manual token validation and before lock access |
| Snippet | query, route or loaded entity | Filter lists; global snippets are unrestricted-only |
| Conversation and Review | query, JSON body or loaded entity | Filter lists; authorize original and submitted host |
| Notification | query or loaded entity | Filter lists and authorize read/read-marker/delete actions |
| Quiz results and stats | optional query | Filter ordinary lists; require an explicit allowed host for aggregate stats |
| Repurpose | route or loaded SocialPost | Authorize natural-key routes and ID-based preview/slide routes |
| Static generation | optional route | Restricted users must supply one allowed host before status reads, rendering or dispatch |
| Page scan and link graph | optional/required query | Require one allowed host for restricted users before reading results or dispatching work |
| Newsletter Audience | query, body `mainHost` or loaded entity | Filter and authorize by canonical `mainHost` |
| Media | loaded Media plus tracked Page usages | Keep list/GET/upload available; guard metadata POST/PATCH and DELETE from the distinct usage hosts |

For collection queries, apply the host predicate before cloning the count query so
pagination metadata cannot reveal off-host row counts.

### Scope-neutral endpoints

The following do not expose or mutate site-owned data and may remain available to a
scoped editor:

- `/api/docs`;
- `/api/whoami`;
- the editor sync JavaScript;
- schema/validation metadata endpoints that operate only on the supplied transient
  document.

Extend `/api/whoami` and its schema with `allowedHosts: string[]|null`, where `null`
means unrestricted, so a token client can discover its effective scope.

### Unrestricted-only endpoints in v1

Deny these areas to a scoped editor until they have a reliable ownership model:

- newsletter Contact, Campaign and Automation APIs: contacts can belong to multiple
  audiences, campaigns own an audience indirectly, and automations can watch several
  hosts or all hosts.

Their controllers should perform an explicit "unrestricted scope required" check at
entry. A denial is safer than returning shared data and pretending the user is
host-isolated.

### Extension contract

Because `ApiControllerRouteLoader` discovers controllers from optional bundles, add
an API extension rule to the developer documentation and an architecture test over
the controllers shipped in this monorepo. The test maintains three explicit sets of
route names and asserts that their union equals every authenticated `/api` route in
the router collection. Each action must be classified as one of:

- scope-neutral;
- host-aware, with collection and direct-item enforcement;
- unrestricted-only.

The test should fail when a new shipped API route has no classification. This keeps
future extensions from accidentally reintroducing a `ROLE_EDITOR`-only bypass.

## User administration and sync

Add a `UserAllowedHostsField` to the default `admin_user_form_fields` security
fieldset:

- show an explicit "Unrestricted" choice and the configured canonical hosts;
- map unrestricted to `null`, not `[]`;
- explain that `ROLE_ADMIN` and `ROLE_SUPER_ADMIN` ignore this setting;
- show the effective scope on the User index so an unrestricted editor is not
  silent.

Extend `pushword/flat`'s `UserSync` shapes and create/update logic with
`allowedHosts?: list<string>|null`. Missing or `null` means unrestricted; `[]` means
no host access. Validate configured hosts before changing the database and cover
both create and update paths.

No Doctrine migration is added in this repository. The upgrade note must tell sites
to run:

```bash
bin/console doctrine:schema:update --force
bin/console pw:media:usage:rebuild
```

## Tests

### Core

- `HostScope` unit tests for restricted/unrestricted users, empty list, exact host
  matching and the admin role-hierarchy bypass;
- `HostVoter` tests for a host string, a `HostInterface` entity, unsupported subjects
  and an anonymous/non-Pushword user;
- `User` mapping/getter/setter tests, including normalization and a downstream User
  subclass.

### Admin functional tests

- a one-host editor sees only its host in page/redirection indexes, totals, filters,
  menus, forms and Page associations;
- direct edit/detail/delete and every custom Page route reject an off-host Page;
- a crafted create/update POST cannot submit a forbidden destination host;
- block preview, page lock and frontend admin fragment reject an off-host Page;
- the full media library and every upload path remain available to a scoped editor;
- media update, rotate, rename and delete allow no usage or allowed-host-only usage,
  but reject mixed/off-host usage with the explicit blocking-host message;
- a denied media action does not expose off-host Page titles or slugs;
- one representative test for each adapted extension CRUD proves both list filtering
  and direct-item denial;
- global/unowned admin areas are hidden and deny direct access;
- unrestricted editor and administrator behaviour remains unchanged.

### API functional tests

- use real bearer tokens for a scoped editor, an unrestricted editor and an admin;
- Page search filters both `items` and `total`, with and without an explicit host;
- Page preview/create/GET/PUT/PATCH/DELETE and redirection routes allow the owned host
  and reject another host;
- PUT/PATCH and raw Markdown updates cannot move a Page to another host;
- each host-aware controller category has an allowed-host and forbidden-host case,
  including ID-addressed resources;
- all-host snapshot/scan/static/stats calls are rejected for a scoped user unless an
  allowed host is explicit;
- Media API list, GET and upload remain available; metadata POST/PATCH and DELETE
  allow unreferenced and allowed-host-only media, but reject mixed/off-host usage with
  `media_used_outside_scope` and sorted `blockingHosts`;
- complex newsletter APIs reject a scoped user and still allow an unrestricted
  editor;
- `/api/whoami` reports the effective scope;
- the API action-classification architecture test covers all controllers contributed
  by installed optional bundles;
- API denials are JSON `403` responses and no denied operation changes the database,
  filesystem, lock state or background queue.

### Sync and compatibility

- `UserSync` creates, updates, clears and preserves scopes according to the YAML
  contract and rejects unknown hosts;
- existing users with `allowedHosts = null` keep current admin and API behaviour;
- the media guard uses one distinct-host query, handles duplicate usage rows and is
  covered after `pw:media:usage:rebuild`;
- targeted SQLite tests plus the existing MariaDB-capable suite cover the JSON
  mapping and `IN` predicates.

Run the targeted suites while implementing, then the repository gates:

```bash
composer console cache:clear
composer stan
composer rector
composer test
```

## Documentation and upgrade notes

Document:

- the nullable `allowedHosts` semantics and `users.yaml` example;
- how admin login and bearer-token API access share the same scope;
- the scoped, neutral and unrestricted-only API areas;
- the shared-library media rule, explicit denial message and Page-tracking limit;
- the host-scope contract for extension admin/API controllers;
- the schema update command in `packages/docs/content/upgrade/next-release.md`.

Update translations for the User field and scope labels in every existing locale,
using camelCase keys in alphabetical order.

## Implementation order

1. Add the User mapping, `HostScope`, voter and core tests.
2. Add User admin/sync configuration and expose the scope through `/api/whoami`.
3. Secure Page/redirection admin CRUD and every Page-specific custom route.
4. Secure Page/redirection/raw-file API routes, including destination-host checks.
5. Add the usage-based Media mutation guard and secure every admin/API media write
   path while preserving full-library reads and uploads.
6. Adapt the remaining simple host-owned admin and API resources from the tables
   above.
7. Deny scoped access to global/ambiguous resources and add the action-classification
   test.
8. Add documentation and the upgrade note, run all quality gates, then perform a
   manual browser check with a one-host editor.

Do not expose `allowedHosts` in a release until all admin and API enforcement steps
are present. A partially wired scope would give administrators a false security
signal.

## Non-goals and deferred work

- Subtree/page-branch scoping. The Page tree is a plain `parentPage` foreign key;
  efficient collection filtering needs a path column or recursive query design.
- Per-page ACLs, per-field permissions, permission inheritance matrices and a new
  audit database.
- Adding ownership or a host column to shared Media. Media authorization derives
  from tracked Page usages instead.
- Fine-grained newsletter ownership across contacts, audiences, campaigns and
  multi-host automations.
- Restricting CLI, Messenger or trusted filesystem sync by the interactive User
  scope.
- Approval rights. Workflow approval remains a separate guard concern; host scope
  answers which site's content a user may reach, not which editorial transition
  they may execute.
