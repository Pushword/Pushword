---
name: ui-debug
description: Log into the Pushword dev-app admin with dev-browser and take screenshots. Use when validating admin UI or frontend changes in a real browser.
---

# Debugging the dev-app UI

Start the dev server first (`composer dev` from `packages/dev-app/`, check with
`symfony server:list`).

Credentials: `admin@example.tld` / `p@ssword` (ROLE_SUPER_ADMIN); reset via
`composer reset-dev-app`.

## Login is two-step

The dev server (`http://127.0.0.1:8000` — **http**, not https) does *not* use a classic
`_username`/`_password` form. It is two pages:

1. `GET /login` → fill `#inputEmail` (often prefilled) → submit `button[type=submit]`
   ("Continue"). The Google/Microsoft buttons are alternatives on this step.
2. Lands on `/login?step=password` → fill `#inputPassword` → submit ("Log in").

Scripts run in the `dev-browser` CLI's QuickJS sandbox: no `import`, no `require`, no
`fs`. A pre-connected `browser` global is provided, and pages are real Playwright `Page`
objects. Screenshots go through the `saveScreenshot(buf, name)` helper, which writes to
`~/.dev-browser/tmp/`.

```bash
dev-browser --headless --timeout 90 <<'EOF'
const page = await browser.getPage("pushword-admin");

await page.goto("http://127.0.0.1:8000/login", { waitUntil: "domcontentloaded" });
await page.fill('#inputEmail', 'admin@example.tld');
await page.click('button[type="submit"]');
await page.waitForLoadState('domcontentloaded');   // -> /login?step=password

await page.fill('#inputPassword', 'p@ssword');
await page.click('button[type="submit"]');
await page.waitForLoadState('domcontentloaded');   // -> /admin/page

console.log(await saveScreenshot(await page.screenshot(), "admin.png"));
EOF
```

Check the port first — `symfony server:list` may show another project already on 8000, in
which case start the dev-app elsewhere (`symfony server:start -d --no-tls --port=8011`).

## Screenshots are scaled

Browser-automation screenshots come back scaled relative to the real viewport (e.g. a
1512px image of an ~1863px-wide page), so raw pixel click coordinates drift and land on
the wrong element. Verify UI state through JS (`getBoundingClientRect`, `Alpine.$data`,
`.click()`) or `ref`-based clicks rather than coordinates. Native HTML5 drag-and-drop
cannot be driven by automation at all — unit-test the reorder function in-page instead.

## Editing locks

`PageEditLockManager` writes JSON locks to `packages/dev-app/var/page-locks/`, shared with
the test suite — so leaving an admin edit tab open makes `PageLockControllerTest` fail.
Close the tab rather than deleting the file; the `test-triage` skill has the full
signature if you hit that failure.
