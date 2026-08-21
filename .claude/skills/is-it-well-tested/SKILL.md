---
name: is-it-well-tested
description: Mandatory Pushword post-change test audit. Use after every task that modifies repository files, before code-simplifier and before the final response, to assess coverage, add clear missing tests, and run the relevant targeted tests.
---

# Is It Well Tested?

Audit the changes made during the current conversation and ensure their behaviour is
adequately tested. Keep the review proportional: a documentation-only or non-behavioural
change may legitimately need no automated test, but still record that conclusion.

## Scope

- Review only files changed for the current task. Use the conversation as the source of
  truth; a dirty working tree may contain unrelated work from the user or another agent.
- Start in the affected package's `src/` and `tests/` directories before widening scope.
- Do not invent tests for framework behaviour already covered upstream or for assertions
  that merely duplicate existing coverage.

## Process

1. Identify the behaviour added, changed, or fixed in the current task.
2. Find the corresponding tests. Pushword tests normally live in the same package and
   mirror the source structure.
3. Check every meaningful path introduced by the change:
   - normal behaviour;
   - simple boundaries such as null, empty, zero, and empty collections when relevant;
   - failures and exceptions owned by Pushword;
   - a regression case for every bug fix.
4. Add obvious missing tests immediately. Match neighbouring test style and fixtures.
5. For tests requiring heavy mocking, complex integration setup, or an uncertain product
   decision, explain the proposed coverage and ask before adding it.
6. Run the narrowest relevant test command from the repository root, for example
   `composer test-filter ExampleTest`. Never call `vendor/bin/phpunit` directly.
7. If a test fails unexpectedly, use the repo's `test-triage` skill before treating it as
   a regression.

## Completion report

Keep the report brief:

- what was already covered;
- what was added, or why no test was warranted;
- which test command passed;
- any non-obvious coverage awaiting user confirmation.

End with `is-it-well-tested: complete` when the audit is resolved. If user input is
required, end with `is-it-well-tested: awaiting user confirmation` instead.
