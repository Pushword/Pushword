# @pushword/ai-skills

Author quizzes, social carousels and animated posts in
[Pushword](https://pushword.piedweb.com). The `pw` skill routes each request to a
playbook covering schema validation, visual checks and saving the result.

## The `pw` skill

Invoked as `/pw <command>` (and auto-triggers on natural requests like "add a
quiz to this page").

| Command | What it does |
| --- | --- |
| `quiz [topic]` | Author, embed and validate an interactive QCM quiz (`{% quiz %}` block). |
| `carousel [page]` | Repurpose a page into a social carousel ([repurpose](https://pushword.piedweb.com/extension/repurpose)). |
| `motion [brief or page]` | Create an animated post or Reel, inspect the frames, render and attach the video to an admin draft. |

## Install

Copy [`pw/`](pw/) into your agent's skills directory. From the monorepo root:

```bash
# Claude Code (project-local)
cp -r packages/ai-skills/pw .claude/skills/pw
# or user-global
cp -r packages/ai-skills/pw ~/.claude/skills/pw
```

The installed skill works in the monorepo and on Composer sites. It detects
`packages/core/` or `vendor/pushword/core/` and uses the corresponding tools and
docs. The `motion` playbook covers the bundled NORD. example, rendering,
uploading and attaching a video to an admin draft.
