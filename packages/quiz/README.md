# Pushword Quiz

Interactive, (almost) server-less quizzes (QCM) for [Pushword](https://pushword.piedweb.com).

- **`{{ quiz('…json…') }}` Twig block** — declare a quiz inline in a page. The
  payload is a JSON *string* (not a Twig hash) on purpose: Twig can never choke
  on its structure, so a malformed quiz degrades gracefully (admins see a
  detailed error panel, visitors see nothing) instead of 500-ing the page.
- **EditorJS block** — add/remove questions and answers, flag the correct
  answer(s), attach an image or a video, write the explanation.
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
