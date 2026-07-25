---
paths:
  - "packages/admin/**"
  - "packages/admin-block-editor/**"
  - "packages/*/src/Admin/**"
  - "packages/*/src/Editor/**"
  - "packages/*/src/templates/**"
---

# Admin UI (EasyAdmin + EditorJS)

- **Header buttons go in `{% block page_actions %}`**, not `{% block actions %}`. In
  templates extending `@EasyAdmin/page/content.html.twig`, a wrongly-named block is
  silently dropped: no error, route still 200, buttons simply absent. Assert on rendered
  HTML, never on status alone.
- **EditorJS tools are registered at build time, not runtime.** A tool's `.ts` class must
  live in `packages/admin-block-editor/src/assets/tools/<Name>/` and be imported into the
  `editorjsTools` map in `editor.ts`, then rebuilt with `yarn build` in that package —
  the committed `dist` is what ships. A package activates its tool by shipping a PHP
  `EditorJsToolProviderInterface` implementation (tagged
  `pushword.editorjs_tool_provider`), which `AppExtension::editorjs_extra_tools` merges
  into the widget template.
- **Do not use form themes for editor wiring.** EasyAdmin's per-CRUD `addFormTheme` makes
  form-theme overrides lose the precedence fight; the Twig-function merge above is the
  supported seam.
- **Rebuild core assets after editing js-helper.** `packages/core/src/Resources/public/app.js`
  is compiled — run `yarn build` in `packages/core` after touching `helpers.js`.
- **`pw_auth=1` is a ROLE_EDITOR-only hint.** Cookie set, heal, and 403-clear paths must
  all keep the same role gate; a mismatch makes customer sessions dead-POST the admin
  fragment on every page view. The `admin_buttons` block is wrapped in
  `{% if not isStatic|default(false) %}` so static export omits an endpoint that is
  unreachable behind the static host's allow-list.
- **`liveBlock()` must drop `data-live` on a failed fetch** — it re-runs on every
  DOMChanged, so a retained attribute means an infinite re-fetch loop.
- **Headerless tables need an empty header row.** CommonMark only renders a table when a
  delimiter (hence a header) is present, so a headerless source table is stored as
  `withHeadings=true` with an empty first row; the front's `EmptyTableHeadProcessor` drops
  the all-empty `<thead>` at render. Simple `<table>` HTML converts to a Table block;
  complex tables (colspan/rowspan, nested, block-level cells, non-rectangular) stay Raw.
- Changing render output means bumping `MarkdownParser::CACHE_VERSION`.
