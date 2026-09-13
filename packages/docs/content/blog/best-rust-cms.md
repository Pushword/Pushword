---
title: 'Best Rust CMS in 2026 - Pure Rust, Wasm, and Hybrid Options'
h1: 'Best Rust CMS in 2026: Pure Rust, Wasm, or Hybrid?'
publishedAt: '2026-09-13 00:00'
parentPage: blog
template: /page/blog.html.twig
toc: true
---

What is the best Rust CMS? First decide what you want Rust to do. A headless API
written in Rust, a Markdown site running on WebAssembly, and a PHP CMS
that uses Rust for selected expensive operations solve different problems. The
language alone does not tell you who can edit, how a draft becomes public, or
what you must operate in production.

**Our short answer:** choose [Pushword](/native-acceleration) when you need a
full PHP/Symfony CMS with optional Rust acceleration; evaluate
[NUR CMS](https://github.com/jb-alvarado/nur-cms) for a Rust-first headless CMS;
try [RaisFast](https://github.com/RaisFast/raisfast) if you want an ambitious
single-binary backend and can accept an early alpha; and choose
[Bartholomew](https://developer.fermyon.com/bartholomew/index) when the target
is a Markdown site on Spin.

This is Pushword's blog, so our interest in the first option is obvious. We
checked the projects' own documentation in September 2026; this is a guide to
their stated architecture and current scope, **not** an independent benchmark
or a certification of production readiness.

## The options at a glance

- **[Pushword](/native-acceleration):** a PHP 8.5 / Symfony 8 CMS for editors
  and developers, with optional Rust workers.
- **[NUR CMS](https://github.com/jb-alvarado/nur-cms):** a Rust headless API
  with PostgreSQL and a Vue admin; bring your own site frontend.
- **[RaisFast](https://github.com/RaisFast/raisfast):** an all-in-one Rust
  backend with an embedded admin; its README labels it **early alpha**.
- **[Bartholomew](https://developer.fermyon.com/bartholomew/index):** a
  Markdown micro-CMS compiled to Wasm for Spin, without a conventional admin.
- **[My Rust CMS](https://github.com/space-bacon/my_rust_cms):** a Rust and
  Yew/Wasm visual builder whose extensive claims should be tested firsthand.
- **[derived-cms](https://docs.rs/crate/derived-cms/latest):** a code-first
  crate that generates CMS interfaces from Rust types, not an editorial app.

There is no fair single speed ranking across these products: they do not run
the same workload, and their public documentation does not provide a controlled
head-to-head test.

## Pushword: a CMS with optional Rust on the hot path

Pushword remains a [PHP 8.5 and Symfony 8](https://github.com/Pushword/Pushword)
CMS. It has an admin, [flat-file editing](/extension/flat), and an
[API for agents and other clients](/extension/api). Its Rust integrations are
separate, opt-in executables: the content worker can analyze rendered content
and convert eligible Markdown blocks, while the static-generator package can
minify HTML. PHP still owns routing, permissions, Twig, publication, and the
fallback for native work that is unavailable or declined. Composer does not
download a Rust binary. [The architecture and setup are documented here](/native-acceleration).

This is useful when the team wants a CMS now, already knows PHP, or deploys to
hosting where a native executable may not be possible. It is **not** a pure Rust
CMS. The adapters require a build and parity check on the target platform
before use. Pushword's published timings measure particular
content operations; they are not a promise that every public request or whole
site becomes several times faster.

## NUR CMS: Rust-first headless content

[NUR CMS](https://github.com/jb-alvarado/nur-cms) combines an Axum backend,
PostgreSQL via SQLx, and a Vue 3 admin. Its README describes Markdown, HTML, and
AST output, media processing, localization, REST endpoints, and Wasmtime-based
plugins. The maintainer also [describes using it as a library](https://users.rust-lang.org/t/nur-cms-a-rust-headless-cms/140867)
inside another Rust project.

That makes NUR the clearest fit here when you want content served through a
Rust API and are happy to build the presentation layer separately. Evaluate its
editing and deployment workflow with your own schema before adopting it; a
feature list cannot tell you whether its admin fits your editors.

## RaisFast: broad scope in an early alpha

[RaisFast](https://github.com/RaisFast/raisfast) describes a Rust/Axum backend
with an embedded React admin, dynamic content types, search, e-commerce, jobs,
and JavaScript, Rhai, Lua, and Wasm plugin engines. Its README describes a
single-binary deployment and support for SQLite, PostgreSQL, and MySQL. It also
explicitly says **Early Alpha** and warns that the API may change before 1.0.

If your goal is an all-in-one backend rather than only a website CMS, that
scope is interesting. Treat the feature list as a starting point for a pilot,
not as proof that every module is ready for your production workload. Check the
specific path you will use: content modeling, admin permissions, upgrades, and
backup/restore matter more than the number of built-in modules.

## Bartholomew: a micro-CMS for Spin

[Bartholomew](https://developer.fermyon.com/bartholomew/index) is a Rust
component compiled to WebAssembly and run by [Spin](https://github.com/spinframework/spin).
Its content is Markdown with TOML headers, rendered through Handlebars
templates; Rhai can add template functions. That is a coherent choice for a
small, code-owned site already headed for Spin.

It is not a standalone native server or a conventional multi-editor admin.
The deployment platform is part of the product choice. If editors need forms,
media workflows, roles, or live preview, establish how you will provide those
before choosing it on runtime grounds.

## Visual and code-first projects to evaluate

[My Rust CMS](https://github.com/space-bacon/my_rust_cms) is centered on a
visual page builder. Its repository describes a Rust/Axum backend, Yew/Wasm
frontend, PostgreSQL, Docker setup, and drag-and-drop editing. It is the one to
try if visual composition is your deciding requirement. The README calls it
production-ready; we have not independently verified that claim, so test its
editor and deployment path with real content first.

[derived-cms](https://docs.rs/crate/derived-cms/latest) takes the opposite
approach: define Rust entities and generate an admin interface and headless API
from those types. It fits a developer who wants to own the application model in
code. It is a crate to build with, rather than a CMS that an editorial team can
install and use without Rust development.

[AvoRed Rust CMS](https://github.com/avored/avored-rust-cms) is also worth
watching. Its current README describes Axum, SurrealDB, and a React admin, but
says many admin pages are still being redone and lists REST and GraphQL APIs on
the roadmap. On that evidence, we would validate the exact API and admin
features needed before selecting it as a headless production CMS.

## What about Zola?

[Zola](https://www.getzola.org/documentation/) is an excellent Rust **static
site generator**, not an editor or content API. A Git-backed editor such as
[Decap CMS](https://decapcms.org/docs/) can be paired with a static-site
workflow, but that leaves you to configure authentication, preview, and builds
across two products. Choose that route when a repository and a build pipeline
are the editorial system you want; do not count it as a single Rust CMS.

## How to choose

Start with a representative page and one real publishing task. Have a developer
model content, an editor revise it, and an operator deploy and restore it. Then
measure the operation that is actually slow: uncached Markdown conversion,
media processing, publication, or public requests. A Rust implementation of one
component does not by itself establish a faster whole-site experience.

If your priority is **all-Rust ownership**, start with NUR for headless content,
RaisFast for a broad backend pilot, or Bartholomew for Spin. If your priority is
**a working editorial CMS with optional native acceleration**, start with
Pushword. The best Rust CMS is the one whose authoring and deployment model you
would still choose if the language name were removed from the homepage.
