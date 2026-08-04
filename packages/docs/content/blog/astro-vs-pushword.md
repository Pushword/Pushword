---
title: 'Astro vs Pushword - A Build Tool and a CMS Meeting in the Middle'
h1: 'Astro & Pushword: Two Answers to the Content-Driven Web'
publishedAt: '2026-08-02 10:00'
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

Most of this page is nuance. The decision usually is not.

**Pick Astro if** your content is written by developers in the repository, your team lives
in JavaScript, or you want React/Svelte/Vue components on the page. It is a mature,
excellently designed tool with an ecosystem Pushword cannot match, and for that shape of
project it is the better choice.

**Pick Pushword if two or more of these describe you:**

- Someone who is not a developer needs to publish, and you would rather not run a second
  system to let them
- Your team already writes PHP or Symfony
- You run several sites or locales that share templates and code
- Editors expect a change to be live seconds after saving, not after a CI run
- AI agents write or maintain part of your content
- You want content and code in your own git, with no content vendor in the path

If you recognised yourself in that second list, the rest of this page is detail. If you
recognised yourself in the first, Astro is a genuinely great answer and you can stop here.

---

## Quick overview

|                             | Astro                                                             | Pushword                                                                       |
| --------------------------- | ----------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| **What it is**              | A build tool for content-driven websites                          | A complete CMS built on Symfony bundles                                        |
| **Language**                | JavaScript / TypeScript (Node 22+)                                | PHP 8.4+ / Symfony 8                                                           |
| **Best for**                | Teams who live in the JS ecosystem and author content in the repo | Teams who need editors, multi-site, or AI agents in the content loop           |
| **Content authored by**     | Developers, in the repo (or an external CMS via a loader)         | Developers, editors, and AI agents — all three at once                         |
| **To run an editable site** | Astro **plus** a headless CMS — two systems                       | One install                                                                    |
| **First public release**    | 2021                                                              | December 2020                                                                  |
| **Current major**           | Astro 7.0, June 2026; used by Unilever, Visa, NBC News            | 1.0.0-rc, continuous since 2020                                                |
| **Track record**            | 7 majors since August 2022; very fast iteration                   | 800+ releases, 24 bundles, ~2,600 tests; runs its authors' production sites    |
| **Ecosystem**               | Very large; ~61k GitHub stars, ~2.7M weekly npm downloads         | Small itself, on top of Symfony, Doctrine and Twig — a large, LTS-backed stack |
| **Licence & hosting**       | MIT; deploy anywhere, CMS licensed separately                     | MIT, self-hosted, no vendor in the content path                                |

Astro's ecosystem advantage is real and worth weighing. Hundreds of integrations, a large
community, extensive documentation, and now a well-resourced corporate steward committed
to keeping it open source alongside an Ecosystem Fund backed by Webflow, Netlify, Wix and
Sentry.

Two counterweights, though, because "smaller" is often misread as "newer" or "unproven".

**Pushword is not new.** Its first release on Packagist predates Astro's public launch,
and it has shipped continuously since — 800+ tagged releases, 24 bundles, around 2,600
tests running in CI. It is small in _audience_, not in age or in maturity.

**Nor is the stack under it small.** Symfony, Doctrine and Twig have a decade of
documentation, an enormous hiring pool and a published LTS cadence. Most questions you hit
at 2am on a Pushword site are Symfony questions with a well-indexed answer, and a developer
you hire needs Symfony experience, not Pushword experience.

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

**Astro is a build tool that renders content it is given.** It has no database and no
admin interface by design. Content lives in your repository as Markdown or MDX, or it
arrives from somewhere else through a loader. You change a word, you commit, CI builds,
the site deploys. This is a coherent and deliberate design: the whole site is a pure
function of the repository, which makes builds reproducible, reviewable in a pull request,
and trivially rollback-able. Plenty of teams want exactly that and nothing more.

**Pushword is a CMS that can go static.** There is a database, an admin UI, and an editor
who has never seen a terminal. Content is stored in SQLite (or MySQL) and mirrored to
Markdown files with YAML frontmatter — the same shape Astro reads. An editor saves a page
and that one page re-renders. The files can live in the same git repository as the code, or
in a separate content repository; the database is a functional mirror, not the source of
truth.

The consequence worth internalising:

|                                   | Astro                     | Pushword                                           |
| --------------------------------- | ------------------------- | -------------------------------------------------- |
| Changing one word on one page     | Full rebuild, then deploy | That page re-renders                               |
| Reviewing a content change        | A pull request, natively  | A git diff on the exported file                    |
| Rolling back content              | `git revert`, rebuild     | `git revert`, re-import — or the version extension |
| Onboarding a non-technical editor | Add a headless CMS        | Already done                                       |

Astro's full rebuild is not a weakness in itself — it is fast (a 100-post Markdown site
loads its content in roughly 200ms) and it eliminates an entire category of stale-cache
bugs by construction. Worth noting what the editor actually waits for, though: not the
build step, but commit → CI queue → build → deploy, which is minutes rather than
milliseconds. That gap is felt on every typo fix, at any site size, and it grows with the
page count.

---

## Three authoring surfaces

This is where the projects diverge most, and it is the honest reason to pick one over the
other.

Astro has one authoring surface: a developer with a text editor and commit access. When a
project needs more, the ecosystem's answer is to pair Astro with a headless CMS —
**Storyblok** has the most mature Astro integration and a visual editor that renders the
live site; **Sanity**, **Contentful** and **Strapi** are all common; **Decap** and
**TinaCMS** sit at the git-based end. These are good products and the integrations are
well-trodden. It does mean a second system, a second bill, and a second set of
credentials.

Pushword has three surfaces writing the same content through different doors:

- **Developers**, editing Markdown files in the repository
- **Editors**, in the admin UI, who never touch git
- **AI agents**, through the [REST API](/extension/api) or by writing flat files directly

The [flat](/extension/flat) package reconciles all three. An agent can rewrite a page's
frontmatter, an editor can fix a typo in the admin, and a developer can restructure the
content directory — and the three changes converge rather than collide.

If developers are the only people who will ever touch the content, Astro's single surface
is simpler, and that simplicity is worth having.

---

## Comparing like for like

Astro against Pushword is not quite the right comparison. Astro is a rendering layer, and
it is excellent at that. But the moment someone other than a developer needs to publish,
the honest comparison becomes **Astro plus a headless CMS** against **Pushword on its
own**.

That changes the arithmetic:

|                                    | Astro + headless CMS                              | Pushword                                        |
| ---------------------------------- | ------------------------------------------------- | ----------------------------------------------- |
| Systems to run, upgrade and secure | Two                                               | One                                             |
| Where content lives                | The CMS's store, reached over HTTP at build time  | Your database, mirrored to Markdown in your git |
| Cost as the team grows             | Per-seat / per-API-call on the commercial options | Server cost                                     |
| Editor preview                     | A preview environment to wire up and keep working | The site itself                                 |
| Publish → visible                  | Webhook → CI rebuild → deploy                     | The page re-renders                             |
| If the vendor changes terms        | Export and migrate                                | Nothing to migrate; it is already your Markdown |

To be fair to the alternatives: **Decap** and **TinaCMS** are open source and store content
in your git, so they avoid the vendor and cost rows entirely — at the price of a thinner
editing experience than the commercial options. And free tiers on Storyblok, Sanity and
Contentful are genuinely usable; the bill arrives with growth, which is exactly when it is
hardest to leave.

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

Astro rebuilds everything, and incremental *builds* remain an open roadmap discussion. For
deployed HTML the answer is delegated to the CDN: Astro 7 stabilised a platform-agnostic
route-caching API, which is a real improvement on the older per-adapter approach (ISR on
Vercel, cache tags on Netlify) — the app now expresses caching once and providers honour
it. This is a perfectly reasonable division of labour for a build tool, and full rebuilds
are wonderfully hard to get wrong.

Pushword needed something else, because a page save must not trigger a full-site render.
Each host now carries a **render epoch** — an opaque token bumped by any change that can
affect other pages' output: a snippet edit, a media edit, a template save, a published
review, or a page edit that adds or removes an internal link. Pages are stamped with the
epoch they rendered under, and a mismatch means stale. Sweeps are debounced and
incremental, and a re-render whose HTML is byte-identical skips the write entirely. See
[page-cache](/extension/page-cache) for the full model.

Pushword is in an easier position than Astro here, not a smarter one: we render through PHP
services we own, so collecting render dependencies is a normal service concern. Astro would
have to instrument its bundler.

---

## Where Pushword is the better choice

- **Anyone other than a developer publishes.** You get an admin UI, media management,
  versioning and publication holds in the box, with no second system to run, secure,
  upgrade or pay for.
- **You want the whole site in one deliverable.** Admin, media with image variants,
  [versioning](/extension/version), [search](/extension/search),
  [forms](/extension/conversation), [newsletter](/extension/newsletter),
  [redirections](/extension/flat), [dead-link scanning](/extension/page-scanner),
  [REST API](/extension/api) and static export ship as maintained bundles of one project,
  tested together. Assembling the same set from integrations and SaaS is work you do once
  and then maintain forever.
- **Publishing should be instant.** An editor saves and that page is live — no commit, no
  CI queue, no rebuild. This is an everyday workflow difference, not just a
  large-site optimisation.
- **You run a fleet.** One installation serves many hosts and locales, sharing templates,
  media and code. The alternative is N projects, N builds and N deploys.
- **AI agents are in your content loop.** Both projects now court agents — Astro 7 added
  agent detection, a background dev server and JSON logging, and Pushword has
  [agent-optimized command output](/agent-output). The difference is what an agent can
  reach: in Astro it writes files and commits; in Pushword the [REST API](/extension/api),
  the flat-file round trip and `pw:schema:dump` let it read the content model and write
  through the same validated path an editor uses.
- **You want no vendor in the content path.** MIT, self-hosted, content as Markdown in
  your own git and a SQLite file you can copy. Nothing to export if terms change.
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
| Extensions come from     | ~61,000 plugins in the .org directory          | ~14 official, hundreds community-published    | 24 bundles, one project                      |
| Installed by             | Click-install in admin, often auto-updating    | `package.json`, reviewed in a pull request    | `composer require`, versioned together       |
| Extensions run           | At runtime, on every request                   | Mostly at build time                          | At runtime, but shipped and tested as a set  |
| A broken extension means | A broken live site, sometimes a security hole  | A failed build, caught in CI                  | A failed test or a failed deploy             |
| Core breaking changes    | Almost never                                   | 7 majors since August 2022                    | Rolling `1.0.0-rc`, Symfony LTS underneath   |
| Therefore the risk is    | **Entropy** — nothing forces you to update     | **Churn** — you cannot stand still            | **Concentration** — a small team, less choice |

### WordPress: entropy

WordPress's plugin ecosystem is unmatched in breadth, and its refusal to break backward
compatibility is a real service to millions of sites. But those two facts produce the
familiar problem together: because nothing forces an update, plugins accumulate, each
hooking the same runtime and writing to the same tables. Conflicts appear in production,
and abandoned plugins are risky both to keep and to remove.

### Astro: churn, but loud and early

Astro will not reproduce that, and the architecture is the reason. Integrations run at
build time, they are pinned in `package.json` and reviewed in a pull request, and they do
not share a mutable request lifecycle. When one breaks, the build fails in CI rather than
the site failing for visitors. That is a much healthier failure mode, and it is the honest
answer to "is this the next plugin nightmare?" — no, structurally it is not.

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

Pushword avoids both failure modes by not having a third-party extension ecosystem at all.
The 24 bundles are versioned, released and tested together, so "does the newsletter package
work with this version of the admin?" is a question CI answers rather than you. Symfony's
LTS cadence sits underneath, and the surface that can break is small.

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

Adopting a smaller project should be judged on its exit cost, not on a feeling. Ours is
deliberately low, and you can verify every line of this before committing:

- **Content** is Markdown with YAML frontmatter, in your git — the same shape Astro's
  content collections read. Migrating content to Astro is largely a directory copy.
- **The database** is a SQLite file you own, or your own MySQL. There is no hosted service
  holding anything, no API key, no export request, no egress bill.
- **Templates** are Twig, and the application is a standard Symfony app. A Symfony
  developer who has never seen Pushword can read, debug and extend it.
- **Media** are ordinary files on disk in a directory you control.
- **The licence** is MIT, and the whole monorepo is public. If the project stopped
  tomorrow, you would be maintaining a Symfony bundle set, which is an ordinary thing for
  a PHP team to do.

Compare that honestly against the exit cost of a hosted headless CMS, where the content
lives in someone else's database under terms they can revise.

The reasonable way to test the claim is to spend an afternoon on it and put a real page
through the part that matters most to you — the admin UI, the flat-file round trip, or the
API:

```shell
composer create-project pushword/new pushword "^1.0.0-rc"
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
5. **How fast must a published change appear?** Seconds → Pushword. A CI run is acceptable
   → either.
6. **One site or a fleet?** Many hosts and locales from a single codebase is Pushword's
   default; in Astro it is N projects, N builds, N deploys.

If you would rather pair Astro with a CMS than adopt one, that is a legitimate answer and
the integrations are mature. If you would rather have the CMS built in — and keep your
content as Markdown on a disk you own — that is what Pushword is for.

---

## Resources

- **Astro**: [astro.build](https://astro.build) · [docs](https://docs.astro.build) · [github.com/withastro/astro](https://github.com/withastro/astro)
- **Pushword**: [pushword.piedweb.com](https://pushword.piedweb.com) · [github.com/Pushword/Pushword](https://github.com/Pushword/Pushword)
- Related: [CMS comparison — Pushword vs WordPress, Statamic, Sulu](/blog/cms-comparison)

<div class="not-prose p-4 mb-8 bg-blue-50 dark:bg-blue-900/30 rounded-lg shadow">
  <p class="text-sm text-blue-800 dark:text-blue-200">
    <strong>About this comparison</strong><br>
    Written by the Pushword author (and Claude). We are obviously not neutral, and Astro is
    a far more widely adopted project with a much larger community than Pushword. Claims
    about Astro are based on its official documentation and release notes as of August 2026;
    claims about Pushword are based on shipped features, not roadmap, and its release count
    and test figures are checkable on Packagist and GitHub. Where Pushword adopted an idea
    from Astro, we have said so.<br>
    <span class="text-xs">Found an error, or think we have been unfair to Astro? <a href="https://github.com/Pushword/Pushword/issues" class="underline">Open an issue</a> — corrections are welcome.</span>
  </p>
</div>

<div class="not-prose p-4 mt-8 bg-amber-50 dark:bg-amber-900/30 rounded-lg border border-amber-200 dark:border-amber-800">
  <p class="text-sm text-amber-800 dark:text-amber-200">
    <strong>Version</strong><br>
    Last updated: August 2026. Reflects Astro 7.0 (22 June 2026) and Pushword's render epoch,
    declared page properties and view transitions. Both projects move quickly; updates
    welcome via GitHub issues.
  </p>
</div>
