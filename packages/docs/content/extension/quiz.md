---
title: 'Quiz: interactive QCM with end-of-quiz conversion form'
h1: Quiz
editMessage: 'Imported via pw:flat:sync from extension/quiz.md'
publishedAt: '2026-06-10 12:00'
parentPage: extensions
toc: true
filter_twig: 0
revision: af539a6039be5151f3c1bfebe3f21dc685a22880 # read only
---

Add interactive, client-side quizzes (QCM) to any page. A quiz works almost
without a server: it is declared inline in the page content and runs in the
browser. A conversion form (via [Conversation](/extension/conversation)) can be branched at
the end.

## Install

```bash
composer require pushword/quiz
```

Then run `bin/console doctrine:schema:update --force` (a tiny `quiz_result`
table stores anonymous scores for the percentile — and chosen profiles for a
personality test) and `bin/console assets:install`.

## Declare a quiz

Declare a quiz inline in a page's content. The payload is a JSON object; there
are two equivalent ways to write it.

**Recommended — the `{% quiz %}` block.** The JSON is the raw tag body, so
apostrophes and quotes need **no escaping** and the JSON stays readable/diffable:

```twig
{% quiz %}{"title":"Mountains","feedback":"immediate","cta":"newsletter","questions":[{"q":"Highest summit?","answers":[{"a":"Mont Blanc"},{"a":"Everest","correct":true},{"a":"K2"}],"explanation":"Everest is 8,849 m."}],"results":[{"min":0,"msg":"Try again"},{"min":80,"msg":"Expert!"}]}{% endquiz %}
```

**Legacy — the `quiz()` function.** Here the JSON is a single-quoted Twig
string, so every literal apostrophe must be escaped as `\'`:

```twig
{{ quiz('{"title":"Mountains", … }') }}
```

You normally never write that JSON by hand — the **EditorJS block** generates it
(add/remove questions and answers, flag the correct answer, pick or upload an
image from the media library, add a video, write the explanation). When you do
author a flat file by hand, prefer the `{% quiz %}` block and lint it with
`pw:quiz:validate` (below).

> The `{% quiz %}` body is sub-parsed as Twig, so a `{{ … }}` inside it is still
> interpolated — but the result is then JSON-decoded, so only quote-free output
> is safe (a bare URL, a number). A helper like `{{ link() }}` emits HTML with
> double quotes and would break the JSON. Keep the JSON body free of **blank
> lines** (compact or pretty-printed without empty lines): the Markdown pipeline
> splits content on blank lines, which would cut the block in two.

A missing or unknown media file no longer 500s the page: the illustration is
skipped (admins see an inline warning) and the rest of the quiz still renders.

### Question & answer fields

| Field | Where | Notes |
| --- | --- | --- |
| `q` | question | The question text. |
| `media` | question / answer | An image filename (rendered with `image()`). On a video question it doubles as the poster and is then **required**. |
| `video` | question | A video URL (rendered with `video()`), using `media` as its poster. |
| `alt` | question / answer | Media alternative text. **Required** for a video. |
| `explanation` | question | Shown once the question is answered. |
| `a` | answer | The answer text. |
| `correct` | answer | `true` for an expected answer (several allowed). Knowledge quiz only. |
| `weights` | answer | Personality test (`mode: profile`): a `{profileKey: points}` map. |
| `profile` | answer | Personality test shorthand: a profile key worth 1 point (== `weights {key: 1}`). |

### Quiz-level fields

- `mode` — `quiz` (default, scored on `correct` answers) or `profile` (a
  personality test scored on answer `weights`; see below).
- `title`, `difficulty` — header.
- `feedback` — `immediate` (reveal each answer at once, default) or `end`. Forced
  to `end` in `mode: profile` (no correct answer to reveal).
- `profiles` — personality-test outcomes `{key, title, msg?, media?, alt?}`; used
  only in `mode: profile`.
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
  as the percentile placeholder), `level`, `nextLevel`, `profile` and `share`
  (personality mode; `{p}` = the share). Set these only to force a specific wording.
- `levels` — turn the quiz into several difficulty levels (see below).

## Difficulty levels

A single quiz can offer several difficulty levels behind an accessible tab
selector. Add a `levels` array: each entry is a **complete quiz of its own**
(same shape — `difficulty`, `questions`, `results`, `feedback`, `cta`, `pass`,
`labels`), while the root keeps the shared metadata (`title`, `labels`, …).

```twig
{% quiz %}{"title":"Mountains","cta":"newsletter","pass":50,"levels":[
  {"difficulty":"Easy","questions":[ … ],"results":[ … ]},
  {"difficulty":"Intermediate","questions":[ … ]},
  {"difficulty":"Hard","questions":[ … ]}
]}{% endquiz %}
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

In the EditorJS block, pick *"Knowledge quiz with difficulty levels"* in the
**Type** selector to edit one full sub-quiz per level.

## Personality test (`mode: profile`)

Set `"mode": "profile"` to turn the same block into a personality test ("Which X
are you?"). There is no correct answer: each answer weighs one or more named
`profiles`, and the highest-tallied profile is shown as a result card.

```twig
{% quiz %}{"mode":"profile","title":"Which explorer are you?","profiles":[{"key":"sommet","title":"The Summiteer","msg":"Higher, always.","media":"peak.jpg"},{"key":"calm","title":"The Contemplative","msg":"The mountain is your refuge."}],"questions":[{"q":"A free weekend, you…","answers":[{"a":"climb a peak","weights":{"sommet":2}},{"a":"walk by a lake","profile":"calm"}]}],"cta":"newsletter"}{% endquiz %}
```

- Each answer weighs profiles with a `weights` map, or the `profile: "key"`
  shorthand (== `{ "key": 1 }`). The highest tally wins; ties break by the order
  profiles are declared.
- The validator requires at least one profile and enforces that **every weight
  references a declared profile `key`** — a typo would otherwise vote for nothing.
- `feedback` is always `end`, `levels` are not used, and **no schema.org/Quiz
  markup** is emitted (there is no accepted answer to advertise). Every outcome is
  still server-rendered (hidden) for SEO/no-JS, and the runtime reveals the winner.
- On completion the browser posts `{ quiz, result }` to `POST /quiz/result` and
  gets back `{ share }` — *"X% got the same profile"*. The knowledge-quiz
  percentile and the personality share stay separate even under one page slug.
- `labels.profile` (the *"Your profile:"* heading) and `labels.share` (use `{p}`
  for the share) override the wording.

### Editing one

Pick *"Personality test"* in the **Type** selector. The profiles are edited
**above** the questions — they are the vocabulary the answers then point at:

- Each answer shows **one chip per declared profile**. Clicking a chip adds a
  point, clicking again raises it up to ×3, once more clears it. Because the chips
  are generated from the profiles, an answer cannot weigh a profile that does not
  exist, and renaming a `key` follows through every weight on its own.
- A weight heavier than ×3 stays available from hand-written JSON, for the odd
  answer that must count far more than the others. It renders as its own chip and
  an edit leaves it untouched.
- Each profile card reports its live tally (*"6 answers · 8 pts"*) and warns when
  **no answer leads to it** — an unreachable profile is otherwise only noticed
  while playing the quiz. A question weighing nothing at all is flagged too.
- *"+ Question"* seeds one answer per profile, each designating its own.

## Styling

The quiz is styled with Tailwind utilities on the markup, so it inherits your
site's design system — the accent follows `--primary`, and the spacing and
colours come from your theme's scale. Nothing to import: the utilities are in
your stylesheet already, because `@pushword/js-helper`'s `app.css` scans bundle
templates (see [managing assets](/manage-assets) if you build your CSS from an
entry point of your own).

### Restyling one element

Every element's utilities are a `pwQuiz*Class` default. Redefine one as a **twig
global** and that element restyles, no template fork:

```yaml
# config/packages/twig.yaml
twig:
    globals:
        pwQuizQClass: 'rounded-none border-l-4 border-brand-500 bg-brand-50 p-6'
        pwQuizAClass: 'flex w-full items-center gap-3 rounded-md border px-4 py-3 text-left'
```

| variable | element |
|---|---|
| `pwQuizClass` | the `<section>` root |
| `pwQuizHeadClass` / `pwQuizTitleClass` | header, `<h2>` |
| `pwQuizMetaClass` / `pwQuizLevelMetaClass` / `pwQuizDiffClass` | meta line, per-level meta line, difficulty badge |
| `pwQuizQuestionsClass` / `pwQuizQClass` / `pwQuizQNumClass` / `pwQuizQuestionClass` | question list, card, "Question 1/5", the question text |
| `pwQuizAnswersClass` / `pwQuizAnswerItemClass` / `pwQuizAClass` | answer grid, its `<li>`, the answer button |
| `pwQuizAPrefixClass` / `pwQuizATextClass` / `pwQuizAMarkClass` | A/B/C bullet, answer text, ✓/✗ slot |
| `pwQuizExplanationClass` / `pwQuizExplanationLabelClass` | explanation box and its label |
| `pwQuizResultClass` / `pwQuizProfileTitleClass` / `pwQuizProfileMsgClass` | result box, profile title, profile message |
| `pwQuizCtaClass` / `pwQuizCtaTitleClass` | CTA block and its title |
| `pwQuizTabsClass` / `pwQuizTabClass` | difficulty tablist and its tabs |

Two constraints worth knowing:

- **It must be a global, not a render variable.** Most of the markup is built in
  `{% macro %}`, and a macro sees no render context — only globals reach inside.
- **The value is HTML-escaped.** An arbitrary variant containing `&` or `>`
  (`[&>p]:mt-0`) comes out as `[&amp;&gt;p]:mt-0` and matches nothing. Put those
  in CSS instead.
- Tailwind must *see* your override to emit it. A class named only in
  `twig.yaml` is not scanned — add `@source "../config/packages/twig.yaml";` to
  your CSS, or keep the classes in a file already scanned.

### What is still CSS

`quiz.css` ships alongside and holds only what a class attribute cannot express:

1. **runtime state** — rules like "hide the explanation until its question is
   answered" read a class `quiz.js` toggles on an *ancestor*, and Tailwind has no
   ancestor variant;
2. **markup built by `quiz.js`** — the score donut, the restart/next/share
   buttons. Tailwind scans templates, not JS, so utilities there would be purged;
3. **images rendered from `QuizRenderer.php`**, for the same reason.

The file is unlayered, so its rules outrank the template's utilities whatever the
source order — which is what lets the answered/correct/wrong states override the
base look. It keeps the `--pw-quiz-*` variables for its own colours:

```css
.pw-quiz {
  --pw-quiz-accent: var(--primary, #6d28d9);
  --pw-quiz-correct: #16a34a;
  --pw-quiz-wrong: #e11d48;
}
```

The `pw-quiz-*` class names themselves are load-bearing — `quiz.js` queries them
and those state rules key off them. Restyle by adding to them, never by replacing
them.

## SEO & accessibility

The whole quiz — questions, answers, the correct flag and the explanations —
is rendered **server-side** as a readable, schema.org `Quiz` Q&A. That is what
crawlers and no-JS visitors get. `quiz.js` then progressively enhances it into a
game. Correctness is never signalled by colour alone (✓/✗ glyphs + `aria-live`).
With difficulty levels, every level is server-rendered (panels stack without JS)
and emits its own schema.org `Quiz`. A personality test (`mode: profile`) is
server-rendered the same way but emits **no** schema.org `Quiz` — it has no
accepted answer, so that markup would be misleading.

## Percentile & leads

On completion the browser posts the score (in %) to `POST /quiz/result`, which
returns the percentile ("better than X% of participants"). This store is
anonymous (no PII). If a `cta` is set, the Conversation form is shown at the end,
pre-filled from a previously stored identity (localStorage); the lead is tagged
with the quiz via the `referring` field.

## Read the results from the API

The admin lists the attempts (**Quiz → Results**); the API answers the same
question from outside, so participation is readable without an admin session.
Both endpoints are token-authenticated and read-only — an attempt is written by
the public endpoint above and round-tripped through `quiz-result.csv`.

```
GET /api/quiz/result?host=&quiz=       # attempts, newest first, paginated
GET /api/quiz/result/stats?host=&quiz= # participation per quiz, unpaginated
```

A stats row reports `attempts` (both kinds), `knowledgeAttempts` and their
`averageScore`, and the `profiles` split of a personality test:

```json
{"quiz":"Mountains","host":"example.tld","attempts":48,"knowledgeAttempts":40,
 "averageScore":65.3,"profiles":{"sommet":5,"calm":3}}
```

## Validate from the API (AI agents)

`POST /api/quiz/validate` (token-authenticated, like the rest of the
[API](/extension/api)) validates a quiz payload against the same rules as the renderer and
the editor, returning precise violations:

```bash
curl -X POST https://example.tld/api/quiz/validate \
  -H "Authorization: Bearer <token>" -H "Content-Type: application/json" \
  -d '{"questions":[{"q":"","answers":[{"a":"x"}]}]}'
# 422 → {"error":"validation","violations":[{"path":"questions[0].q","message":"A question cannot be empty."}, …]}
```

## Validate from the CLI (no server)

`pw:quiz:validate` lints every quiz block in a flat file (both `{% quiz %}` and
`{{ quiz('…') }}` forms) — or stdin (`-`) — against the same rules, printing
`{path, message}` violations and exiting non-zero. Drop it into an edit→check
loop:

```bash
bin/console pw:quiz:validate content/my-page.md
#  ✓ Quiz #1 (line 12, {% quiz %}) — valid
cat draft.md | bin/console pw:quiz:validate -
```

It also warns (without failing) when a `cta` does not match a registered
Conversation form type.

## JSON Schema

Fetch the machine-readable JSON Schema of a quiz payload (keys, aliases, enums)
to generate a structurally-valid quiz in one shot:

```bash
bin/console pw:quiz:schema                 # prints the schema
curl https://example.tld/api/quiz/schema   # same schema (token-authenticated)
```

The Symfony Validator on the `Quiz` model stays the source of truth; the schema
mirrors its structure as an authoring aid.

## Flat sync integration

When the [Flat extension](/extension/flat) is enabled, every `pw:flat:sync` run
(or `--entity=quiz-result` alone) round-trips quiz results through
`content/<host>/quiz-result.csv`. Results are anonymous and immutable, so the
merge is trivial: import only creates rows whose `uuid` is unknown — never
updates, never deletes — and the merged union is written back on export.

The point is deployment safety: a workflow that rebuilds the database from flat
files (or does not ship `var/app.db` at all) cannot erase attempts collected in
production, and the percentile keeps its history. Results predating the `uuid`
column get one on their first export. For the same reason the admin delete
action on results is disabled: a deleted row would be recreated by the next
import.
