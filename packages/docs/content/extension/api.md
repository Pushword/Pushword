---
title: 'REST API for Pushword CMS'
h1: API
publishedAt: '2026-06-01 10:00'
toc: true
---

A token-authenticated **REST API** mirroring the Pushword admin: list, create, edit and
delete Pages and redirections (plus Media — see [Media Metadata API](/media-api)) without
the admin UI. Built for scripted workflows, external tooling and **LLM agents** editing
content programmatically.

The API is **self-describing**: `GET /api/docs` returns an always-up-to-date OpenAPI 3.1
document generated from the controllers themselves, so an agent can discover every route
and field before authenticating.

{id=authentication}
## Authentication

Every request (except `/api/docs`) requires a Bearer token in the `Authorization` header,
matched against the `apiToken` field of the `User` entity. The user must hold `ROLE_EDITOR`.

```bash
TOKEN=$(bin/console pw:user:token robin@example.tld)
curl -H "Authorization: Bearer $TOKEN" https://example.com/api/page/search
```

- `401` — token missing or unknown.
- `403` — token valid but the user lacks `ROLE_EDITOR`.

{id=conventions}
## Conventions

- **Addressing by natural key.** Pages and redirections are addressed by `(host, slug)`,
  not an internal `id` — the same identity used by flat files and public routes. The `host`
  field also drives multi-locale (one host per locale).
- **Frontmatter + body.** A page is split into `frontmatter` (all metadata: `h1`, `title`,
  `locale`, `tags`, `parentPage`, `mainImage`, `customProperties`, …) and `body` (the
  `mainContent` Markdown), mirroring the flat-file format.
- **Optimistic concurrency.** Each page carries an opaque `revision` (also sent as the
  `ETag` header). Writes must send `If-Match: <revision>`; a stale value yields `409`.
- **Token-efficient responses.** Writes return a minimal body by default; reads return the
  full page. See [Write responses](#write-responses).

{id=discovery}
## Discovery — `GET /api/docs`

Public (no token needed). Returns the OpenAPI 3.1 spec covering every endpoint and schema,
including those contributed by optional bundles (page-workflow, conversation, snippet…).

```bash
curl https://example.com/api/docs | jq '.paths | keys'
```

{id=pages}
## Pages

| Method   | Route                          | Action                                              |
|----------|--------------------------------|-----------------------------------------------------|
| `GET`    | `/api/page/search`             | Filterable, paginated light listing                 |
| `GET`    | `/api/page/{host}/{slug}`      | Full page (frontmatter + body + revision)           |
| `POST`   | `/api/page/{host}`             | Create (slug comes from the frontmatter)            |
| `PUT`    | `/api/page/{host}/{slug}`      | Replace body/frontmatter (`If-Match` required)      |
| `PATCH`  | `/api/page/{host}/{slug}`      | Partial edit — find/replace on the body (`If-Match`)|
| `DELETE` | `/api/page/{host}/{slug}`      | Soft-delete (unpublish) or hard-delete via config   |
| `POST`   | `/api/page/preview`            | Render Markdown to HTML without persisting          |

### Read a page

```bash
curl -H "Authorization: Bearer $TOKEN" \
     https://example.com/api/page/example.com/about
```

```json
{
  "host": "example.com",
  "slug": "about",
  "revision": "66a3f1b2c8d4e7d3a",
  "frontmatter": { "h1": "About us", "locale": "en", "tags": ["company"] },
  "body": "# About us\n\nWe are…",
  "updatedBy": "robin@example.tld",
  "updatedAt": "2026-05-28T11:47:16+00:00"
}
```

The `revision` is also returned as the `ETag` response header — keep it to send `If-Match`
on the next write.

### Create a page

```bash
curl -X POST -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
     -d '{"frontmatter":{"slug":"pricing","h1":"Pricing","locale":"en"},"body":"# Pricing"}' \
     https://example.com/api/page/example.com
```

Returns `201` with the minimal write response (see below). `409` if the slug already exists.

### Replace a page (`PUT`)

Sends the whole `frontmatter` and/or `body`. Requires a matching `If-Match`.

```bash
curl -X PUT -H "Authorization: Bearer $TOKEN" -H "If-Match: $REV" \
     -H 'Content-Type: application/json' \
     -d '{"frontmatter":{"h1":"New title"},"body":"# New title\n\nUpdated."}' \
     https://example.com/api/page/example.com/pricing
```

{id=patch}
### Partial edit (`PATCH`) — token-efficient

`PUT` requires re-sending the entire Markdown body to change one line. `PATCH` instead
applies **anchored find/replace** edits to the body, and/or a selective `frontmatter`
merge — ideal for LLM agents, which only emit the changed slice.

```bash
curl -X PATCH -H "Authorization: Bearer $TOKEN" -H "If-Match: $REV" \
     -H 'Content-Type: application/json' \
     -d '{
       "edits": [
         { "find": "## Premium\nFrom 90€", "replace": "## Premium\nFrom 120€" }
       ],
       "frontmatter": { "title": "Updated pricing" }
     }' \
     https://example.com/api/page/example.com/pricing
```

Request body (at least one of `edits` or `frontmatter` is required, else `400`):

- `edits` — a list of `{ find, replace, replaceAll? }`, applied **sequentially** to the
  body in memory (an edit may only become matchable after the previous one ran).
- `frontmatter` — a partial metadata merge; only the keys you send are changed.

**The match-once guarantee.** Each `find` must match **exactly once**. If it matches zero
times or more than once, the API returns `422` instead of guessing — ambiguity is a loud
error, never a silent wrong edit. Set `replaceAll: true` to replace every occurrence
intentionally (still `422` if it matches nothing). Omitting `replace` deletes the matched
text.

**Atomicity.** Edits apply all-or-nothing: if any edit fails, the page is left untouched
and nothing is persisted.

```json
// 422 — a find was ambiguous
{ "error": "patch_failed", "edit": { "index": 0, "reason": "ambiguous", "matches": 2 } }
```

`reason` is `not_found` (zero matches) or `ambiguous` (more than one); `index` is the
position of the failing edit in your `edits` list.

### Search

```bash
curl -H "Authorization: Bearer $TOKEN" \
     "https://example.com/api/page/search?host=example.com&q=pricing&tag[]=company&page=1&per_page=25"
```

Filters: `host`, `q` (matches h1/slug/title/content), `locale`, `parentPage`, `tag[]`,
plus `page` / `per_page`. Returns light items `{ host, slug, h1, locale, updatedAt }` with
`{ items, total, page, per_page }`.

{id=write-responses}
## Write responses (minimal by default)

To save tokens, `POST` / `PUT` / `PATCH` return only what the client needs to track the
new state — it already has the content it just sent:

```json
{ "host": "example.com", "slug": "pricing", "revision": "9f1c…", "updatedAt": "2026-06-01T10:12:00+00:00" }
```

The new `revision` is also in the `ETag` header. Add `?return=full` to get the complete
page payload (same shape as `GET`) instead.

`GET` always returns the full page, and a `409` conflict always includes the fresh
`current` page so a client can rebase without a second request.

{id=concurrency}
## Optimistic concurrency

Writes require `If-Match: <revision>`:

- `428 Precondition Required` — the `If-Match` header is missing.
- `409 Conflict` — the revision is stale. The response carries `your_revision`,
  `current_revision`, and the full `current` page to rebase against:

```json
{
  "error": "revision_mismatch",
  "your_revision": "66a3f1b2c8d4e7d3a",
  "current_revision": "71b9d2e4a0c6f8b1c",
  "current": { "host": "…", "slug": "…", "revision": "71b9…", "frontmatter": {}, "body": "…" }
}
```

{id=workflow}
## Editorial workflow integration

When the [Page Workflow](/extension/page-workflow) bundle is installed and the target page
is already **published**, a `PUT`/`PATCH` does not mutate the page directly. Instead it
records a pending modification and returns `202 Accepted`:

```json
{ "pendingModification": { "id": 42, "state": "in_review" }, "page": { "…current page…" } }
```

The page stays unchanged until the modification is applied through the workflow. Draft
pages (and installs without the bundle) are written directly.

{id=redirections}
## Redirections

`GET/POST/PUT/DELETE /api/redirection/{host}/{slug}` and `GET /api/redirection` manage
redirections, addressed by `(host, slug)` like pages. Shape:
`{ host, slug, redirectTo, code, revision, updatedAt }`. Same `If-Match` concurrency rules.

{id=errors}
## Error reference

| Status | Meaning                                                              |
|--------|---------------------------------------------------------------------|
| `400`  | Malformed request (e.g. `PATCH` with neither `edits` nor `frontmatter`) |
| `401`  | Missing or invalid token                                            |
| `403`  | Token valid but user lacks `ROLE_EDITOR`                            |
| `404`  | Resource not found                                                  |
| `409`  | Slug already exists (create) or revision mismatch (`revision_mismatch`) |
| `422`  | `validation` (constraint violations) or `patch_failed` (find not_found/ambiguous) |
| `428`  | Missing `If-Match` header on a write                                |
