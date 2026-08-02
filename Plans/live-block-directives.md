# Plan — js-helper speaks htmx: a semantic subset engine

## Goal

Replace the bespoke `data-live` vocabulary with **htmx's own syntax**:
js-helper implements a small, semantically exact subset of the `hx-*`
attribute API. Templates are authored in htmx syntax, full stop.

- **Zero-job switching.** A site that outgrows the subset loads real htmx and
  changes nothing in its templates. js-helper detects `window.htmx` at init
  and yields: it disables its own engine and installs the
  `htmx:afterSwap → DOMChanged` bridge so Pushword-ecosystem enhancements
  (quiz.js, icons, …) keep re-initialising. Switching libraries = swapping a
  script, in either direction.
- **The internal drop lands for free.** Every Pushword-owned htmx template
  (admin inline saves, scanner/static-generator log tails) already sits inside
  the subset — grammar inventoried below — so removing htmx 1.9 from
  `pushword/admin` is an engine swap with **zero template edits**.
- The original gates/triggers goal (defer or condition a block's fetch) is
  reached with stock htmx grammar: `hx-trigger="load[cond]"`,
  `hx-trigger="my-event from:window"`, `once`, `delay:`.

**The prime rule: semantic parity.** For everything the subset implements,
behaviour must be indistinguishable from htmx. The reference is not the docs
but the source: **htmx 2.0.10, `dist/htmx.js`** (unminified, in GA/FT
`node_modules/htmx.org/` — the exact version the fleet runs). All `:NNNN`
references below point into that file. Anything outside the subset is not
approximated: the engine `console.warn`s and ignores it ("load htmx for
this"). The parity test suite (Phase 0) makes the claim enforceable.

Scope: `packages/js-helper` (+ tests), two in-repo template conversions,
`admin.js` engine swap, docs. No PHP logic changes. BC is allowed; a
one-release legacy shim keeps `data-live` sites working during the transition.

## Current state (baseline)

1. **`data-live` today**: POST on load, `credentials: 'include'`, `outerHTML`
   self-swap, `DOMChanged` after (`packages/js-helper/src/helpers.js:32-65`);
   one gate prefix `cookie:` (`:124-133`), unknown prefixes fail open. Plus
   `data-src-live` buttons (`:143-153`) and `.live-form` (`:155-166`).
2. **`liveBlock()` re-runs on every `DOMChanged`** (`app.js:26-27,47`) — this
   rescan becomes the engine's discovery pass, the equivalent of
   `htmx.process()` on new content.
3. **In-repo `data-live` consumers** (to convert): the admin-buttons fragment
   (`packages/core/src/templates/page/page_default.html.twig:100-101`,
   `cookie:pw_auth=1` gate) and the newsletter placeholder
   (`packages/newsletter/src/templates/newsletter/form_placeholder.html.twig:7`).
4. **Pushword-owned htmx templates** (already in target syntax): admin
   `weight_inline_field`, `hold_toggle`, `published_toggle`,
   `pageListTitleField`; conversation `messageListTitleField`,
   `messageListMediaGallery`; page-scanner `scanning`, `output_fragment`;
   static-generator `running`, `output_fragment`. Their **complete** grammar,
   by grep: `hx-get`, `hx-post`, `hx-target="#id"`,
   `hx-swap="outerHTML|innerHTML"`, `hx-vals='js:{…}'`, `hx-trigger` ∈
   { `load`, `load delay:500ms`, `change`, `blur`, `change, blur`,
   `blur changed` }. This is the subset's floor.
5. **`admin.js` bundles htmx 1.9.12** (`packages/admin/package.json:19`) and
   bridges `htmx:afterSwap` → `markContentEditableElements` (`admin.js:68`).
6. **`document.querySelectorAll` cannot see inside `<template>`.** The
   downstream modal call sites sit in `<template x-teleport="body">`; blocks
   become scannable only after Alpine clones them into `<body>`, on the next
   `DOMChanged` pass. Kept as the Phase 2 acceptance concern.

## Evidence (conclusions unchanged, target syntax final)

**A. Downstream modals** (`../GA-microsites`):
- `phoneModal.html.twig:92` + `modal.html.twig:95` fetch on ~every pageview
  from a shut modal → `hx-trigger="open-conversation-modal from:window once"`.
- FurtherTreks `_modal.html.twig:51-57` wants fresh-per-open → same trigger
  **without** `once` + `hx-swap="innerHTML"` (surviving element re-fires —
  stock htmx behaviour).
- GrandAngle's `isSharedModal` branch (`modal.html.twig:73-90`) builds its URL
  per open from Alpine state — unreachable by any attribute syntax, stays
  hand-rolled, not part of the ROI.

**B. Mobile-invisible widget** (`_navbarPart1.html.twig:160`) →

```
hx-trigger="load[matchMedia('(min-width: 640px)').matches],
            resize[matchMedia('(min-width: 640px)').matches] from:window once delay:200ms"
```

Pure subset grammar. `load[cond]` alone would evaluate once at process time
(`:2728-2731`) and permanently miss a window widened later (or a phone
rotated to landscape); the composed `resize` trigger re-evaluates on real
viewport changes, `once` disarms after the first fetch (the condition filter
runs *before* the `once` check, `:2531` vs `:2549`, so failing evaluations
don't consume it), `delay:` debounces resize storms, and the self-cleaning
listener plus the detached-element entry check cover the element being
destroyed by the successful load-path swap.

**C. The internal htmx population** — baseline #4/#5. After the engine swap,
htmx and its bridge leave the admin bundle; the fleet's oldest htmx pin (1.9,
two majors behind) disappears instead of owing a migration.

**D. Downstream context, not deliverables**: boost exits via the
native-navigation pilot (platform, not this lib); the checkout tunnel (~20
call sites, incl. `hx-include`/`hx-headers`/`hx-push-url`/`hx-disinherit`)
stays on real htmx, scoped per-page once boost is gone.

## The subset — pinned against htmx 2.0.10 source

### Attributes

Both `hx-*` and `data-hx-*` spellings are scanned, as htmx does.

| attribute | supported | source ref |
|---|---|---|
| `hx-get` / `hx-post` | URL; other verbs (`put`/`delete`/`patch`) trivial to add if a consumer appears | `:2669-2690` |
| `hx-target` | `this`, extended selector (below) | `:1396-1412` |
| `hx-swap` | all eight standard styles: `innerHTML` (default), `outerHTML`, `beforebegin`, `afterbegin`, `beforeend`, `afterend`, `delete`, `none`; modifiers `swap:`/`settle:` honoured as delays, others logged + ignored (htmx's own `logError` behaviour for unknowns, `:3809`) | `:3764-3815` |
| `hx-vals` | JSON (`parseJSON`), `js:`/`javascript:` prefix → evaluated with `this = elt` and `event` in scope; auto-wrapped in `{…}`; `unset`; parent-recursive merge, nearest wins | `:3917-3962` |
| `hx-trigger` | grammar below | `:2234-2346` |
| `hx-indicator` | extended selector; `htmx-request` class, reference-counted; defaults to the element itself | `:3371-3382` |
| `hx-disabled-elt` | extended selector; `disabled` + `data-disabled-by-htmx` marker, reference-counted — the standard htmx guard against double-submission on non-idempotent actions | `:3388-3426` |

**Inheritance is real and in-subset**: htmx resolves `hx-target`, `hx-swap`,
`hx-indicator` via *closest ancestor* (`getClosestAttributeValue`) and merges
`hx-vals` up the parent chain with child values winning. The engine does the
same — reading only the element itself would be a divergence, not a
simplification. `hx-disinherit` stays out (warn): templates relying on it
(GA checkout, ×4) are real-htmx pages.

**Extended selector** (used by `hx-target`, `from:`, `hx-indicator`,
`:1136-1208`): plain CSS selector (first match from document), `closest <sel>`,
`find <sel>`, `next` / `next <sel>`, `previous` / `previous <sel>`,
`document`, `window`, `body`. `global` prefix / `host` / `root`: out, warn.

### Trigger grammar (`:2234-2321`)

Comma-separated triggers; per trigger: event name, optional `[condition]`
immediately after the name, then space-separated modifiers.

- **names**: `load`, any DOM or custom event. Absent `hx-trigger` → htmx's
  element defaults (`:2337-2345`): `form` → `submit`;
  `input[type=button|submit]` → `click`; `input`/`textarea`/`select` →
  `change`; **everything else → `click`**. A load-on-appear live block always
  writes `hx-trigger="load"` explicitly — the visible cost of parity.
- **modifiers**: `once`, `changed`, `delay:<time>`, `from:<extended selector>`,
  `consume`, `target:<sel>` (event-target filter). Out, warn: `throttle:`,
  `queue:`, `every <time>` (recursion covers all known polling), `intersect`,
  `revealed`, `root:`, `threshold:`.
- **conditions** `[expr]` (`:2155-2195`): compiled with `Function`, gated by
  `allowEval` (→ `htmx:evalDisallowedError` when off, `:3970-3977`); bare
  symbols resolve as `event.X` falling back to `window.X` (htmx's token
  rewrite, `:2187-2191`); called with `this = elt`; a throwing filter fires
  `htmx:eventFilter:error` and counts as not-matching (`:2475-2487`).
- **in-grammar errors** fire `htmx:syntax:error` (`:2305,2313`) — parity —
  while *out-of-subset* features get the boundary `console.warn` (htmx would
  honour them; the warn is the fidelity marker).

### Trigger semantics — the details that differ from naive implementations

Verified against `:2496-2602`, `:2648-2661`, `:2704-2738`:

- **`from:` binds at processing time** to the elements the selector resolves
  to *then* — NOT delegation. Elements added later do not trigger (htmx
  behaviour). `from:window` listens on `window`.
- **`delay:` on an event trigger is a debounce** — each new event clears the
  pending timeout (`:2566-2568`). On `load`, it is a plain timeout
  (`:2656-2660`).
- **`once`** keeps the listener attached and gates on internal
  `triggeredOnce` (`:2549-2555`).
- **`changed`** seeds a per-listen-target WeakMap with `.value` at bind time
  and fires only when the value differs, updating it (`:2506-2517,2556-2565`).
- **`load` fires at first init only** (`!firstInitCompleted`, `:2728`),
  consumed via `nodeData.loaded`; its condition is evaluated once against a
  synthetic `load` event (`:2729`). Success or failure, it never re-fires —
  which is the static-host 404 protection, for free, with no attribute
  stripping.
- **Every listener self-cleans**: first statement, if the bound element left
  the document, remove the listener and return (`:2521-2524`). htmx already
  implements the detached-node hygiene rule this plan used to specify —
  copy it verbatim. Requests are also refused for detached elements at issue
  time (`:4285-4289`).
- `htmx:trigger` fires on the element before each handler run (`:2587`).
  A naked `hx-trigger` without verb registers a no-op handler (`:2956-2962`).
- **preventDefault rules** (`shouldCancel`, `:2435-2456`): form `submit`;
  `click` on a submit button belonging to a form; `click` on an anchor with a
  real href (bare `#` cancelled; `#fragment` left native).

### Concurrency (`:4330-4411`) — corrected

Without `hx-sync`, a second trigger while the element's request is in flight
is **not dropped and not fired concurrently: it is queued, strategy `last`** —
the newest waiting request replaces the queue and replays when the current
one completes (`endRequestLock`, `:4403-4411`). One in-flight request per
element (`eltData.xhr` as the lock). The engine reproduces exactly this — it
is htmx's own default, and the admin templates already live under it on real
htmx today. The replay path is safe against destroyed elements because
`issueAjaxRequest` refuses detached elements at entry (`:4285-4289`).
`hx-sync` itself: out, warn. For non-idempotent actions the standard htmx
antidote is in the subset: `hx-disabled-elt="this"`. Do **not** "fix" this by
diverging to drop-on-POST — parity is the prime rule, and drop semantics are
what `hx-sync="drop"` is for on real htmx.

### Processing model (`:2946-3025`)

- Discovery: the `DOMChanged` rescan walks new content the way
  `htmx.process()` does.
- **Per-node state lives in internal data (WeakMap), never in `dataset`** —
  dataset survives `outerHTML` serialization and cloning (an Alpine
  x-teleport clone would inherit stale guards); internal data does not, and a
  clone is simply a fresh unprocessed node. This corrects the dataset-guard
  approach of earlier revisions.
- **Re-init on attribute change**: each node stores a hash of its `hx-*`
  attributes (`initHash`); re-processing an unchanged node is a no-op, a
  changed node is de-inited (listeners removed) and re-inited
  (`:2979-2993`) — `load` still does not re-fire (`firstInitCompleted`).
- `htmx:beforeProcessNode` / `htmx:afterProcessNode` per inited node.

### Requests, responses, events

- **Headers sent** (`:3696-3713`): `HX-Request: true`, `HX-Trigger` (elt id),
  `HX-Trigger-Name` (name), `HX-Target` (target id), `HX-Current-URL`.
- **Credentials**: engine always includes them — behaves as htmx with
  `htmx.config.withCredentials = true` (htmx default is `false`, `:184`); the
  switch recipe is that one config line.
- **Response handling** (config `:264-268`): `204` → no swap; `2xx/3xx` →
  swap; `4xx/5xx` → no swap, error. `htmx:beforeSwap` fires on the target for
  **every** response, cancelable, with mutable `shouldSwap` / `serverResponse`
  / `isError` / `target` detail (`:4870-4890`) — this is htmx's error-override
  extension point and the subset keeps it. Errors then fire
  `htmx:responseError` (`:4969`); network failures `htmx:sendError` (`:4609`).
  The legacy `live-block-forbidden` event is kept for one release.
- **No abort-on-detach — by parity.** htmx never cancels an in-flight request
  because its element left the DOM: `xhr.onload` explicitly handles the
  detached case, re-firing `htmx:afterRequest` on the closest surviving
  parent (`:4585-4597`). The engine lets requests complete the same way.
  The explicit `htmx:abort` API (global body listener, `:5124`) is out of
  subset — no consumer; whether the engine uses an `AbortController`
  internally is an implementation detail, not a contract.
- **Fragments** (`makeFragment`, `:603-644`): full-document and `<body>`
  responses reduce to body children; a fragment `<title>` (or full-doc title)
  updates `document.title`; **`<script>` tags in swapped content execute**
  (`allowScriptTags` default true, re-created nodes, `:577-591`) — a real
  divergence from the old `data-live` behaviour, and required for parity.
- **Events**: all `CustomEvent` with `bubbles: true, cancelable: true,
  composed: true` (`:3044-3048`). Guaranteed detail fields: `elt`, `target`,
  `requestConfig` (`verb`, `path`), `pathInfo.requestPath`,
  `successful`/`failed`; `beforeSwap` adds the mutable set above. Named
  divergence: no `xhr` object in details (the engine is fetch-based; htmx
  uses XMLHttpRequest, `:4400`).
- **Settling: out of subset, named divergence.** `htmx:afterSwap` then
  `htmx:afterSettle` both fire (consumers listen to both), but there is no
  settle choreography — no `htmx-swapping`/`htmx-settling`/`htmx-added`
  classes, no id-based attribute merging (`:1418-1437`), `settle:` delay
  accepted but approximate. Graduates only if CSS somewhere depends on it.
- After every swap: `DOMChanged` on `document` — the Pushword ecosystem
  contract. Under real htmx, the installed bridge produces it from
  `htmx:afterSettle`.

### Coexistence — the switch mechanism

At init: if `window.htmx` exists, **the `hx-*` engine** does not start; it
only installs the `afterSettle → DOMChanged` bridge. Documented load-order
rule: htmx, when used, loads before the app bundle. This one check makes
"swap the script" the entire migration in both directions and prevents
double-firing when htmx is loaded for one page area (checkout) while
js-helper runs site-wide.

**The yield covers the `hx-*` namespace only — the legacy processor below
always runs.** This is not optional: GA loads htmx site-wide *today* (boost),
so a yield that also silenced `data-live` would kill every live block in the
downstream fleet the day js-helper updates, since real htmx ignores the
`data-live` namespace entirely.

## Legacy processor (one release)

Not a translation layer feeding the engine — **the current `data-live` /
`data-src-live` / `.live-form` code path is retained as-is** (it is ~80 lines
and already correct), running on every scan pass regardless of which `hx-*`
engine is active. The namespaces are disjoint, so there is no conflict with
real htmx. Two properties fall out of "retained, not translated":

- The `cookie:` gate stays the pure string comparison it is today
  (`helpers.js:124-133`) — it never routes through the `[cond]` eval path, so
  a strict-CSP site that works today keeps working. Only *authored*
  `hx-trigger="…[cond]"` involves `Function`, with the same CSP requirement
  it has under real htmx.
- No behavioural translation gaps: legacy blocks keep legacy semantics
  (attribute-stripping on failure included) until their templates convert.

One `console.warn` deprecation per page. Deleted one minor release after
downstream (GA-microsites' own templates; vendor copies follow the core
release) has converted to `hx-*`.

## Phases

### Phase 0 — Engine + parity harness

The subset as specified above, the coexistence check, the legacy shim.
Convert the two in-repo `data-live` templates (baseline #3). `liveBlock()`
stays the exported entry point; internals are the new engine.

**Parity harness — the acceptance methodology**: every behavioural test runs
its DOM fixture against *both* engines — ours and htmx 2.0.10 (imported from
the repo's own node_modules as a dev dependency) — under jsdom, asserting
identical outcomes: request issued or not, verb, body, headers, swapped
markup, event sequence, queue behaviour. Budget notes: mock `XMLHttpRequest`
for htmx's side and `fetch` for ours; stub `matchMedia`; behaviours jsdom
cannot host get an explicit documented exclusion, never a silent skip.
Priority parity cases, from the source review: queue-`last` replay,
`delay:` debounce vs `load delay:` timeout, `changed` seeding,
`from:` bind-time resolution (an element added later must NOT trigger),
condition token rewrite (`event.X ?? window.X`), element-default triggers,
script execution in fragments, title update, inheritance lookups.

### Phase 1 — Admin runs on the engine; htmx leaves the repo

Swap `import htmx` in `admin.js` for the js-helper engine — zero template
edits (baseline #4 is the floor; `hx-vals js:` and `changed` are in scope for
exactly this). Verify by hand: inline save/toggle on the page list, scanner +
static-generator log tails (the load-recursion polling contract: the fetched
fragment re-carries the trigger; the server ends the loop by omitting it).
Replace the `htmx:afterSwap` bridge (`admin.js:68`) with `DOMChanged`, drop
`htmx.org` from `packages/admin/package.json`.

### Phase 2 — Downstream conversions (GA-microsites follow-up, after a release)

Evidence A/B call sites in native syntax. Carries the **x-teleport acceptance
step** (real browser, dev-browser — jsdom can't see either issue):
(1) triggers inside `<template x-teleport>` only register on a `DOMChanged`
pass after Alpine teleports the clone (baseline #6) — if no pass happens the
modal spins forever; if the timing accident is load-bearing, fix deliberately
with a hook that works under **both** engines (e.g. an `alpine:init` listener
that invokes the active engine's process pass — ours, or `htmx.process()` on
real htmx; the coexistence bridge is the natural owner). A global
`MutationObserver` is explicitly not the fix: htmx core has none (verified —
zero occurrences in `dist/htmx.js`), content added outside a swap needs an
explicit process call under real htmx too, and auto-discovering what htmx
would ignore is a parity break, not a robustness win. (2) GrandAngle's
"outerHTML issues inside x-teleport" comment (`modal.html.twig:73`) — if
confirmed, `hx-swap="innerHTML"` is the stock answer.

### Phase 3 — `intersect` / `revealed`

Deferred on merit — no confirmed below-the-fold consumer. If built, mimic the
source: `intersect` = one IntersectionObserver per element with
`root:`/`threshold:` (`:2709-2727`); `revealed` = htmx's scroll/resize flag +
200 ms interval sweep and the `data-hx-revealed` marker attribute
(`:2606-2638`).

## Out of subset — the boundary is the feature

Warn-and-ignore, "load htmx" is the documented answer: `hx-boost` (navigation
belongs to the platform or to real htmx, never to this engine),
`hx-push-url`, `hx-sync`, `hx-select`, `hx-select-oob`, `hx-swap-oob`,
`hx-include`, `hx-headers`, `hx-params`, `hx-confirm`/`hx-prompt`,
`hx-disinherit`, `hx-preserve`, `hx-history`, `hx-ext`, `hx-on:*`, the
`htmx:abort` API, WS/SSE, `every` polling, HX-* response headers
(`HX-Redirect`/`HX-Retarget`/`HX-Trigger`/…), settle choreography. Each
graduates only with a Pushword-side consumer **and** its parity cases. The
subset staying an order of magnitude smaller than htmx (~16 kB min) is the
point; when a page's gap list grows long, that page has told you it wants
real htmx.

## Ordering and risk

- Phase 0 → 1 → 2 strictly; Phase 3 floats.
- The pivot's main risk is semantic drift; the parity harness plus the pinned
  source references above are the control. Parity targets **htmx 2.0.10**
  (what GA/FT run; admin's 1.9 is being deleted, not matched).
- The regression that must not return: a trigger registering on every rescan
  pass (`app.js:47`) — prevented structurally by the internal-data
  process-once model (unchanged `initHash` → no-op).

## Docs

`packages/docs/content/extension/page-cache.md`: the "htmx alternative"
section (`:97-110`) disappears — one syntax now. In its place: the subset
tables, the boundary list, the switch recipe (load htmx before the bundle +
`htmx.config.withCredentials = true`), the `DOMChanged`/`afterSettle`
pairing, the CSP/`allowEval` note, the shim timeline.

## Consolidation log

2026-08-02 (review): corrected evidence A (GrandAngle shared modal
unreachable; FurtherTreks needs a surviving container); settled media
semantics; lifecycle preamble; x-teleport acceptance step; fail-closed
verified safe (only `cookie:pw_auth=1` exists anywhere).

2026-08-02 (htmx merge): merged the htmx-replacement objective (admin inline
saves, log-tail polling, htmx 1.9 dropped from `pushword/admin`); introduced
an htmx-*mappable* `data-live-*` vocabulary.

2026-08-02 (zero-job pivot): js-helper implements the `hx-*` subset itself;
`data-live` & co. become a one-release shim; parity harness added; admin drop
reduced to an engine swap with zero template edits.

2026-08-02 (source review, htmx 2.0.10 `dist/htmx.js`): every subset claim
pinned to source with line refs. **Corrections**: default in-flight behaviour
is queue-`last` with replay, not drop; `from:` resolves elements at
processing time (delegation would diverge); `delay:` is a debounce on event
triggers; per-node guards must be internal data (WeakMap), not `dataset`.
**Pulled into subset** (htmx does it, cheap, consumers touch it):
target/swap/vals/indicator inheritance, all eight swap styles, extended
selectors, `hx-indicator` + `htmx-request` refcounting, script execution in
fragments, title handling, `htmx:beforeSwap` mutable detail, exact header
set, element-default triggers, `consume`/`target:` modifiers, attribute-hash
re-init. **Named divergences**: fetch instead of XHR (no `xhr` in event
details), always-include credentials, no settle choreography.

2026-08-02 (second colleague review): **accepted** — the legacy shim is
reframed as a retained legacy processor that runs independently of the
`hx-*` yield (the translation-based shim would have died the moment
`window.htmx` existed, i.e. on every GA page today, and its `cookie:` →
`[cond]` rewrite would have pushed today's eval-free gate through
`Function`, breaking strict-CSP sites); `hx-disabled-elt` pulled into the
subset as the standard double-submission antidote; Evidence B upgraded to
the composed `load[cond], resize[cond] from:window once delay:` idiom
(in-grammar, and it restores the rotation nicety previously dropped);
Phase 2's teleport fix constrained to work under both engines. **Rejected,
against source**: drop-on-POST divergence (queue-`last` is htmx's own
default, running in the admin today); auto-abort on detach (htmx completes
such requests by design, `:4585-4597`); a global discovery
`MutationObserver` (htmx core has none — the proposed "parity" fix is a
parity break); the zombie-queue-replay claim (already covered by the
`:4285-4289` entry check pinned in an earlier revision).
