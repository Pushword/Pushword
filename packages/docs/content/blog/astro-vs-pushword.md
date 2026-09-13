---
title: 'Astro vs Pushword - A Build Tool and a CMS Meeting in the Middle'
h1: 'Astro & Pushword: Two Answers to the Content-Driven Web'
publishedAt: '2026-08-02 10:00'
parentPage: blog
template: /page/blog.html.twig
toc: true
---

Astro is one of the best things to happen to the web in the last five years, and this
comparison starts by saying so plainly. It made "ship less JavaScript" a mainstream
position at a moment when the industry was heading the other way, and it did it without
lecturing anyone. If you are choosing between Astro and Pushword, you are choosing between
two good options, not between a right one and a wrong one.

This page exists because the two projects keep arriving at the same conclusions from
opposite starting points. So which one should you choose for your next project, and why?

## The short answer

Most of this page is nuance. The decision usually is not, and one question settles it:
**will anyone other than a developer ever need to publish?**

**If no** — content is written by developers, in the repository, and that will not change —
pick Astro and stop reading. It is mature, excellently designed, and has an ecosystem
Pushword cannot match. Its single authoring surface is simpler than anything here, and
simpler wins.

**If yes, Pushword is the better default for a team that wants the editor built in**,
because the comparison becomes **Astro plus an editing system** against one integrated
CMS. That second system may be free or paid, self-hosted or managed; either way, someone
must integrate its publishing and preview workflow.
[Comparing like for like](#comparing-like-for-like) prices that out properly.

These do not gate the decision — each one simply widens the gap, and teams that have one
usually have several:

- Your team already writes PHP or Symfony
- You run several sites or locales that share templates and code
- Editors expect a change to be live seconds after saving, not after a CI run
- AI agents write or maintain part of your content
- You want content and code in your own git, with no content vendor in the path

---

## Quick overview

|                             | Astro                                                             | Pushword                                                                       |
| --------------------------- | ----------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| **What it is**              | A build tool for content-driven websites                          | A complete CMS built on Symfony bundles                                        |
| **Language**                | JavaScript / TypeScript (Node 22.12+)                             | PHP 8.4+ / Symfony 8                                                           |
| **Best for**                | Teams who live in the JS ecosystem and author content in the repo | Teams who need editors, multi-site, or AI agents in the content loop           |
| **Content authored by**     | Developers, in the repo (or an external CMS via a loader)         | Developers, editors, and AI agents — all three at once                         |
| **To run an editable site** | Astro **plus** an editor/CMS integration                          | Admin included in one install                                                  |
| **First public release**    | 2021                                                              | December 2020                                                                  |
| **Current version**         | [Astro 7.3.2](https://github.com/withastro/astro/releases/tag/astro%407.3.2), September 2026 | Pushword 1.0, stable since September 2026                      |
| **Track record**            | 7 majors since August 2022; fast iteration                        | 25 packages (18 bundles), over 3,000 test methods; production use since 2020  |
| **Ecosystem**               | Large integration and developer community                         | Smaller project built on Symfony, Doctrine and Twig                            |
| **Licence & hosting**       | MIT; deploy to supported hosts; CMS terms vary                    | MIT, self-hosted, no hosted content vendor required                            |

Astro's ecosystem advantage is real and worth weighing. Hundreds of integrations, a large
community, extensive documentation, and now a well-resourced corporate steward committed
to keeping it open source alongside an Ecosystem Fund backed by Webflow, Netlify, Wix and
Sentry.

Two counterweights, though, because "smaller" is often misread as "newer" or "unproven".

**Pushword is not new.** Its first release on Packagist predates Astro's public launch,
and it has shipped continuously since. It now has 25 packages (18 Symfony bundles) and
over 3,000 test methods. It is small in _audience_, not in age.

**Nor is the stack under it small.** Symfony, Doctrine and Twig have extensive
documentation and large developer communities. Pushword 1.0 uses Symfony 8, not a
Symfony LTS branch; check [Symfony's support schedule](https://symfony.com/releases)
when planning upgrades. Symfony experience helps, though developers must still learn
Pushword's own content and extension model.

### "So why haven't I heard of it?"

A fair question, and the honest answer is not flattering to our marketing: Pushword was
built to run its authors' own client sites, and it has been doing that since 2020. There
was never a launch campaign, a funding round, a conference track or a growth team. It grew
features when real sites needed them and stopped when they did not.

That is worth knowing in both directions. It means a much smaller community, fewer
tutorials, and nobody but us to call — a real cost, and the main reason to choose Astro
instead. It also means the roadmap has never been driven by adoption targets, the project
has no investor who needs a return from it, and every feature described on this page is in
production somewhere rather than in a demo.

---

## The structural difference

Not PHP versus JavaScript, and not static versus dynamic. Both projects ship static HTML
and both let you go dynamic when you need to. The real difference is **who owns the
content and when the site is assembled**.

**Astro is a web framework that renders content it is given.** It has no built-in CMS
database or admin interface. A static project can keep Markdown or MDX in the repository:
change a word, commit, rebuild and deploy. [Live content collections](https://docs.astro.build/en/guides/content-collections/#live-content-collections)
and [on-demand rendering](https://docs.astro.build/en/guides/on-demand-rendering/) can
instead fetch content at request time without rebuilding the whole site.
Both are deliberate workflows; teams should choose which one they need.

**Pushword is a CMS that can go static.** There is a database, an admin UI, and an editor
who has never seen a terminal. Content is served from SQLite, PostgreSQL or MariaDB and
can be synchronized with Markdown files through the Flat extension. An editor saves a
page and the page cache can re-render it. The files can live in the same git repository
as the code, or in a separate content repository. Flat reconciles changes in either
direction; the files are not unconditionally the source of truth.

The consequence worth internalising:

|                                   | Astro                     | Pushword                                           |
| --------------------------------- | ------------------------- | -------------------------------------------------- |
| Changing one word on one page     | Static route: rebuild and deploy; live route: fetch at request time | The cached page can re-render |
| Reviewing a content change        | A pull request, natively  | A git diff on the exported file                    |
| Rolling back content              | Depends on content source | `git revert`, re-import — or the version extension |
| Onboarding a non-technical editor | Add a headless CMS        | Already done                                       |

For a static Astro route, the editor waits for commit → CI queue → build → deploy.
That is a good trade when content changes are reviewed in git; it can feel slow for small
corrections. A live route removes that rebuild, but needs a server adapter, content source
and cache policy. Pushword includes the editing and rendering workflow in the CMS.

---

## Three authoring surfaces

This is where the projects diverge most, and it is the honest reason to pick one over the
other.

Astro itself ships no editorial admin; repository-based authoring uses a text editor and
commit access. When a project needs more, the ecosystem's answer is to pair Astro with a
CMS —
**Storyblok** has the most mature Astro integration and a visual editor that renders the
live site; **Sanity**, **Contentful** and **Strapi** are all common; **Decap** and
**TinaCMS** sit at the git-based end. These are good products and the integrations are
well-trodden. It does mean another integration and often another set of credentials, but
not necessarily a second bill.

Pushword has three surfaces writing the same content through different doors:

- **Developers**, editing Markdown files in the repository
- **Editors**, in the admin UI, who never touch git
- **AI agents**, through the [REST API](/extension/api) or by writing flat files directly

The [flat](/extension/flat) package syncs file and database edits. An agent can rewrite a
page's frontmatter, an editor can fix a typo in the admin, and a developer can restructure
the content directory. Simultaneous edits can still conflict; Flat records a losing
version for review rather than making conflicts impossible.

If developers are the only people who will ever touch the content, Astro's single surface
is simpler, and that simplicity is worth having.

---

## Comparing like for like

Astro against Pushword is not quite the right comparison. Astro is a rendering framework,
and it is excellent at that. But when someone other than a developer needs to publish,
compare **Astro plus an editing system** with **Pushword on its own**.

That changes the arithmetic:

|                                    | Astro + editing system                            | Pushword                                        |
| ---------------------------------- | ------------------------------------------------- | ----------------------------------------------- |
| Systems to run, upgrade and secure | Two                                               | One                                             |
| Where content lives                | Git or the CMS's store; loaded at build or request time | Your database, optionally synced to Markdown in git |
| Cost as the team grows             | Per-seat / per-API-call on the commercial options | Server cost                                     |
| Editor preview                     | Depends on the editor/CMS integration             | The site itself                                 |
| Publish → visible                  | Static: rebuild/deploy; live: next request or cache refresh | The page cache re-renders               |
| If a hosted vendor changes terms   | Export and migrate; self-hosted options differ    | No hosted content vendor required               |

To be fair to the alternatives: **Decap** and **TinaCMS** can store content in git and
avoid a hosted content vendor, though they still need integration. Storyblok, Sanity and
Contentful have free tiers and paid plans; compare their limits and terms with your actual
publishing needs.

None of this makes the pairing a bad choice. Plenty of teams run it happily and the
integrations are mature. It is simply the comparison that should be made, because "Astro
is free and open source" and "Pushword is free and open source" describe two different
scopes of work.

---

## Where the two overlap more than expected

Both projects independently landed on most of the same answers, which is usually a sign
the answers are right.

| Idea                                                | Astro                                                            | Pushword                                                         |
| --------------------------------------------------- | ---------------------------------------------------------------- | ---------------------------------------------------------------- |
| Zero JavaScript by default                          | Islands architecture — static HTML, isolated interactive pockets | Server-rendered Twig; JS is opt-in per component                 |
| Markdown + frontmatter as content                   | Content collections                                              | Flat files, same shape                                           |
| Static output                                       | `astro build`                                                    | [`pw:static`](/extension/static-generator)                       |
| Personalised fragments in an otherwise static page  | Server islands (`server:defer`)                                  | `data-live` / `liveBlock` ([page-cache](/extension/page-cache))  |
| Typed, reusable components with declared parameters | Astro components with typed props                                | [Snippet](/extension/snippet) components with a parameter schema |
| Animated navigation                                 | `<ClientRouter />`                                               | Native [View Transitions](/view-transitions)                     |

The server-islands convergence is the nicest one. Astro's `server:defer` renders a
placeholder into the static HTML and fills it from the server afterwards, so one
personalised widget does not make the whole page uncacheable. Pushword's `data-live` does
the same job for the same reason — the admin toolbar on a statically cached page is fetched
client-side, gated on a cookie, so the cached HTML stays identical for every visitor.

---

## What Pushword learned from Astro

Three features shipped in Pushword in 2026 were designed after reading how Astro handles
the same problem. Two of them are Astro's idea and it would be dishonest to present them
otherwise.

### Schema-validated content properties

Astro's content collections let you declare a Zod schema per collection: frontmatter is
validated at build time, with real error messages and TypeScript autocomplete. It is a
genuinely excellent piece of design and it has been in Astro since 2023.

Pushword's [declared page properties](/page-properties) are the same idea, expressed in
Symfony's idiom rather than Zod's:

```yaml
pushword:
  apps:
    - hosts: [example.com]
      page_properties:
        level:
          { type: string, constraints: [{ Choice: { choices: [Débutant, Initié] } }] }
        price_from: { type: int, constraints: [{ Positive: ~ }] }
```

Constraints are Symfony validator constraints, schemas are checked at container compile
time, and `pw:schema:dump` exposes the model to AI agents and to `/api/docs`.

Two deliberate differences follow from Pushword being a CMS rather than a build tool.
Undeclared keys **pass through untouched** — declaring a schema is opt-in, per property,
and never breaks a site that has none. And the flat import **reports rather than blocks**:
a build tool can fail a build on invalid frontmatter, but a CMS whose import blocks on one
malformed legacy file has just taken the whole site hostage. Astro's stricter default is
correct for Astro; ours is correct for us.

### View transitions

Astro shipped animated navigation years before it was possible without JavaScript, via
`<ClientRouter />`, which intercepts navigation and simulates SPA routing. That was the
only way to do it at the time, and it also preserves client state across navigations —
something the native API does not do.

Pushword ships [View Transitions](/view-transitions) using the native CSS API:

```css
@view-transition {
  navigation: auto;
}
```

No router, no JavaScript, no build step. This is not cleverness on our part — it is
timing. The native cross-document API only reached Chrome 126+ and Safari 18.2+ recently,
and Firefox has not enabled it by default yet. Astro built theirs when the platform had
nothing to offer, and their version still does things ours cannot.

### Cache invalidation

Here the influence was inverted: we looked at Astro's approach, found it did not solve our
problem, and that clarified the design.

Astro's prerendered routes are rebuilt for new static output. Its live collections and
on-demand routes can instead serve updated content without a full-site rebuild. Astro 7
also stabilised [route caching](https://docs.astro.build/en/guides/caching/) for on-demand
responses, with providers for supported deployments. This is a different division of
labour from Pushword's page cache, not an absence of incremental publishing.

Pushword needed something else, because a page save must not trigger a full-site render.
Each host now carries a **render epoch** — an opaque token bumped by any change that can
affect other pages' output: a snippet edit, a media edit, a template save, a published
review, or a page edit that adds or removes an internal link. Pages are stamped with the
epoch they rendered under, and a mismatch means stale. Sweeps are debounced and
incremental, and a re-render whose HTML is byte-identical skips the write entirely. See
[page-cache](/extension/page-cache) for the full model.

Pushword controls the CMS write path and PHP rendering services, so it can invalidate
cached pages when content changes. Astro can use request-time rendering and caching when
the content source lives outside its build.

---

## Where Pushword is the better choice

- **Anyone other than a developer publishes.** You get an admin UI, media management,
  versioning and publication holds in the box, without integrating a separate editor.
- **You want the whole site in one deliverable.** Admin, media with image variants,
  [versioning](/extension/version), [search](/extension/search),
  [forms](/extension/conversation), [newsletter](/extension/newsletter),
  [redirections](/extension/flat), [dead-link scanning](/extension/page-scanner),
  [REST API](/extension/api) and static export ship as maintained bundles of one project,
  tested together. Assembling the same set from integrations and SaaS is work you do once
  and then maintain forever.
- **Publishing should be direct from the CMS.** An editor saves without a commit or CI
  deployment. Astro can also publish without a rebuild when configured with live content;
  the difference is whether that workflow ships as part of the CMS.
- **You run a fleet.** One Pushword installation serves many hosts and locales with one
  admin. Astro also supports multiple locales and locale-specific domains in one project;
  compare administration and deployment needs, not project count alone.
- **AI agents are in your content loop.** Both projects now court agents — Astro 7 added
  agent detection, a background dev server and JSON logging, and Pushword has
  [agent-optimized command output](/agent-output). Astro agents can write files or use
  whatever API its content source provides. Pushword adds a documented
  [REST API](/extension/api), the flat-file round trip and `pw:schema:dump` for reading
  the content model and writing through the CMS.
- **You want no hosted content vendor.** Pushword is MIT-licensed and self-hosted; Flat
  can keep a Markdown copy in your git alongside a database you control.
- **Your team writes PHP.** Symfony, Doctrine and Twig are the whole stack, with no Node
  build step in the critical path.

---

## Where Astro is the better choice

Said plainly, because it is often true:

- **Your team is JavaScript-native.** Fighting a PHP stack to avoid a rebuild step is a bad
  trade. Astro's DX for a JS team is excellent and Pushword's is irrelevant to them.
- **You want React, Svelte or Vue components on the page.** Astro's islands run all of
  them, side by side, hydrating independently. Pushword has no equivalent and is not
  trying to build one.
- **Content is authored only by developers**, and a pull request is the review workflow you
  want. Astro's model is simpler, and simpler wins.
- **You need the ecosystem.** Hundreds of integrations, and answers to most questions
  already written down somewhere.
- **You are deploying to the edge.** Astro's dev server runs the real target runtime
  (Workers, Deno, Bun) in development, so "works in dev, breaks in prod" largely goes away,
  and the Cloudflare relationship will only deepen that.
- **You want a fast-moving project.** Astro ships majors quickly and the performance work is
  real — v7's Rust compiler and native Markdown pipeline cut build times 15–61%. If you
  like being on a tool that improves that fast, that is a genuine draw.
- **You want the reassurance of a large, corporate-backed project.** Pushword is small, and
  the honest mitigation is that it is a thin layer over Symfony rather than a stack of its
  own.

---

## Three ways to survive a decade: WordPress, Astro, Pushword

A question worth asking of any platform: what does maintenance feel like in year three?
WordPress, Astro and Pushword answer it differently, and none of the three answers is
wrong — they are different bets about where complexity should live.

|                          | WordPress                                      | Astro                                        | Pushword                                    |
| ------------------------ | ---------------------------------------------- | --------------------------------------------- | -------------------------------------------- |
| Extensions come from     | [71,000+ free plugins](https://wordpress.org/plugins/) | Official and community integrations | 18 bundles in 25 packages |
| Installed by             | Click-install in admin, often auto-updating    | `package.json`, reviewed in a pull request    | `composer require`, versioned together       |
| Extensions run           | At runtime or in the admin                     | At build time or request time                 | At runtime, shipped and tested as a set      |
| A broken extension means | A failed request, admin action or update       | A failed build or runtime feature             | A failed test, deploy or runtime feature     |
| Core breaking changes    | Rare                                          | 7 majors since August 2022                    | Stable `1.x`, using Symfony 8                |
| Therefore the risk is    | **Entropy** — nothing forces you to update     | **Churn** — you cannot stand still            | **Concentration** — a small team, less choice |

### WordPress: entropy

WordPress's plugin ecosystem is unmatched in breadth, and its refusal to break backward
compatibility is a real service to millions of sites. But those two facts produce the
familiar problem together: because nothing forces an update, plugins accumulate, each
hooking the same runtime and writing to the same tables. Conflicts appear in production,
and abandoned plugins are risky both to keep and to remove.

### Astro: churn, but loud and early

Astro integrations are commonly pinned in `package.json` and reviewed in pull requests.
Build-time failures can be caught in CI, a useful difference from a runtime plugin failure.
But on-demand routes and integrations can fail at request time too; Astro is not immune to
runtime dependency risk.

The cost sits elsewhere. Astro has shipped seven majors since August 2022, with v6 in March
2026 and v7 three months later, and provides security fixes for only one previous major. v7
replaced the remark/rehype Markdown pipeline with a native one and made a stricter compiler
the default, so previously-building invalid HTML now errors — and community integrations
built on remark plugins need attention. Even first-party packages retire: `@astrojs/db` is
gone as of v7.

It is also worth reading "hundreds of integrations" precisely. Astro maintains around
fourteen; the public directory auto-populates each week from npm keyword tags, so listing
is not curation, endorsement, or a maintenance promise. Community integrations need the
same diligence any npm dependency does.

None of this is a flaw so much as a trade: rapid iteration is exactly why Astro keeps
getting faster and better. It means maintenance is *scheduled* rather than deferred, which
most teams should prefer — as long as they budget for it.

### Pushword: concentration

Pushword's 18 Symfony bundles in 25 packages are versioned, released and tested together,
so "does the newsletter package work with this version of the admin?" is a question CI
checks. Its Symfony 8 dependency is a standard-support branch, not an LTS guarantee;
upgrading that dependency is part of the maintenance plan.

The honest cost is the mirror image: far less choice than either alternative, a much
smaller community, and a bus factor that is not a large company. If a capability is missing,
you write it — which is fine when the missing pieces are small, and a real problem when they
are not.

### Reading the table for your own project

If you will genuinely leave a site alone for three years and only want it to keep working,
WordPress's compatibility promise is worth more than it gets credit for. If you have a team
that upgrades deliberately and wants the fastest tooling available, Astro's churn is a fair
price. If you want a small, fixed surface that one team can hold in its head, Pushword's
concentration is the point rather than a limitation.

---

## What it costs to be wrong

Adopting a smaller project should include an exit-cost assessment. Here are the parts of
Pushword that make an exit possible, and the parts that still require work:

- **Content** can be synced to Markdown with YAML frontmatter in your git using Flat.
  Astro can read those files, but routes, relations, custom properties and templates
  still need mapping.
- **The database** is a SQLite file you own, or your own PostgreSQL/MariaDB server. There is no hosted service
  holding anything, no API key, no export request, no egress bill.
- **Templates** are Twig, and the application uses Symfony. A Symfony developer has a
  familiar starting point, but still needs to learn Pushword-specific behavior.
- **Media** are ordinary files on disk in a directory you control.
- **The licence** is MIT, and the whole monorepo is public. If the project stopped
  tomorrow, you could maintain it, but would own its security fixes and upgrades.

Compare that with the exit cost of whichever Astro editing system you would actually use:
a hosted CMS, a self-hosted CMS or a git-backed editor have different risks.

The reasonable way to test the claim is to spend an afternoon on it and put a real page
through the part that matters most to you — the admin UI, the flat-file round trip, or the
API:

```shell
composer create-project pushword/new pushword "^1.0"
```

---

## Choosing

The questions that actually decide it:

1. **Who publishes?** Only developers, forever → Astro. Anyone else → Pushword, or Astro
   plus a headless CMS. Price both before deciding.
2. **What does your team already know?** This outweighs almost every technical argument
   under it. A JS team should not adopt Symfony to avoid a build step, and a PHP team
   should not adopt Node to acquire one.
3. **How many systems do you want to own in three years?** One that does everything, or a
   renderer plus a content service chosen and maintained separately.
4. **Do you need React, Svelte or Vue components?** Astro, without hesitation.
5. **How fast must a published change appear?** Pushword publishes from its admin; Astro
   can serve live CMS content without a rebuild or deploy static changes after CI.
6. **One site or a fleet?** Both can share code and locales; Pushword includes one admin
   across hosts, while Astro's publishing setup depends on the editor/CMS integration.

If you would rather pair Astro with a CMS than adopt one, that is a legitimate answer and
the integrations are mature. If you would rather have the CMS built in — and optionally
sync content to Markdown on a disk you own — that is what Pushword is for.

---

## Resources

- **Astro**: [astro.build](https://astro.build) · [docs](https://docs.astro.build) · [github.com/withastro/astro](https://github.com/withastro/astro)
- **Pushword**: [pushword.piedweb.com](https://pushword.piedweb.com) · [github.com/Pushword/Pushword](https://github.com/Pushword/Pushword)
- Related: [CMS comparison — Pushword vs WordPress, Statamic, Sulu](/blog/cms-comparison)

> [!note] About this comparison
>
> Written by the Pushword author (and Claude). We are obviously not neutral, and Astro is
> a far more widely adopted project with a much larger community than Pushword. Claims
> about Astro are based on its official documentation and release notes as of September 2026;
> claims about Pushword are based on shipped features, not roadmap, and its package and
> test figures are checkable in this repository. Where Pushword adopted an idea
> from Astro, we have said so.
>
> Found an error, or think we have been unfair to Astro? [Open an issue](https://github.com/Pushword/Pushword/issues) — corrections are welcome.

> [!warning] Version
>
> Last updated: September 2026. Reflects Astro 7.3.2 and Pushword's render epoch,
> declared page properties and view transitions. Both projects move quickly; updates
> welcome via GitHub issues.
