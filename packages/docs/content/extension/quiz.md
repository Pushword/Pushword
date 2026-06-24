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
(add/remove questions and answers, flag the correct answer, pick or upload an
image from the media library, add a video, write the explanation).

### Question & answer fields

| Field | Where | Notes |
| --- | --- | --- |
| `q` | question | The question text. |
| `media` | question / answer | An image filename (rendered with `image()`). On a video question it doubles as the poster and is then **required**. |
| `video` | question | A video URL (rendered with `video()`), using `media` as its poster. |
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
- `ctaTitle` — a call-to-action heading shown above that end form (for example
  *"Receive the next quizzes in your mailbox"*).
- `numbering` — prefix each answer so people can refer to one out loud: `"A"`
  (A, B, C…), `"a"` (a, b, c…), `"1"` (1, 2, 3…), or `""` for none (default).
- `pass` — the score (in %) at or above which a difficulty level counts as
  passed and offers the next one (default `50`). Only meaningful with `levels`.
- `labels` — overrides for the UI words, which otherwise default to the site
  locale: `question`, `questions`, `explanation`, `score`, `better` (use `{p}`
  as the percentile placeholder), `level` and `nextLevel`. Set these only to
  force a specific wording.
- `levels` — turn the quiz into several difficulty levels (see below).

## Difficulty levels

A single quiz can offer several difficulty levels behind an accessible tab
selector. Add a `levels` array: each entry is a **complete quiz of its own**
(same shape — `difficulty`, `questions`, `results`, `feedback`, `cta`, `pass`,
`labels`), while the root keeps the shared metadata (`title`, `labels`, …).

```twig
{{ quiz('{"title":"Mountains","cta":"newsletter","pass":50,"levels":[
  {"difficulty":"Easy","questions":[ … ],"results":[ … ]},
  {"difficulty":"Intermediate","questions":[ … ]},
  {"difficulty":"Hard","questions":[ … ]}
]}') }}
```

- The tab label is `label ?? difficulty`.
- A level inherits the root's `labels` (merged), `feedback`, `numbering`, `cta`,
  `ctaTitle`, `results` and `pass` when it does not set its own.
- Tabs are always freely clickable (WAI-ARIA tabs: arrow keys, `Home`/`End`,
  roving focus). When a level is **passed** (score ≥ its `pass`), a *"Next level →"*
  button appears and jumps to the following tab.
- Each level keeps its **own** percentile and lead attribution (the score store
  key and the Conversation `referring` are discriminated per level), so an Easy
  score never dilutes a Hard one.
- A quiz **without** `levels` renders exactly as before — zero change.

In the EditorJS block, tick *"Multiple difficulty levels"* to switch a quiz to
levels mode and edit one full sub-quiz per level.

## SEO & accessibility

The whole quiz — questions, answers, the correct flag and the explanations —
is rendered **server-side** as a readable, schema.org `Quiz` Q&A. That is what
crawlers and no-JS visitors get. `quiz.js` then progressively enhances it into a
game. Correctness is never signalled by colour alone (✓/✗ glyphs + `aria-live`).
With difficulty levels, every level is server-rendered (panels stack without JS)
and emits its own schema.org `Quiz`.

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
