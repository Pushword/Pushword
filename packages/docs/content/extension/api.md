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
  `locale`, `tags`, `parentPage`, `translations`, `mainImage`, `customProperties`, …) and
  `body` (the `mainContent` Markdown), mirroring the flat-file format.
- **Optimistic concurrency.** Each page carries an opaque `revision` (also sent as the
  `ETag` header). Writes must send `If-Match: <revision>`; a stale value yields `409`.
- **Token-efficient responses.** Writes return a minimal body by default; reads return the
  full page. See [Write responses](#write-responses).

{id=discovery}
## Discovery — `GET /api/docs`

Public (no token needed). Returns the OpenAPI 3.1 spec covering every endpoint and schema,
including those contributed by optional bundles (conversation, snippet…).

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
| `DELETE` | `/api/page/{host}/{slug}`      | Delete — a `redirectTo` is required (see below)      |
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

{id=translations}
### Link translations (hreflang)

Locales are separate hosts, so a page's translations are sent as the `translations`
frontmatter key — a list of `host/slug` refs (a bare `slug` for a sibling on the same
host). It works on `POST`, `PUT` and `PATCH` like any other frontmatter key:

```bash
curl -X PATCH -H "Authorization: Bearer $TOKEN" -H "If-Match: $REV" \
     -H 'Content-Type: application/json' \
     -d '{"frontmatter":{"translations":["us.example.com/pricing"]}}' \
     https://example.com/api/page/example.com/tarifs
```

- **One call pairs both directions.** The sibling gains the reverse link, so you never
  have to send the mirror request.
- **The list is authoritative.** Refs you drop are unlinked; `"translations": []` clears
  the group. Omit the key to leave it untouched.
- **An unknown ref is a `422`** (`invalid_frontmatter`, `key: translations`), and the
  existing group is left as it was — create the target page before linking it.

{id=delete}
### Delete a page (`DELETE`) — a redirection is mandatory

A deleted URL that answers `404` loses its inbound links and its search ranking, so the API
refuses to delete without knowing where the slug goes next: `redirectTo` is **required**
(`400` otherwise), in the JSON body or as a query parameter.

```bash
curl -X DELETE -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
     -d '{"redirectTo":"/pricing"}' \
     https://example.com/api/page/example.com/old-pricing
```

- `redirectTo` — an internal path (`/pricing`) or an absolute URL. A bare relative string
  (`pricing`) or the deleted page's own slug is a `400`.
- `code` — optional, defaults to `301`; must be a `3xx` status.

Returns `204`. Where the redirection lands depends on the target:

- **Internal target that exists** (hard delete) — the page row is really deleted and the
  destination page records the old path in its `redirectFrom`, like a slug rename does.
- **External, unknown, or chained target** (and every soft delete) — the page keeps its
  slug but its body is replaced by the redirection (`Location: …`), the same shape the
  [redirection endpoints](#redirections) manage. Nothing else can serve a `301` for a slug
  a row still owns.

With `delete_strategy: soft` the page is also unpublished (kept out of listings and
sitemaps). Soft delete no longer preserves the body — use the
[Version](/extension/version) extension if you need the old content back.

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

To save tokens, `POST` / `PUT` / `PATCH` return only what the client cannot already
derive. The `host` and `slug` are in the request URL, so they aren't echoed — just the new
`revision` (for the next `If-Match`) and the server-set `updatedAt`:

```json
{ "revision": "9f1c…", "updatedAt": "2026-06-01T10:12:00+00:00" }
```

Create is the exception: it also returns the `slug`, which isn't in its URL and may have
been normalized (e.g. `"Qui Sommes-Nous"` → `"qui-sommes-nous"`).

```json
{ "slug": "qui-sommes-nous", "revision": "9f1c…", "updatedAt": "2026-06-01T10:12:00+00:00" }
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

{id=hold-publication}
## Holding publication

A `PUT`/`PATCH` saves to the database immediately. In static (cache) mode the public keeps
seeing the previously generated static file until you release the hold and regenerate. Set
`holdPublication: true` in the write payload to keep the live static page in place while you
stage edits; clear it (and regenerate) to publish. Use the [Version](/extension/version)
extension to see diffs between revisions.

{id=redirections}
## Redirections

`GET/POST/PUT/DELETE /api/redirection/{host}/{slug}` and `GET /api/redirection` manage
redirections, addressed by `(host, slug)` like pages. Shape:
`{ host, slug, redirectTo, code, revision, updatedAt }`. Same `If-Match` concurrency rules.

{id=page-scan}
## Page scan (dead links, 404, 301)

Mirrors the admin [Page scanner](/extension/page-scanner). A scan can run for minutes, so the
API never blocks: `POST` dispatches a **background** scan, `GET` polls its progress and
findings.

| Method | Route                       | Action                                              |
|--------|-----------------------------|-----------------------------------------------------|
| `POST` | `/api/page-scan`            | Start a background scan (or return a fresh result)  |
| `GET`  | `/api/page-scan`            | Poll status, live output, and the cached findings   |

Both take an optional `?host=` (a configured host; omit to scan every site at once). An
unknown host yields `400`.

```bash
# Trigger — returns 202 with a statusUrl to poll (or 200 if a fresh result already exists)
curl -X POST -H "Authorization: Bearer $TOKEN" \
     "https://example.com/api/page-scan?host=example.com"

# Poll until status is "completed"
curl -H "Authorization: Bearer $TOKEN" \
     "https://example.com/api/page-scan?host=example.com"
```

```json
{
  "host": "example.com",
  "status": "completed",
  "running": false,
  "lastScannedAt": "2026-06-02T09:14:00+00:00",
  "errorCount": 2,
  "errors": [
    { "host": "example.com", "slug": "about", "message": "404 /team" }
  ]
}
```

- `status` — `idle` (never scanned), `running`, `completed`, or `error`. While `running`
  (or on `error`) the body also carries the live console `output`.
- `POST` returns `202 Accepted` when a scan is running (just started, or already in
  progress), or `200` with the cached result when it is still within the configured
  `min_interval_between_scan` window — pass `?force=1` to bypass that and rescan.
- `errors[].message` is plain text (admin HTML stripped), grouped per page in the admin but
  flattened to a list here.

{id=link-graph}
## Link graph (inbound links, depth, orphans)

The scan also records which pages link to which. This endpoint reports that graph — see
[Page scanner](/extension/page-scanner#link-graph) for what counts as a link and why.

| Method | Route             | Action                                                   |
|--------|-------------------|----------------------------------------------------------|
| `GET`  | `/api/link-graph` | Inbound/outbound links, inbound sources, depth, orphans   |

Read-only: the graph comes from the page scan, so there is nothing separate to start. When a
host was never scanned the endpoint answers `404` with a `triggerUrl` pointing at
`POST /api/page-scan`. `?host=` is **required** — a graph is scoped to one site — and
`?page=` optionally narrows it to one slug.

```bash
curl -H "Authorization: Bearer $TOKEN" \
     "https://example.com/api/link-graph?host=example.com&page=about"
```

```json
{
  "host": "example.com",
  "status": "completed",
  "generatedAt": "2026-06-02T09:14:00+00:00",
  "pageCount": 251,
  "edgeCount": 1203,
  "orphanCount": 3,
  "homepageScanned": true,
  "pages": [
    {
      "host": "example.com",
      "slug": "about",
      "inboundCount": 2,
      "outboundCount": 11,
      "inbound": ["example.com/homepage", "example.com/team"],
      "depth": 1
    }
  ]
}
```

- `generatedAt` — when the scan behind this graph ran. A page's `inboundCount` changes when
  **other** pages are edited, so a stale graph misleads without anything on the page itself
  having moved. `POST /api/page-scan` refreshes it.
- `depth` — clicks from the homepage; `null` when unreachable without a pager.
- `homepageScanned` — false when the host has no scanned homepage: `depth` is then unknown
  rather than infinite.
- `orphanCount` counts pages with at most one inbound link. A homepage is never an orphan.

{id=errors}
## Error reference

| Status | Meaning                                                              |
|--------|---------------------------------------------------------------------|
| `400`  | Malformed request (e.g. `PATCH` with neither `edits` nor `frontmatter`, `DELETE` without `redirectTo`) |
| `401`  | Missing or invalid token                                            |
| `403`  | Token valid but user lacks `ROLE_EDITOR`                            |
| `404`  | Resource not found                                                  |
| `409`  | Slug already exists (create) or revision mismatch (`revision_mismatch`) |
| `422`  | `validation` (constraint violations) or `patch_failed` (find not_found/ambiguous) |
| `428`  | Missing `If-Match` header on a write                                |
