---
name: site-audit
description: Browse the Pushword site and admin to identify bugs, then propose fixes. Use when asked to audit, test, find bugs, or QA the site.
user-invocable: true
argument-hint: "[base-url]"
---

# Site Audit Skill

You will browse the entire Pushword site (public pages and admin) using dev-browser, identify bugs, and propose fixes. Sub-agents keep the main context window clean.

## Step 1: Detect base URL

If `$ARGUMENTS` is provided and non-empty, use it as the base URL.

Otherwise, run `symfony server:list` from `packages/skeleton/` to find the running local server URL. If no server is running, tell the user to start one with `composer dev` from `packages/skeleton/` and stop.

Store the base URL (e.g. `https://127.0.0.1:8000`) for use in all subsequent steps.

## Step 2: Start dev-browser

Invoke the `/dev-browser` skill to start the browser automation server.

## Step 3: Login once

Run a dev-browser script to authenticate as admin and create a reusable page named `admin-session`:

```typescript
import { connect, waitForPageLoad } from '@/client.js'

const client = await connect()
const page = await client.page('admin-session')
await page.setViewportSize({ width: 1280, height: 800 })

await page.goto('BASE_URL/admin')
await waitForPageLoad(page)

await page.fill('input[name="_username"]', 'admin@example.tld')
await page.fill('input[name="_password"]', 'p@ssword')
await page.click('button[type="submit"]')
await waitForPageLoad(page)

await page.screenshot({ path: 'tmp/audit-login.png' })
await client.disconnect()
```

Replace `BASE_URL` with the actual base URL. Verify the login succeeded by reading the screenshot.

## Step 4: Run sub-agents sequentially

Launch 4 sub-agents **one at a time** (sequential, NOT parallel) using the Agent tool with `subagent_type: general-purpose`. Each sub-agent reuses the `admin-session` page from dev-browser.

**IMPORTANT for all sub-agents:**
- All test data created must use the prefix `__audit_test_` so it's identifiable
- Each sub-agent must **clean up after itself** (delete any test records it created)
- Save screenshots to `tmp/` with descriptive names (e.g. `tmp/audit-public-homepage.png`)
- Report findings as a list with severity: `CRITICAL`, `HIGH`, `MEDIUM`, `LOW`, `INFO`
- Use `getAISnapshot()` to discover page elements when selectors are unknown
- Use `waitForPageLoad(page)` after every navigation

### Sub-agent A: Public Pages

Prompt the sub-agent with:

> You have access to a dev-browser instance. Use the existing `admin-session` page OR create a new `public-session` page for public browsing.
>
> Base URL: {BASE_URL}
>
> Browse all public pages and check for issues:
> 1. Homepage - loads correctly, no console errors, layout intact
> 2. Sitemap.xml - valid XML, contains expected URLs
> 3. RSS/Atom feed - valid, has entries
> 4. robots.txt - present and reasonable
> 5. Follow 5-10 internal links from homepage - all return 200, no broken links
> 6. Test mobile viewport (375x812) on homepage and one inner page - no horizontal overflow
> 7. Click all navigation items - verify they work
> 8. If search is present, test it with a query
>
> Save screenshots to `tmp/audit-public-*.png`. Report all issues found with severity levels.

### Sub-agent B: Auth Flow

> You have access to a dev-browser instance. Create a new page `auth-session` for this test.
>
> Base URL: {BASE_URL}
>
> Test the authentication flow:
> 1. Go to login page, submit with wrong credentials (`bad@example.com` / `wrong`) - verify error message appears
> 2. If forgot-password link exists, click it and test the form (submit with `admin@example.tld`)
> 3. Login with correct credentials (`admin@example.tld` / `p@ssword`) - verify redirect to admin
> 4. Click logout - verify redirect to public site or login page
> 5. Login again to restore session for subsequent agents
>
> Save screenshots to `tmp/audit-auth-*.png`. Report all issues found with severity levels.

### Sub-agent C: Admin Pages CRUD

> You have access to a dev-browser instance. Use the existing `admin-session` page (already logged in).
>
> Base URL: {BASE_URL}
>
> Test admin CRUD operations:
> 1. **List pages** - Navigate to admin page list, verify it loads, check pagination if present
> 2. **Create page** - Click "Create Page", fill form with title `__audit_test_page`, slug `__audit-test-page`, set a body, submit. Verify success flash message.
> 3. **Edit page** - Find the created page in list, click edit, change the title to `__audit_test_page_edited`, save. Verify changes persisted.
> 4. **Inline edit** - If inline-editable fields exist (weight, tags), test them
> 5. **View page** - Visit the public URL of the test page, verify it renders
> 6. **Delete page** - Delete the `__audit_test_page_edited` page. Verify it's removed from the list.
> 7. **Redirections** - If a redirections section exists in admin, navigate to it and verify it loads
> 8. **Cheatsheet** - If a cheatsheet/help section exists, verify it loads
>
> IMPORTANT: Clean up - ensure the test page is deleted before finishing.
>
> Save screenshots to `tmp/audit-admin-crud-*.png`. Report all issues found with severity levels.

### Sub-agent D: Admin Media & Users

> You have access to a dev-browser instance. Use the existing `admin-session` page (already logged in).
>
> Base URL: {BASE_URL}
>
> Test media and user management:
> 1. **List media** - Navigate to media list, verify it loads
> 2. **Upload media** - If upload is available, test uploading a small test image. Name it `__audit_test_media`. Verify it appears in the list.
> 3. **Edit media** - Edit the uploaded media metadata if possible, save
> 4. **Delete media** - Delete the test media, verify removal
> 5. **List users** - Navigate to user list, verify it loads
> 6. **Create user** - Create a test user with email `__audit_test_user@example.com`, verify success
> 7. **Edit user** - Edit the test user, verify changes save
> 8. **Delete user** - Delete the test user, verify removal
> 9. **Multi-upload** - If a multi-upload feature exists, test it
>
> IMPORTANT: Clean up - ensure all test records (__audit_test_*) are deleted before finishing.
>
> Save screenshots to `tmp/audit-admin-media-*.png`. Report all issues found with severity levels.

## Step 5: Aggregate findings

After all 4 sub-agents complete, combine their reports into a single structured bug report:

```
## Site Audit Report

### CRITICAL
- [description] (screenshot: tmp/audit-*.png)

### HIGH
- ...

### MEDIUM
- ...

### LOW
- ...

### INFO
- ...
```

If no bugs are found, report that the site passed the audit.

## Step 6: Propose fix plan

For each bug found:
1. Identify the likely source file(s) using Grep/Glob to search the codebase
2. Describe the root cause
3. Suggest a specific fix (code change or configuration)

Present fixes grouped by severity, highest first.
