# Plan — Adopt htmx 4: upgrade, don't reimplement

**Decision (2026-08-02).** htmx 4 converged on the subset engine's own design —
fetch-based, explicit inheritance, no XHR event model — which makes building
that engine redundant. This plan replaces the build with an adoption:
htmx 4 wherever htmx already runs, and `data-live` kept **permanently** as the
zero-dependency tier, aliasing onto htmx when htmx is present.

**Relationship to `Plans/live-block-directives.md`**: that plan (the `hx-*`
subset engine, pinned to htmx 2.0.10) is kept intact as the fallback if this
decision is reversed. Its analysis transfers here wholesale: the template
inventory, the trigger idioms, the coexistence design, the teleport risk. What
dies with it: the engine, the parity harness, the namespace-yield logic, the
out-of-subset boundary doc.

## Verified facts (2026-08-02)

- **htmx `4.0.0-beta6`** on npm; measured `dist/htmx.min.js`: 36.3 kB raw,
  **12.8 kB gz** — smaller than 2.0.10 (51.2 / 16.5 kB). Announced timeline:
  beta through 2026, stable / `latest` tag early 2027; htmx 2 keeps perpetual
  support.
- Breaking v2 → v4, the subset that touches us:
  - **fetch replaces XHR** — no `event.detail.xhr`; events carry a
    `detail.ctx` request/response context.
  - **Event names**: `htmx:<phase>:<action>` — `htmx:afterSwap` →
    `htmx:after:swap`, `htmx:beforeRequest` → `htmx:before:request`, etc.
  - **Inheritance is explicit**: ancestors share an attribute only with the
    `:inherited` modifier (`hx-target:inherited="#out"`); `hx-disinherit` /
    `hx-inherit` removed as redundant.
  - **Error responses swap by default** (4xx/5xx HTML replaces the target —
    v2 didn't swap). Revert via config `noSwap` or per-status `hx-status:XXX`.
  - Renames: `hx-disabled-elt` → `hx-disable`; old `hx-disable` → `hx-ignore`.
  - Removed: `hx-ext` (extensions load as plain scripts), `hx-vars`,
    `hx-params`, `hx-prompt`; `withCredentials` config →
    `hx-config='credentials:"include"'` (per element) or global config.
  - New defaults: **60 s request timeout** (v2: unlimited); history restores
    by refetch (no DOM snapshot cache); OOB swaps run after the main swap.
  - Kept and unchanged for us: `hx-get/post`, `hx-target`, `hx-swap`
    (all styles incl. `none`; adds `innerMorph`/`outerMorph`), `hx-vals` with
    `js:` prefix, full `hx-trigger` grammar (`load`, `delay:`, `once`,
    `changed`, `from:`, `[condition]`), `hx-indicator`, `hx-boost`, `hx-sync`.

**Consequence for the old plan's "named divergences"**: fetch-instead-of-XHR
and explicit-only inheritance are now upstream behaviour, and always-include
credentials is one `hx-config` line. Zero-job migration becomes zero-job by
identity — templates run on the real library.

## Track 1 — admin: htmx 1.9.12 → pinned v4 (now)

The ten admin/scanner/static-generator/conversation templates' complete
grammar (`hx-get`, `hx-post`, `hx-target="#id"`, `hx-swap="outerHTML|
innerHTML"`, `hx-vals='js:{…}'`, `hx-trigger` ∈ {`load`, `load delay:500ms`,
`change`, `blur`, `change, blur`, `blur changed`}) is **valid v4 as-is**, and
no template relies on ancestor attributes, so the explicit-inheritance flip is
a no-op. Plus the Ctrl+S form (`packages/admin/src/templates/page/edit.html.twig:22-24`):
`hx-post` + `hx-trigger="pw-ctrl-s-event"` + `hx-swap="none"` — also valid v4.
**Zero template edits.** The JS work:

1. `packages/admin/package.json:19`: `"htmx.org": "^1.9.12"` →
   **exact pin** `"4.0.0-beta6"` (no caret while beta; bump deliberately and
   re-run the browser checklist each time).
2. `admin.js:4-5` keeps `import htmx` + `window.htmx = htmx`; directly after,
   restore v2 error behaviour: configure `noSwap` for 4xx/5xx (admin
   fragments must never swap an error page into a list row).
3. `admin.js:68`: `htmx:afterSwap` → `htmx:after:swap`.
4. `admin.ctrlSAutoSave.js:88-110`: the four v2 event names remapped to the
   v4 set (`htmx:before:request`, `htmx:after:request`, `htmx:response:error`,
   plus `htmx:error`/`htmx:finally:request` for the reset path), and
   **`:94` `event.detail.xhr.status` rewritten** to read the status from the
   v4 `detail.ctx` (exact field verified against the beta6 source in
   `node_modules` at implementation — do not guess it).
5. Guard greps before calling it done: `hx-ext|hx-vars|hx-params|hx-prompt|
   hx-disabled-elt|hx-swap-oob` in templates (expected: none) and
   `htmx:[a-z]+[A-Z]` camelCase listeners in all admin assets (expected:
   only the two files above).
6. **Browser verification** (dev-browser skill, admin creds in CLAUDE.md):
   page-list inline title save (`hx-vals js:` + `changed`), weight/hold/
   published toggles, conversation title + gallery fields, Ctrl+S autosave
   (success indicator AND failure path — kill the server to see it), scanner
   and static-generator log tails (the load-recursion polling contract: the
   fetched fragment re-carries `hx-trigger="load delay:500ms"`, the server
   ends the loop by omitting it). The 60 s default timeout is harmless here —
   every poll request is short.

htmx stays a dependency of `pushword/admin` (that was already true); what
disappears is the two-majors-behind 1.9 pin, not the library.

## Track 2 — js-helper: permanent zero-dep tier + alias under htmx

The `data-live` / `data-src-live` / `.live-form` processor
(`packages/js-helper/src/helpers.js:32-166`) is **permanent**, not a
one-release shim: sites that ship no JS framework keep a ~80-line, eval-free,
zero-dependency way to have live blocks. Two additions.

### The planned evolutions (from the original plan, kept)

Small, string-matched, no `Function` anywhere — strict-CSP sites stay safe:

- **`data-live-trigger="<event>"`** — defer the fetch until a custom event
  fires on `window`; once by default (the phoneModal case). Add
  `data-live-repeat` for fresh-per-open (the FurtherTreks case): the block's
  **content** is replaced (inner swap) so the trigger element survives and
  re-fires.
- **`data-live-if="media:<media query>"`** — `matchMedia` gate, re-evaluated
  on every rescan pass; a `change` listener on the MediaQueryList re-runs
  `liveBlock()` so a window widened later (or a rotated phone) still loads.
- Gate prefixes become a **fail-closed registry** (`cookie:`, `media:`);
  unknown prefixes skip the block instead of failing open. Safe: the only
  gate in the wild is `cookie:pw_auth=1` (verified in the first plan's
  consolidation).
- Unchanged invariants: POST, `credentials: 'include'`, no swap on 4xx/5xx +
  `live-block-forbidden`, **attribute-strip on failure** (the no-infinite-
  refetch rule from `.claude/rules/admin-ui.md`), `DOMChanged` after swap.

### Alias mode — when htmx 4 is on the page

At scan time, if `window.htmx` with major version ≥ 4 exists, the processor
does not fetch: it **translates** the block's directives into `hx-*`
attributes and hands the element to `htmx.process(el)` — one implementation
of request semantics (htmx's), ours only evaluates the eval-free gates first.
A gate that fails leaves the block untranslated for the next pass. Under
htmx **2** (what GA runs today), no translation — the legacy processor fetches
itself, exactly as now; translation semantics are only validated against v4.

| legacy | translated to |
|---|---|
| `data-live="URL"` | `hx-post="URL" hx-trigger="load" hx-swap="outerHTML"` + `hx-config='credentials:"include"'` |
| `data-src-live` button | `hx-post` + `hx-target` on the block + `hx-swap="outerHTML"` (click is htmx's default trigger) |
| `.live-form` | `hx-post` on the form, `hx-target="closest .live-form"`, `hx-swap="outerHTML"` |
| `data-live-trigger="evt"` | `hx-trigger="evt from:window once"` (drop `once` + inner swap when `data-live-repeat`) |
| `data-live-if="cookie:…"` | no htmx equivalent — stays ours, evaluated before translation |
| `data-live-if="media:…"` | evaluated before translation (native authors write the composed `load[cond], resize[cond] from:window once delay:200ms` idiom instead) |

Equivalence requirements under alias: 4xx/5xx must not swap (per-element
`hx-config` `noSwap` if beta6 supports it there, else a targeted
`htmx:before:swap` listener setting `shouldSwap = false` — verify at
implementation) and `htmx:response:error` re-dispatched as
`live-block-forbidden` `{status, url}` for existing listeners. The
attribute-strip rule is unnecessary under alias — htmx's `load` fires once
per node by design.

### The bridge (~10 lines, bidirectional)

Installed whenever `window.htmx` exists:

- `htmx:after:swap` (and v2's `htmx:afterSwap` during the transition) →
  dispatch `DOMChanged` — ecosystem re-init keeps working.
- `DOMChanged` → `htmx.process(detail.target ?? document.body)` — content
  added outside a swap (an Alpine `x-teleport` clone: the modal dispatches
  `DOMChanged` on open, `GA modal.html.twig:26`) becomes discoverable. No
  loop: re-processing just-swapped, unchanged nodes is a no-op (htmx's
  attribute-hash re-init), and this single mechanism replaces the first
  plan's whole Phase 2 teleport fix.

Ship: `helpers.js` + tests (`helpers.test.js` for the evolutions; alias tests
with `htmx.org@4` as a devDependency under jsdom — if v4 proves un-hostable in
jsdom, the alias cases move to a dev-browser checklist, documented, never
silently skipped). Then rebuild `packages/core` assets (`yarn build` — the
compiled `app.js` is what ships, per `.claude/rules/admin-ui.md`).

The two in-repo `data-live` templates (`page_default.html.twig:100-101`,
newsletter `form_placeholder.html.twig:7`) **stay as they are** — they are now
the reference examples of the zero-dep tier, and the cookie-gated admin
buttons can never be htmx-native anyway.

## Track 3 — checkout + GA/FT: htmx 2.0.x → 4 (downstream, after Tracks 1–2)

Ordered last: the admin (Track 1) proves v4 + our config in the environment we
fully control, and the js-helper release (Track 2) must be deployed first so
`data-live` blocks keep working the day GA's `window.htmx` becomes v4
(alias mode activates automatically). Per downstream repo:

1. Bump `htmx.org` 2.0.8/2.0.10 → the same pinned v4 the admin validated.
2. **Inheritance audit**: grep containers whose `hx-target` / `hx-swap` /
   `hx-headers` descendants rely on → add `:inherited`. Delete the ×4
   `hx-disinherit` — v4's explicit default is what disinherit was patching.
3. **Event/JS audit**: grep `htmx:[a-z]+[A-Z]` camelCase listeners
   (htmx-boost.js, checkout scripts) → v4 names; any `detail.xhr` reads →
   `detail.ctx`.
4. **Error-swap flip**: audit checkout responses that return non-2xx HTML
   (422 re-rendered forms would now swap by default — possibly *better* than
   the current hand-rolled handling; decide per site, else set `noSwap`).
5. `hx-ext` is gone: `htmx-ext-preload` needs a v4-compatible build or gets
   dropped — the native-navigation pilot's speculation rules already cover
   hover-preloading, so dropping is the likely answer.
6. `hx-boost` still exists in v4 but is exiting via the native-navigation
   pilot regardless — don't invest; under v4 boost must be
   `hx-boost:inherited="true"` at the root to keep covering descendants.
   The three `hx-boost="false"` opt-out markers in **core** templates
   (`user/layout.html.twig:7`, `user/login.html.twig:27`,
   `page/_admin_buttons.html.twig:1`) keep working under both majors (child
   wins) — delete them only when the fleet-wide boost exit completes.
7. History is refetch-based in v4 — verify checkout back-button UX (for a
   tunnel, refetch is usually more correct than a snapshot).
8. Escape hatch: the official `htmx-2-compat` extension, per page, if one
   template can't migrate cleanly in a pass.

## Ordering and risk

- Track 1 → Track 2 (released) → Track 3. The native-navigation pilot
  proceeds independently throughout.
- **Beta churn** is the main risk: exact-pin every install; re-run the
  Track 1 browser checklist on every bump; config/HCON spellings may still
  move before stable. Internal surfaces (admin) ride the beta; downstream
  guidance in docs waits for the first stable tag.
- **The error-swap flip is the loaded gun**: v2 behaviour must be restored
  explicitly everywhere htmx 4 lands (admin config in `admin.js`, per-element
  under alias, documented snippet for downstream). A forgotten `noSwap` means
  a 403 login page swapped into a page fragment.
- Unknowns deliberately deferred to implementation, to be checked against the
  installed beta6 source, not docs: exact `detail.ctx` response-status field,
  per-element `hx-config` `noSwap` support, jsdom hostability, v4-compatible
  preload extension.
- The regression that must not return (infinite re-fetch of a failed block,
  `.claude/rules/admin-ui.md`): legacy tier keeps attribute-strip; alias tier
  inherits htmx's load-once-per-node semantics.

## Docs

`packages/docs/content/extension/page-cache.md:97-110` ("htmx alternative")
inverts: `hx-*` on real htmx 4 **is** the rich tier, `data-live` the permanent
zero-dependency tier. Add: the alias table above as the migration reference,
the downstream config snippet (`credentials` + `noSwap` + the 60 s timeout
note), the CSP note (`hx-vals js:` and `[cond]` need eval under htmx; the
`data-live` tier never does), the `DOMChanged` ⇄ `htmx.process` bridge
contract, and the "wait for stable before adopting v4 downstream" line until
it flips.

## Log

2026-08-02 (executed: Tracks 1–2) — Track 1 shipped and browser-verified
(inline saves, toggles, Ctrl+S, scanner 3-poll + static-generator 2-poll
recursion, noSwap held on a live 500). Two v4 findings beyond the plan:
(a) when a fragment swaps its own trigger away, `htmx:after:swap` is
dispatched **on `document`** (detached-source fallback, `trigger()`:
`on?.isConnected ? on : document`) — bridge listeners moved from `body` to
`document`; (b) v4 seeds the `changed` modifier lazily (first event always
fires) and our fragments swap in a fresh input per save, so every focus+blur
would post a no-op inline-update — mitigated in `admin.js` by cancelling
`htmx:config:request` when `value === defaultValue`, restoring v2 semantics
(verified: 0 requests unchanged / 1 on edit / 0 on the fresh input).
Track 2 shipped (12 new vitest cases, 87 pass; core assets rebuilt;
page-cache.md rewritten) with three deliberate deviations: alias covers
`data-live` blocks only — `data-src-live` buttons and `.live-form` keep the
legacy fetch path (user-triggered, custom spinner UX, no discovery-timing
problem to solve); the bridge is v4-only (no v2 `htmx:afterSwap` relay — a
js-helper update must not change GA's live behaviour while the fleet is on
v2); per-element error no-swap is `hx-status:4xx/5xx="swap:none"` because
`hx-config` merges into `ctx.request` only, so `noSwap` cannot be set there.
Deferred unknowns resolved against beta6 source: status lives at
`ctx.response.status`; `from:window` resolves via the extended selector
(`:1917`); `hx-target` defaults to the element itself; alias unit tests use a
stub htmx (real-engine behaviour is browser-verified in the admin).
Remaining: Track 3 (downstream), a dedicated js-helper live-block doc page.

2026-08-02 — plan created after the htmx 4 evaluation: v4 (fetch, explicit
inheritance, 12.8 kB gz measured on beta6) implements the first plan's named
divergences upstream, so the subset engine is cancelled in favour of adoption.
New findings over the first plan's inventory: `admin.ctrlSAutoSave.js`
listens to four v2 camelCase events and reads `event.detail.xhr.status`
(breaks under fetch); the Ctrl+S form in `edit.html.twig` is an eleventh
in-grammar template (`hx-swap="none"`, custom-event trigger); three
`hx-boost="false"` markers live in core public templates as downstream boost
opt-outs. Track 3 widened from "checkout stays on v2" to full v4 migration
per Robin's decision.
