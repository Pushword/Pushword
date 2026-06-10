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

A question may carry an image (`media`) **or** a video (`video` + optional
`poster`); a video requires an `alt` (accessibility). The single source of
truth for validity is the `Quiz` model + Symfony Validator, shared by the
renderer, the editor lint and the API endpoint.
