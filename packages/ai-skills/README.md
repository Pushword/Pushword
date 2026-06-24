# @pushword/ai-skills

AI authoring skills for [Pushword](https://pushword.piedweb.com). Lets an agent
build Pushword content features reliably instead of rediscovering each schema
from source. Inspired by [pbakaus/impeccable](https://github.com/pbakaus/impeccable):
one router skill, dispatching sub-commands to focused reference playbooks.

## The `pw` skill

Invoked as `/pw <command>` (and auto-triggers on natural requests like "add a
quiz to this page").

| Command | What it does |
| --- | --- |
| `quiz [topic]` | Author, embed and validate an interactive QCM quiz (`{% quiz %}` block). |

More commands will follow.

## Install

The skill lives at [`pw/`](pw/). Until the `npx` installer lands, copy it into a
harness skills directory:

```bash
# Claude Code (project-local)
cp -r packages/ai-skills/pw .claude/skills/pw
# or user-global
cp -r packages/ai-skills/pw ~/.claude/skills/pw
```

It works both inside the Pushword monorepo and on a downstream site that
installed Pushword via Composer (the skill detects `packages/quiz/` vs
`vendor/pushword/quiz/`).

## Roadmap

- `npx @pushword/ai-skills install` — auto-detect the harness (Claude Code,
  Cursor, …) and write the skill to the right location, like
  `npx impeccable skills install`.
- More commands (pages, redirections, snippets, …).
