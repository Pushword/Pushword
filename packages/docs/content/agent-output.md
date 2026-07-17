---
title: 'Agent-optimized CLI output'
publishedAt: '2026-06-25 10:00'
toc: true
---

# Agent-optimized CLI output

When a console command is run by an AI coding agent (Claude Code, Cursor, Gemini
CLI, Codex, …), it drops human chrome — progress bars, colors, PID/timing/memory
lines — and prints a single compact JSON document instead. Same information, a
fraction of the tokens. Inspired by [laravel/pao](https://github.com/laravel/pao).

## Usage

Detection is automatic (it reads the same environment variables as
`laravel/agent-detector`). Override it with `--format`:

```shell
php bin/console pw:flat:lint            # auto: JSON for agents, text for humans
php bin/console pw:flat:lint --format=agent   # force JSON
php bin/console pw:flat:lint --format=text    # force human output
```

Every JSON payload starts with `tool` and `result`:

```json
{"tool":"pw:flat:lint","result":"failed","files_checked":12,"errors":1,"issues":[{"file":"about.md","error":"line 3: malformed YAML"}]}
```

`result` is `passed`/`failed` for checks, `done` for actions, `running`/`blocked`
when the command short-circuits.

### Supported commands

`pw:page-scan`, `pw:link:graph`, `pw:flat:sync`, `pw:flat:lint`, `pw:static`,
`pw:image:cache`, `pw:quiz:validate`. (`pw:media:debug --json` and `pw:quiz:schema` already emit JSON.)

## Adding it to a command

Use the shared trait `Pushword\Core\Command\AgentOutputTrait`:

```php
use Pushword\Core\Command\AgentOutputTrait;

final class MyCommand
{
    use AgentOutputTrait;

    private bool $agentMode = false;

    public function __invoke(
        OutputInterface $output,
        #[Option(description: 'Output format: auto, agent, or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);

        // ... do the work, gating every human write behind `if (! $this->agentMode)` ...

        if ($this->agentMode) {
            $this->writeAgentJson($output, ['tool' => 'pw:my-command', 'result' => 'done', /* counts */]);
        } else {
            $output->writeln('Done.'); // human summary
        }

        return self::SUCCESS;
    }
}
```

Rules:

- Gate **every** human-facing write — `$output->writeln`/`write`, `SymfonyStyle`
  calls, tables and especially `ProgressBar` — behind `if (! $this->agentMode)`.
  In agent mode stdout must contain only the final JSON line.
- Still do all the real work in agent mode; only the *output* changes.
- `isAgentFormat()` treats `agent`/`json` as forced, `text` as off, `auto` as detect.
- `writeAgentJson()` encodes with unescaped slashes/unicode.

> **Testing gotcha:** the test suite itself runs inside an agent (so detection is
> on). Any test asserting human output must pass `'--format' => 'text'` to
> `CommandTester::execute()`, and add a `'--format' => 'agent'` test for the JSON
> path.
