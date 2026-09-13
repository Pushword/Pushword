---
title: 'Best Rust CMS in 2026 - Pure Rust, Wasm, and Hybrid Options'
h1: 'Best Rust CMS in 2026: Pure Rust, Wasm, or Hybrid?'
publishedAt: '2026-09-13 00:00'
parentPage: blog
template: /page/blog.html.twig
toc: true
---

The best Rust CMS depends on what you want Rust to do. A headless API
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
is a Markdown site on Spin. For a Git-authored static site, compare the Rust
generator [Zola](https://www.getzola.org/documentation/getting-started/overview/)
with [Hugo](https://gohugo.io/documentation/), which is written in **Go**, not
Rust. Neither is a multi-user editorial CMS on its own.

This is Pushword's blog, so our interest in the first option is obvious. We
checked the projects' own documentation in September 2026; this is a guide to
their stated architecture and current scope, **not** an independent benchmark
or a certification of production readiness.

There is no fair single speed ranking across these products: they do not run
the same workload, and their public documentation does not provide a controlled
head-to-head test.

## Feature differences that change the decision

| Product | How content is edited and stored | What visitors receive | Product boundary |
| --- | --- | --- | --- |
| [Pushword](/extension/admin) | Browser admin; database, with optional [flat-file sync](/extension/flat) | Dynamic pages or [static export](/extension/static-generator) | Editing, users, media and an optional [write API](/extension/api) live in the CMS; Rust workers accelerate selected operations |
| [NUR CMS](https://github.com/jb-alvarado/nur-cms) | Vue admin; PostgreSQL | Rust REST API in Markdown, HTML or AST form | Headless content and media; the public website is yours to build |
| [RaisFast](https://github.com/RaisFast/raisfast) | Embedded React admin; SQLite, PostgreSQL or MySQL | Rust API and built-in blog/backend modules | Broadest claimed backend scope here, with an explicit early-alpha warning |
| [Bartholomew](https://developer.fermyon.com/bartholomew/index) | Markdown files with TOML headers and a CLI | Pages rendered by a Wasm component on Spin | Templates and content serving; no conventional multi-user admin |
| [Zola](https://www.getzola.org/documentation/getting-started/overview/) | Markdown files in a site repository | Static files produced by a Rust build | Site generation, not a content-editing service |
| [Hugo](https://gohugo.io/documentation/) | Content files in a site repository | Static files produced by a Go build | Site generation, not a content-editing service or a Rust project |

**Editing and publication.** An admin form is a practical difference, not a
cosmetic one. Pushword exposes page and media editing in its
[admin](/extension/admin), a [token-authenticated REST API](/extension/api),
and optional [page history](/extension/version). NUR documents a Vue admin and
REST output; RaisFast lists an embedded admin, roles, and workflow modules, but
still calls itself early alpha. By contrast, Hugo has
[draft, publication and expiry dates](https://gohugo.io/content-management/front-matter/)
in front matter, and Zola has a
[draft flag](https://www.getzola.org/documentation/content/page/). These
control what a *build* includes. Neither ships integrated editor accounts,
approvals, or deployment workflows. Bartholomew also supports unpublished
content and a documented
[preview mode](https://developer.fermyon.com/bartholomew/contributing-bartholomew),
but its documented editing path is Markdown files and the `bart` CLI.

**Content model and presentation.** NUR is explicitly headless: its
[README](https://github.com/jb-alvarado/nur-cms) lists Markdown, rendered HTML,
and AST output, so a separate frontend decides how to display it. Pushword
renders through Twig but can also expose content through its API. RaisFast
advertises dynamic schemas and automatic CRUD APIs; validate the particular
schema and permissions you need before treating those claims as equivalent to
an established editorial workflow. Hugo's
[content types](https://gohugo.io/content-management/types/),
[taxonomies](https://gohugo.io/content-management/taxonomies/), and
[page bundles](https://gohugo.io/content-management/page-bundles/) organize
files at build time. Its "headless bundles" expose unpublished content and
resources to templates, **not a live headless CMS API**; a JSON output format is
still generated during the build. Zola likewise offers
[sections and taxonomies](https://www.getzola.org/documentation/content/taxonomies/)
for static pages. Bartholomew renders Markdown with Handlebars on requests to
Spin, a different delivery model from either generator.

**Media, languages, and extension points.** Hugo can transform
[page-bundle images](https://gohugo.io/content-management/image-processing/),
build [multilingual sites](https://gohugo.io/content-management/multilingual/),
and compose [modules](https://gohugo.io/hugo-modules/use-modules/) and an
[asset pipeline](https://gohugo.io/hugo-pipes/). Zola has
[image resizing](https://www.getzola.org/documentation/content/image-processing/),
[multilingual content](https://www.getzola.org/documentation/content/multilingual/),
Sass and a [build-time search index](https://www.getzola.org/documentation/content/search/),
though the search interface is left to the site. Those are substantial site
features: calling either generator "just Markdown" understates it. NUR's
documented media upload/processing and Wasmtime plugins belong to a running
CMS, while RaisFast advertises several plugin engines. Pushword's media library
and Symfony bundles address the same needs through its PHP application. The
overlap in feature names does not erase the difference between generating an
asset during a build and letting an editor upload it in an authenticated admin.

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
CMS. Both native test suites run in CI and compare supported results with PHP.
Pushword's published timings measure particular content operations; they are
not a promise that every public request or whole site becomes several times
faster.

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

[AvoRed Rust CMS](https://github.com/avored/avored-rust-cms) describes Axum,
SurrealDB, and a React admin in its README, but
says many admin pages are still being redone and lists REST and GraphQL APIs on
the roadmap. On that evidence, we would validate the exact API and admin
features needed before selecting it as a headless production CMS.

## Zola and Hugo: the static-site alternatives

[Zola](https://www.getzola.org/documentation/getting-started/overview/) is
written in Rust and uses Tera templates. It is a strong fit for a code-owned
blog or documentation site: Markdown, taxonomies, image resizing, multilingual
content, and a search index can all be produced by the build. Its
[draft flag](https://www.getzola.org/documentation/content/page/) keeps pages
out of normal builds, but it does not approve a draft or deploy the result.

[Hugo](https://gohugo.io/documentation/) is a **Go** static site generator. It
builds from content files and gives developers a broader
set of built-in composition tools: content archetypes, page bundles, custom
taxonomies, shortcodes, multilingual configurations, image transformations,
modules, and the Hugo Pipes asset pipeline. Its
[front matter](https://gohugo.io/content-management/front-matter/) handles
drafts and future or expired pages. These are real advantages if the team wants
to assemble a complex static site without operating an application server.
Hugo is not a Rust CMS, however, and its build-time JSON output or "headless"
page bundle should not be mistaken for an authenticated content API.

Both generators shift publication to the repository and deployment pipeline.
Adding a Git-backed editor such as [Decap CMS](https://decapcms.org/docs/) is
possible, but authentication, preview, review policy and build deployment then
span more than one product. A simple site may benefit from that separation; a
busy editorial team should test the complete workflow, not the generator alone.

## How to choose

Start with a representative page and one real publishing task. Have a developer
model content, an editor revise it, and an operator deploy and restore it. Then
measure the operation that is actually slow: uncached Markdown conversion,
media processing, publication, or public requests. A Rust implementation of one
component does not by itself establish a faster whole-site experience.

If your priority is **all-Rust ownership**, start with NUR for headless content,
RaisFast for a broad backend pilot, or Bartholomew for Spin. If you only need a
**Git-authored static site**, compare Rust-based Zola with Go-based Hugo on the
site features above; Hugo belongs in that decision even though it does not
qualify as a Rust CMS. If your priority is **an editorial CMS with optional
native acceleration**, start with Pushword.

## Sources

The feature descriptions above come from project documentation and repositories,
not a hands-on acceptance test of every editing flow.

- Pushword: [admin](/extension/admin), [API](/extension/api),
  [static generator](/extension/static-generator), and [native acceleration](/native-acceleration).
- NUR CMS: [project README](https://github.com/jb-alvarado/nur-cms).
- RaisFast: [project README and alpha notice](https://github.com/RaisFast/raisfast).
- Bartholomew: [Fermyon overview](https://developer.fermyon.com/bartholomew/index)
  and [quickstart](https://developer.fermyon.com/bartholomew/quickstart).
- Zola: [overview](https://www.getzola.org/documentation/getting-started/overview/)
  and [content documentation](https://www.getzola.org/documentation/content/overview/).
- Hugo: [documentation](https://gohugo.io/documentation/),
  [front matter](https://gohugo.io/content-management/front-matter/), and
  [image processing](https://gohugo.io/content-management/image-processing/).
