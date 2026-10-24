---
name: pw
description: >-
  Authoring toolkit for Pushword CMS content. Use this whenever you need to
  build, edit, or validate content features that Pushword exposes through Twig
  functions and its API, even if the user doesn't name Pushword explicitly.
  It covers quizzes (interactive multiple-choice QCM), static social-media
  carousels, and animated posts or Reels with editable motion-design scenes.
  Trigger it for requests like
  "add a quiz about X to this page", "make a QCM", "build a knowledge test",
  "validate this quiz", "turn this article into a LinkedIn carousel",
  "repurpose this page for Instagram", "create an animated reel" or "add motion
  design to this post" on a site built with Pushword. It guides schema validation,
  visual review, rendering and saving a draft in the admin.
---

# pw — Pushword content authoring

Route content requests to the playbook below. Each covers the feature's schema,
authoring constraints and verification steps.

## Commands

| Command | Description | Reference |
| --- | --- | --- |
| `quiz [topic]` | Author, embed, and validate an interactive QCM quiz | [reference/quiz.md](reference/quiz.md) |
| `carousel [page]` | Repurpose a page into a social-media carousel (SVG slides) | [reference/carousel.md](reference/carousel.md) |
| `motion [brief or page]` | Create an animated post or Reel, render it and attach it to an admin draft | [reference/motion.md](reference/motion.md) |

## Routing

1. **First word matches a command** (`quiz`, `carousel`, `motion`): read its reference file
   and follow it. Everything after the command name is the target/topic/page.
2. **No command, but the request is clearly one of them** — "add a quiz…", "make a
   QCM…" → `quiz`; "turn this into a carousel", "make a LinkedIn carousel",
   "repurpose this article for Instagram" → `carousel`; "create a reel",
   "animate this post", "motion design" → `motion`. An explicit request for
   animation takes precedence over a generic carousel or Instagram mention.
3. **No argument**: show the command table above and ask what they'd like to build.

## Working in any Pushword project

This skill runs both inside the Pushword monorepo and on a downstream site that
installed Pushword via Composer. The command references point at the right
source either way:

- **Monorepo** (a `packages/` directory with `packages/core/`): read source and
  docs under `packages/`; run console commands from `packages/dev-app/`.
- **Downstream site** (a `vendor/pushword/` directory): read docs under
  `vendor/pushword/docs/content/` and entity/model source under
  `vendor/pushword/<package>/src/`; run console commands from the app root
  (`bin/console`).

Detect which by checking for `packages/core/` vs `vendor/pushword/core/` before
you reach for a path.

## Bring the project's own rules

These references encode the *package's* rules (schemas, limits, gotchas) — never a
project's editorial voice. For any copywriting, read the project's own brand/voice
files first (e.g. `content/.rules/brands/*.md`, `.rules/ContentGuidelines.md`, or
the site's AGENTS.md) and follow them. The package publishes what only it knows;
you bring what the project knows.
