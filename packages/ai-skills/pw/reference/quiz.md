# quiz — author a Pushword quiz

Create an interactive multiple-choice quiz and embed it in a page. It renders
server-side (good for SEO) and `quiz.js` upgrades it into a game in the browser.

**The full field schema lives in the project docs — read it first:**

- Monorepo: `packages/docs/content/extension/quiz.md`
- Downstream site: `vendor/pushword/docs/content/extension/quiz.md`

Or fetch the machine-readable JSON Schema in one shot:
`php bin/console pw:quiz:schema` (keys, aliases, enums).

This playbook covers the three things that matter when authoring by hand:
embedding, the (now-gone) quote footgun, and validating. The minimum valid
shape: at least one question, each with a non-empty `q`, **≥2 answers**, and
**≥1** flagged `"correct": true`.

## 1. Embed (flat-file site)

A quiz is one block placed in the page's Markdown content. On a `pushword/flat`
site that means editing the target page's `.md` file and pasting the block where
the quiz should appear in the body. **Use the `{% quiz %}` block** — the JSON is
its raw body, so apostrophes and quotes need **no escaping**:

```twig
{% quiz %}{ …a JSON object describing the quiz… }{% endquiz %}
```

- The JSON can be one line or pretty-printed, but keep it **free of blank lines**
  (the Markdown pipeline splits blocks on blank lines).
- Do **not** set `filter_twig: 0` in the page front-matter — that disables Twig
  for the page and the block would print as literal text.

> Legacy form: `{{ quiz('{ …json… }') }}` still works, but there the JSON is a
> single-quoted Twig string, so every apostrophe must be escaped as `\'`
> (`"Qu\'est-ce…"`). Prefer `{% quiz %}` and skip the escaping entirely.

## 2. No more quote footgun

With `{% quiz %}` the body is raw: write `Qu'est-ce que…` literally, no `\'`.
(Twig `{{ … }}` inside the block is still interpolated, but the result is then
JSON-decoded — so only quote-free output is safe; a helper like `{{ link() }}`
emits HTML with double quotes and would break the JSON.)

## 3. Validate before declaring done

Lint the file directly — **no server needed**:

```bash
php bin/console pw:quiz:validate content/<host>/<page>.md
#  ✓ Quiz #1 (line 12, {% quiz %}) — valid
# or pipe a draft on stdin:
cat draft.md | php bin/console pw:quiz:validate -
```

It finds every quiz block (both forms), prints `{path, message}` violations,
exits non-zero on failure, and warns on an unknown `cta` form type.

If you only have HTTP access and `pushword/api` is installed, post the **raw**
JSON to `/api/quiz/validate` instead:

```bash
php bin/console pw:user:token admin@example.tld   # mint a token
curl -X POST https://<site>/api/quiz/validate \
  -H "Authorization: Bearer <token>" -H "Content-Type: application/json" \
  -d '<raw JSON>'
# 200 {"valid":true,"questions":N} → good
# 422 {"violations":[{"path":"questions[0].q","message":"…"}]} → fix each path, re-post
```

Neither reachable? Self-check against the minimum shape above (and the rules in
the docs), then load the page as admin — an invalid quiz shows a red error panel
listing each violation (admins only; visitors see nothing).

## Example (flat page, apostrophes left raw)

```twig
{% quiz %}{"title":"Culture générale","feedback":"immediate","questions":[{"q":"Qu'est-ce que la Lune ?","answers":[{"a":"Une étoile"},{"a":"Un satellite naturel","correct":true},{"a":"Une planète"}],"explanation":"La Lune est le satellite naturel de la Terre."},{"q":"Combien de continents compte la Terre ?","answers":[{"a":"Cinq"},{"a":"Sept","correct":true},{"a":"Neuf"}],"explanation":"On en compte sept."}],"results":[{"min":0,"msg":"À revoir"},{"min":50,"msg":"Bien joué !"}]}{% endquiz %}
```

Note `Qu'est-ce` — the apostrophe is written literally, no escaping.
