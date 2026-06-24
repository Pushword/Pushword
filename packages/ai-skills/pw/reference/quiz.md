# quiz — author a Pushword quiz

Create an interactive multiple-choice quiz and embed it in a page. It renders
server-side (good for SEO) and `quiz.js` upgrades it into a game in the browser.

**The full field schema lives in the project docs — read it first:**

- Monorepo: `packages/docs/content/extension/quiz.md`
- Downstream site: `vendor/pushword/docs/content/extension/quiz.md`

This playbook only covers the three things that trip you up when authoring by
hand: embedding, the quote footgun, and validating. The minimum valid shape:
at least one question, each with a non-empty `q`, **≥2 answers**, and **≥1**
flagged `"correct": true`.

## 1. Embed (flat-file site)

A quiz is one Twig call placed in the page's Markdown content. On a `pushword/flat`
site that means editing the target page's `.md` file and pasting the call where
the quiz should appear in the body:

```twig
{{ quiz('…a JSON string describing the quiz…') }}
```

- Keep the whole call on **one line**.
- Do **not** set `filter_twig: 0` in the page front-matter — that disables Twig
  for the page and the quiz would print as literal text.

## 2. The quote footgun (the #1 reason a quiz fails to render)

The JSON lives inside a **single-quoted** Twig string. So in every text value:

- Escape each apostrophe `'` as `\'` — a bare `'` ends the Twig string early and
  breaks the page. Example: `"Qu\'est-ce que…"`.
- Escape each double quote `"` as `\"` (standard JSON; it passes through the Twig
  literal unchanged).

Re-scan every text field for `'` before you finish — this bites hardest in
French or any apostrophe-heavy language.

## 3. Validate before declaring done

If `pushword/api` is installed, post the **raw** JSON (apostrophes literal, no
Twig escaping) to `/api/quiz/validate`:

```bash
# mint a token — monorepo: from packages/skeleton; downstream: from the app root
php bin/console pw:user:token admin@example.tld

curl -X POST https://<site>/api/quiz/validate \
  -H "Authorization: Bearer <token>" -H "Content-Type: application/json" \
  -d '<raw JSON>'
```

- `200 {"valid":true,"questions":N}` → good.
- `422 {"violations":[{"path":"questions[0].q","message":"…"}]}` → each `path`
  points at the field to fix; correct and re-post.

No API reachable? Self-check against the minimum shape above (and the rules in
the docs), then load the page as admin — an invalid quiz shows a red error panel
listing each violation (admins only; visitors see nothing).

## Example (flat page, with an escaped apostrophe)

```twig
{{ quiz('{"title":"Culture générale","feedback":"immediate","questions":[{"q":"Qu\'est-ce que la Lune ?","answers":[{"a":"Une étoile"},{"a":"Un satellite naturel","correct":true},{"a":"Une planète"}],"explanation":"La Lune est le satellite naturel de la Terre."},{"q":"Combien de continents compte la Terre ?","answers":[{"a":"Cinq"},{"a":"Sept","correct":true},{"a":"Neuf"}],"explanation":"On en compte sept."}],"results":[{"min":0,"msg":"À revoir"},{"min":50,"msg":"Bien joué !"}]}') }}
```

Note `Qu\'est-ce` — the apostrophe is escaped for the Twig string.
