---
title: 'Pushword for AI Agents - Edit a Live CMS with Claude Code, Codex or Cursor'
h1: 'Pushword for AI agents'
publishedAt: '2026-10-29 07:00'
toc: true
---

Pushword lets coding agents edit Markdown files or a live site through its API. It
ships no model and calls no AI service. This guide covers discovery, editing,
validation and token permissions.

## At a glance

| An agent needs to…                          | Pushword gives it                                                                                                 |
| ------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| Understand the project                      | `vendor/pushword/docs/AGENTS.md`, and the docs of the installed version in `vendor/pushword/docs/content/`        |
| Learn the content model                     | `pw:schema:dump`, or `x-pushword-page-properties` in `/api/docs`                                                  |
| Discover the API                            | `GET /api/docs`: an OpenAPI 3.1 spec generated from the controllers, readable without a token                     |
| Read command output without wasting tokens  | One compact JSON line instead of progress bars and colours ([agent output](/agent-output))                        |
| Change one sentence                         | `PATCH` with anchored find/replace, instead of resending the whole page                                           |
| Avoid overwriting a human                   | `If-Match` on every write; a stale write gets `409` and the current page                                          |
| Check its own work                          | `POST /api/page/preview`, `pw:flat:lint`, validation errors as `422`, [page scan](/extension/page-scanner)        |
| Keep SEO intact                             | Deleting a page requires a redirection; the [link graph](/extension/api#link-graph) reports orphans and depth     |
| Map a whole site                            | `pw:ai-index` exports pages and media as CSV ([AI content index](/ai-index))                                      |

## Three ways to edit

### Markdown files in Git

With the [Flat](/extension/flat) extension, each page is a Markdown file with YAML
front matter. The agent edits files, and you review the diff before importing:

```shell
php bin/console pw:flat:lock example.com --reason="Agent: rewrite pricing pages"
# the agent edits content/example.com/*.md
php bin/console pw:flat:lint example.com
git diff content/
php bin/console pw:flat:sync example.com --mode=import
php bin/console pw:flat:unlock example.com
```

This lock warns admin users that files are being edited. For a stricter lock that refuses
admin saves, and for CI pipelines, see the [Git workflow](/extension/flat-git-workflow). If
a page still changes on both sides, the most recent version wins and the other is kept as
`page~conflict-{id}.md`, with an admin notification.

### The live site, through the API

Without a local install, the agent reads the API spec, fetches a page and sends only
what changed:

```shell
curl https://example.com/api/docs | jq '.paths | keys'

curl -H "Authorization: Bearer $TOKEN" https://example.com/api/page/example.com/pricing
# → { "revision": "66a3f1b2…", "frontmatter": { … }, "body": "…" }

curl -X PATCH -H "Authorization: Bearer $TOKEN" -H "If-Match: $REV" \
     -H 'Content-Type: application/json' \
     -d '{"edits":[{"find":"From 90€","replace":"From 120€"}]}' \
     https://example.com/api/page/example.com/pricing
```

Pages are addressed by host and slug, as in URLs and flat files. One call links a page to
its translations in both directions. The [API reference](/extension/api) also covers
redirections, page scans and static regeneration; the [Media API](/media-api) covers
uploads and image metadata.

### Local files, live site

Claude Code users can download a Markdown snapshot (`/api/content/snapshot.tar.gz`)
and edit it locally. After each save, a hook runs the instance's sync client
(`/api/editor/sync.js`). It sends the file's `revision:` as `If-Match` and writes back
the server's canonical text. On conflict, it writes the server's current version so
the agent can reapply its edit.

This requires Node, with no local PHP or database. See
[raw `.md` file intake](/extension/api#raw-markdown) for setup.

## What stops a bad edit

- **Concurrent edits.** Every write must send `If-Match` with the page revision (`428`
  without it). If someone saved in between, the write is refused with `409`, and the
  response carries the current page so the agent can rebase without another request.
- **Ambiguous edits.** Each `find` in a `PATCH` must match exactly once; zero or several
  matches return `422` instead of a guess. The edits of one request apply all together or
  not at all.
- **Invalid data.** [Declared page properties](/page-properties) are validated: a wrong
  type or a broken constraint returns `422`. An unknown key is saved but reported in
  `warnings.undeclared`, which catches a mistyped property name.
- **Lost URLs.** The API refuses to delete a page without a `redirectTo`, so a removed
  page redirects instead of answering `404`.
- **Traceability.** The token's user is recorded as the page's editor, and `editMessage`
  can say why. With the [Version](/extension/version) extension, every save is kept and can
  be compared or restored.

### When an edit goes live

Writes follow the page’s publication state:

| Page state                                                                                                                               | The public sees the change…                                                 |
| ---------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------- |
| `publishedAt: draft`                                                                                                                     | once someone gives the page a publication date                              |
| `publishedAt` in the future                                                                                                              | at that date; the write response says `"scheduled": true`                   |
| `holdPublication: true`, on a site served from the [page cache](/extension/page-cache) or a [static export](/extension/static-generator) | once the hold is released and the page regenerated                          |
| any other state                                                                                                                          | at once on a dynamic or cache-mode site; at the next export on a static one |

For agent work that needs review, create new pages as drafts and put existing ones on
hold. Pushword has no approval workflow: anyone with `ROLE_EDITOR`, the agent included,
can publish a draft or release a hold.

## What an agent token can do

An API token carries its user's rights:

- **`ROLE_EDITOR` is a trusted code author.** Editorial Markdown accepts raw HTML and
  unsandboxed Twig, so an editor, human or agent, can inject scripts and call exposed Twig
  functions. If the agent also reads untrusted text, such as customer mail or scraped
  pages, a prompt injection in that text can reach your site. See the
  [security model](/security#trusted-editorial-content).
- **Tokens are coarse.** They are stored in clear text, with no scope, no expiry and no
  record of last use. Rotate one after any suspected leak.
- **Permissions are not per site or per section.** An editor can change every page on
  every host of the installation. Scoped permissions are on the [roadmap](/roadmap).
- **Give each agent its own account.** The history then shows which edits came from the
  agent, and you can rotate its token or delete the account without affecting anyone else.

## Setting up a site for agents

1. Point your project's `AGENTS.md` at `vendor/pushword/docs/AGENTS.md`, then add what only
   your project knows: hosts and locales, editorial rules, deployment.
2. Declare the [page properties](/page-properties) your templates read, so agents get typed
   fields and mistyped keys get reported.
3. Install [Version](/extension/version) before the first agent edit, not after.
4. Create an account with `ROLE_EDITOR` for the agent, and generate its token in
   **Admin > Users > API Access**.
5. After a bulk change, run a [page scan](/extension/page-scanner) to find dead links and
   redirects.

## Related

- [API reference](/extension/api) and [Media API](/media-api)
- [Flat](/extension/flat) and the [Git workflow](/extension/flat-git-workflow)
- [Agent-optimized CLI output](/agent-output)
- [AI content index](/ai-index)
- [Security model](/security)
- [Getting help](/pro), including from your coding agent
