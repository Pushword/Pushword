# Pushword Quiz

Interactive, (almost) server-less quizzes (QCM) for [Pushword](https://pushword.piedweb.com).

- **`{% quiz %}{ …json… }{% endquiz %}` block** (recommended) — declare a quiz
  inline in a page. The JSON is the **raw tag body**, so apostrophes/quotes need
  no escaping and the payload stays readable/diffable. The legacy
  `{{ quiz('…json…') }}` function still works (there the JSON is a single-quoted
  Twig string, so literal `'` must be escaped as `\'`). A malformed quiz degrades
  gracefully (admins see a detailed error panel, visitors see nothing) instead of
  500-ing the page — and a missing media file is skipped, not fatal.
- **EditorJS block** — add/remove questions and answers, flag the correct
  answer(s), attach an image or a video, write the explanation.
- **`pw:quiz:validate <file|->`** — lint quiz blocks in a flat file (or stdin)
  with precise `{path, message}` violations and a non-zero exit, for an
  edit→check loop without a server. `pw:quiz:schema` prints the payload's JSON
  Schema.
- **Progressive enhancement** — the full quiz is rendered server-side as a
  readable, schema.org-tagged Q&A (great for SEO and no-JS); `quiz.js` turns it
  into a one-question-at-a-time game with immediate feedback and a score donut.
- **Anonymous percentile** — `POST /quiz/result` stores a score (no PII) and
  returns "better than X% of participants".
- **Conversion form** — set `cta` to a [`pushword/conversation`](https://pushword.piedweb.com/extension/conversation)
  form type to show a lead form at the end (pre-filled from a localStorage
  identity). Optional soft dependency.
- **`POST /api/quiz/validate`** — token-authenticated endpoint (for AI agents)
  that validates a quiz payload and returns precise `{path, message}` violations.
  **`GET /api/quiz/schema`** serves the payload's JSON Schema.

## Quiz JSON shape

```json
{
  "title": "Montagnes du monde",
  "difficulty": "Facile",
  "feedback": "immediate",
  "cta": "newsletter",
  "questions": [
    {
      "q": "Quel est le plus haut sommet du monde ?",
      "media": "everest.jpg",
      "alt": "Le mont Everest",
      "answers": [
        { "a": "Mont Blanc" },
        { "a": "Everest", "correct": true },
        { "a": "K2" }
      ],
      "explanation": "Le mont Everest culmine à 8 849 mètres."
    }
  ],
  "results": [
    { "min": 0, "msg": "À retravailler !" },
    { "min": 80, "msg": "Bravo !" }
  ]
}
```

A question may carry an image (`media`) **or** a video (`video`); a video reuses
the `media` image as its poster, so both `media` and an `alt` (accessibility)
are required for one. The single source of truth for validity is the `Quiz`
model + Symfony Validator, shared by the renderer, the editor lint and the API
endpoint.

## Difficulty levels

Add a `levels` array to offer several difficulty levels behind an accessible tab
selector. Each entry is a full quiz (`difficulty`, `questions`, `results`, …);
the root keeps the shared metadata (`title`, `labels`). The tab label is
`label ?? difficulty`, and a level inherits the root's `labels`/`feedback`/
`cta`/`pass` when it omits its own.

```json
{
  "title": "Mountains",
  "cta": "newsletter",
  "pass": 50,
  "levels": [
    { "difficulty": "Easy", "questions": [ … ], "results": [ … ] },
    { "difficulty": "Intermediate", "questions": [ … ] },
    { "difficulty": "Hard", "questions": [ … ] }
  ]
}
```

Tabs are freely clickable (WAI-ARIA: arrow keys, roving focus). Passing a level
(score ≥ its `pass`, default 50) reveals a *"Next level →"* button to the next
tab. Each level keeps its **own** percentile and lead attribution. A quiz
without `levels` renders exactly as before. In the EditorJS block, tick
*"Multiple difficulty levels"* to edit one sub-quiz per level.

## Personality test (`mode: profile`)

Set `"mode": "profile"` to turn the same block into a personality test ("Which X
are you?"). There is no correct answer: each answer carries `weights` toward one
or more named `profiles`, and the highest-tallied profile is shown as a result
card (title + description + image). Backward compatible — a quiz without `mode`
behaves exactly as before.

```json
{
  "mode": "profile",
  "title": "Which explorer are you?",
  "profiles": [
    { "key": "sommet", "title": "The Summiteer", "msg": "Higher, always.", "media": "peak.jpg" },
    { "key": "calm",   "title": "The Contemplative", "msg": "The mountain is your refuge." }
  ],
  "questions": [
    {
      "q": "A free weekend, you…",
      "answers": [
        { "a": "climb a peak",    "weights": { "sommet": 2 } },
        { "a": "walk by a lake",  "profile": "calm" }
      ]
    }
  ],
  "cta": "newsletter"
}
```

An answer weighs profiles with a `weights` map, or the `profile: "key"` shorthand
(== `{ "key": 1 }`). The validator enforces at least one profile and that every
weight references a declared profile `key` (a typo would otherwise vote for
nothing). `feedback` is always `end` (no correct answer to reveal), `levels` are
not used, and no schema.org/Quiz markup is emitted (there is no accepted answer).
`POST /quiz/result` accepts `{ quiz, result }` and returns `{ share }` — "X% got
the same profile". Its knowledge-quiz and personality tallies stay separate even
under one page slug. In the EditorJS block, tick *"Personality test"* to swap the
correct-answer flag for per-answer profile weights and edit the profile cards.
