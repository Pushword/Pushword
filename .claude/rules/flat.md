---
paths:
  - "packages/flat/**"
---

# Flat-file sync

**`pw:flat:sync` with no arguments is bidirectional and destructive in this repo.** It
imports `packages/docs/content/*.md` into the DB, then exports the DB back over those
files — treating the dev-app DB as authoritative. The DB is a dev artifact; the content
directory is source-of-truth checked into git.

Observed: one run deleted ~30 lines of real content from `conversation.md`, churned
revision hashes across unrelated docs, regenerated `index.csv` / `media.csv`, and deleted
`pw-snippets/pro-support.md`.

**Never run it just to verify a docs change.** If you do run it, check
`git status --porcelain` immediately and revert everything that is not your edit. Export
artifacts are recognizable: reordered frontmatter, an added `mainImageFormat`, a changed
`revision:` hash. Confirm your own edit survived with `git diff --numstat`. Never
blanket-revert — other agents have uncommitted work in this tree.

## Reserved directories

`PageSync` recurses **all** `.md` under the content dir to pick up nested pages, so any
sibling feature directory would be swept in as phantom pages. `SnippetSync::DIR` is
`pw-snippets` (the `pw-` prefix also keeps a legitimate user page tree at `/snippets`
available) and is listed in `PageSync::RESERVED_DIRS`, which is honoured by all three
walkers (`collectMarkdownFiles`, `hasNewerFilesFast`, `hasNewerFiles`). A new
flat-synced entity type must add its directory there too.

New sync types register via the `pushword.flat.sync` tag (`FlatSyncInterface`), which
`FlatFileSync` iterates — do not wire them into `FlatFileSync` by hand.
