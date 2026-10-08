# Pushword Quiz

Interactive quizzes (QCM) and personality tests for [Pushword](https://pushword.piedweb.com).

- **`{% quiz %}{ …json… }{% endquiz %}` block** — declare a quiz inline in a
  page. The JSON is the raw tag body, so quotes need no escaping. The legacy
  `{{ quiz('…json…') }}` function still works (escape `'` as `\'`). A malformed
  quiz shows admins an error panel and visitors nothing; a missing media file is
  skipped.
- **EditorJS block** — knowledge quiz, difficulty levels or personality test,
  chosen from its **Type** selector.
- **Progressive enhancement** — the quiz is rendered server-side as a readable,
  schema.org-tagged Q&A (SEO, no-JS); `quiz.js` turns it into a
  one-question-at-a-time game with feedback and a score donut.
- **Difficulty levels** (`levels`) behind an accessible tab selector, and
  **personality tests** (`mode: profile`) where answers weigh named profiles.
- **Anonymous percentile** — `POST /quiz/result` stores a score (no PII) and
  returns "better than X% of participants".
- **Conversion form** — set `cta` to a
  [`pushword/conversation`](https://pushword.piedweb.com/extension/conversation)
  form type to show a lead form at the end. Optional dependency.
- **Validation for agents** — `pw:quiz:validate <file|->` lints quiz blocks in a
  flat file or stdin; `POST /api/quiz/validate` does the same over the API. Both
  return `{path, message}` violations. `pw:quiz:schema` and `GET /api/quiz/schema`
  serve the payload's JSON Schema.
- **Results API** — `GET /api/quiz/result` lists attempts;
  `GET /api/quiz/result/stats` tallies them per quiz.

## Installation

```shell
composer require pushword/quiz
php bin/console doctrine:schema:update --force
php bin/console assets:install
```

## Example

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

## Documentation

Fields, levels, personality tests, styling and APIs:
[pushword.piedweb.com/extension/quiz](https://pushword.piedweb.com/extension/quiz).

## License

MIT — see the [license](https://pushword.piedweb.com/license).
