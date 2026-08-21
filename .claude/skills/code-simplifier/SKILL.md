---
name: code-simplifier
description: Mandatory final simplification pass for Pushword changes. Use after is-it-well-tested on every task that modifies repository files and before the final response, refining only the current task's code without changing behaviour.
---

# Code Simplifier

Refine the code changed during the current conversation for clarity and consistency while
preserving exact behaviour.

## Scope

- Review only files changed for the current task. A dirty working tree may contain
  unrelated work from the user or another agent; leave it untouched.
- Include source and test code added or edited during the task.
- If the task changed no code, report that there was nothing to simplify. Do not create
  unrelated cleanup work to make the pass non-empty.
- Remove orphans created by the current task. Only flag pre-existing dead code.

## Pushword standards

- Match surrounding code and keep PHP compatible with PSR-12 and PSR-4.
- Use explicit parameter, return, and property types where the surrounding API permits.
- Keep imports and namespaces organized, and group a property's getter and setter.
- Prefer precise names, specific exception types, early returns, and straightforward
  control flow.
- Use constructor property promotion and `readonly` only when they genuinely clarify
  ownership.
- Prefer `match` when it makes returned alternatives clearer.
- Avoid nested ternaries, dense expressions, speculative abstractions, and deprecated
  PHP, Symfony, or Pushword features.

## Preserve clarity

Simplification means reducing accidental complexity, not minimizing line count. Stop when
a rewrite would combine concerns, weaken an existing abstraction, obscure intent, or
change observable behaviour. Remove comments only when they restate obvious code; retain
comments that explain constraints or non-obvious decisions.

## Process

1. Identify the exact hunks changed for the current task.
2. Remove redundant nesting, duplication, indirection, and stale comments introduced by
   those hunks.
3. Improve names and structure where the result is plainly easier to read.
4. Review the final diff to confirm that behaviour and scope are unchanged.
5. Report only significant refinements. If no refinement is beneficial, say so.

End with `code-simplifier: complete` when the pass is finished.
