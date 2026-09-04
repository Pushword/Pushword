---
paths:
  - ".github/workflows/**"
  - ".scripts/release"
  - "monorepo-builder.php"
  - "packages/*/.gitattributes"
---

# Release and monorepo split

`split-monorepo.yaml` mirrors each `packages/<pkg>` to its own `pushword/<pkg>` repo. It
triggers on **tag pushes only** (`on: push: tags: ["*"]`), so one release produces exactly
one race-free split run.

- **Every release must use a new version.** The split action hard-fails on
  `git tag X` when `X` already exists, with no `--force`. `.scripts/release` (what
  `composer release` and `/tagAndPush` delegate to) auto-bumps from the latest
  `1.0.0-rc*`; pass `1.0.0` explicitly to cut the stable release.
- **A matrix entry needs both a `packages/<pkg>` directory and an existing mirror repo.**
  `admin-monaco-editor` and `ai-skills` have source dirs but no mirror and are
  deliberately absent; adding them would 404 at clone.
- **A new matrix entry with tests also needs `packages/<pkg>/.gitattributes`.** It holds
  `/tests export-ignore` so the dist archive Composer installs by default ships no tests
  into a site's `vendor/`. Nothing checks this — add it with the matrix entry. Two
  reminders: it only prunes dist (`--prefer-source` still gets tests, as contributors
  want), and it lands only at the next tag, since the split runs on tag pushes.
  `pushword/new` is the deliberate exception — `type: project`, so its `tests/` and
  `phpunit.xml.dist` are the starter suite the site owner keeps.
- **Seed a brand-new mirror before its first split.** A repo created without
  `--add-readme` has no `main` ref, and the failure is misleading — `cloned an empty
  repository` → `pathspec 'main' did not match` → `src refspec main does not match any`.
  Create `main` via `gh api --method PUT repos/Pushword/<pkg>/contents/README.md`, then
  `gh run rerun <run-id> --failed`. Re-running the same failed tag is safe: the job dies
  before it tags the mirror. Afterwards, submit the mirror to Packagist once by hand so
  downstream `composer require` resolves.
- **A workflow fix only takes effect if the tagged commit contains it** — `.scripts/release`
  pushes commits before tagging the same HEAD, so it does.
- **`next-release.md` must be committed before releasing.** The note is promoted from the
  working tree, so an uncommitted draft ships into `rc<N>.md` describing code the tag does
  not contain — exactly what happens when a parallel agent is mid-change. `.scripts/release`
  aborts on a dirty draft. If it ever slips through, move the offending sections back
  into `next-release.md` and fix both the `rc<N>.md` title and its row in `upgrade.md`.
  **The guard cannot see the case that actually bites:** a peer committing
  `next-release.md` scoped sweeps in whatever another agent has drafted in that same file,
  and the draft then reads clean. `next-release.md` is the one file where a scoped commit
  is not enough isolation — before releasing, read the draft and check every section is
  yours, or that its code is in `HEAD`.

## Legacy tag conflicts

Mirrors carry duplicate `v0.0.X` / `0.0.X` tags from 2021 pointing at different commits.
Packagist normalizes both to one version, blocks the update, and emails the maintainer.
This is **benign** — the originally-published ref is kept and nothing can be swapped.

To remove such a version, delete the tag(s) in the **mirror** repo, both forms:
`git push git@github.com:Pushword/<pkg>.git :refs/tags/0.0.34 :refs/tags/v0.0.34`.

**Never push a monorepo tag deletion to fix it.** The split workflow has no deletion
guard, so touching an old `0.0.X` tag on the monorepo re-triggers the split and can
resurrect the mirror tag.
