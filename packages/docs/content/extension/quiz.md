---
title: 'Quiz: interactive QCM with end-of-quiz conversion form'
h1: Quiz
publishedAt: '2026-06-10 12:00'
parentPage: extensions
toc: true
filter_twig: 0
---

Add interactive, client-side quizzes (QCM) to any page. A quiz works almost
without a server: it is declared inline in the page content and runs in the
browser. A conversion form (via [Conversation](conversation)) can be branched at
the end.

## Install

```bash
composer require pushword/quiz
```

Then run `bin/console doctrine:schema:update --force` (a tiny `quiz_result`
table stores anonymous scores for the percentile) and `bin/console assets:install`.

## Declare a quiz

Use the `quiz()` Twig function in a page's content. The argument is a JSON
**string** describing the quiz:

```twig
{{ quiz('{"title":"Mountains","feedback":"immediate","cta":"newsletter","questions":[{"q":"Highest summit?","answers":[{"a":"Mont Blanc"},{"a":"Everest","correct":true},{"a":"K2"}],"explanation":"Everest is 8,849 m."}],"results":[{"min":0,"msg":"Try again"},{"min":80,"msg":"Expert!"}]}') }}
```

You normally never write that JSON by hand — the **EditorJS block** generates it
(add/remove questions and answers, flag the correct answer, attach an image or a
video, write the explanation).

### Question & answer fields

| Field | Where | Notes |
| --- | --- | --- |
| `q` | question | The question text. |
| `media` | question / answer | An image filename (rendered with `image()`). |
| `video`, `poster` | question | A video URL + poster (rendered with `video()`). |
| `alt` | question / answer | Media alternative text. **Required** for a video. |
| `explanation` | question | Shown once the question is answered. |
| `a` | answer | The answer text. |
| `correct` | answer | `true` for an expected answer (several allowed). |

### Quiz-level fields

- `title`, `difficulty` — header.
- `feedback` — `immediate` (reveal each answer at once, default) or `end`.
- `results` — score bands `{min, msg}`; the highest matched `min` wins.
- `cta` — a Conversation form type shown at the end (skipped if Conversation is
  not installed).

## SEO & accessibility

The whole quiz — questions, answers, the correct flag and the explanations —
is rendered **server-side** as a readable, schema.org `Quiz` Q&A. That is what
crawlers and no-JS visitors get. `quiz.js` then progressively enhances it into a
game. Correctness is never signalled by colour alone (✓/✗ glyphs + `aria-live`).

## Percentile & leads

On completion the browser posts the score (in %) to `POST /quiz/result`, which
returns the percentile ("better than X% of participants"). This store is
anonymous (no PII). If a `cta` is set, the Conversation form is shown at the end,
pre-filled from a previously stored identity (localStorage); the lead is tagged
with the quiz via the `referring` field.

## Validate from the API (AI agents)

`POST /api/quiz/validate` (token-authenticated, like the rest of the
[API](api)) validates a quiz payload against the same rules as the renderer and
the editor, returning precise violations:

```bash
curl -X POST https://example.tld/api/quiz/validate \
  -H "Authorization: Bearer <token>" -H "Content-Type: application/json" \
  -d '{"questions":[{"q":"","answers":[{"a":"x"}]}]}'
# 422 → {"error":"validation","violations":[{"path":"questions[0].q","message":"A question cannot be empty."}, …]}
```
