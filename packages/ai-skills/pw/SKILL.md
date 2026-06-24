---
name: pw
description: >-
  Authoring toolkit for Pushword CMS content. Use this whenever you need to
  build, edit, or validate content features that Pushword exposes through Twig
  functions and its API, even if the user doesn't name Pushword explicitly.
  Right now it covers ONE thing: creating a quiz (an interactive multiple-choice
  QCM declared inline with a `{% quiz %}` block, rendered server-side for SEO).
  Trigger it for requests like "add a quiz about X to this page", "make a QCM",
  "create an interactive quiz", "build a knowledge test", "turn this lesson into
  a quiz", or "validate this quiz" on a site built with Pushword. Authoring the
  JSON by hand drifts from a strict validator; this skill encodes the rules and a
  one-command lint so the result renders the first time.
---

# pw — Pushword content authoring

A router for authoring Pushword content. Pushword is a modular Symfony CMS; some
of its features are declared inline in a page through Twig blocks/functions (e.g.
the `{% quiz %}` block) and validated by a strict server-side schema. Writing that
by hand drifts from the rules. Each command below encodes the real schema, the
gotchas, and a verification step so the result renders the first time.

## Commands

| Command | Description | Reference |
| --- | --- | --- |
| `quiz [topic]` | Author, embed, and validate an interactive QCM quiz | [reference/quiz.md](reference/quiz.md) |

More commands will be added; for now `quiz` is the only one.

## Routing

1. **First word matches a command** (`quiz`): read its reference file and follow
   it. Everything after the command name is the target/topic.
2. **No command, but the request is clearly a Pushword quiz** ("add a quiz…",
   "make a QCM…"): treat it as `quiz` and read [reference/quiz.md](reference/quiz.md).
3. **No argument**: show the command table above and ask what they'd like to build.

## Working in any Pushword project

This skill runs both inside the Pushword monorepo and on a downstream site that
installed Pushword via Composer. The command references point at the right
source either way:

- **Monorepo** (a `packages/` directory with `packages/quiz/`): read source and
  docs under `packages/`; run console commands from `packages/skeleton/`.
- **Downstream site** (a `vendor/pushword/` directory): read docs under
  `vendor/pushword/docs/content/` and entity/model source under
  `vendor/pushword/<package>/src/`; run console commands from the app root
  (`bin/console`).

Detect which by checking for `packages/quiz/` vs `vendor/pushword/quiz/` before
you reach for a path.
