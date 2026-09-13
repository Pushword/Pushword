---
title: 'TYPO3 Alternative - Pushword for People Who Already Know TYPO3'
h1: 'A TYPO3 Alternative, Explained to TYPO3 People'
publishedAt: '2026-08-05 10:00'
parentPage: blog
template: /page/blog.html.twig
toc: true
---

TYPO3 has shipped since 1998 and runs sites for Deutsche Telekom, Mercedes-Benz,
Lufthansa and the FAO. It has a published security process, an 18-month LTS cadence and a
funded association behind it.

This page is for someone who knows TCA and has debugged a TypoScript object path at 11pm.
It asks whether a small or medium-sized site needs TYPO3's governance and content model,
or whether Pushword's simpler workflow would serve it better.

## The short answer

For sites with a few editors, a few thousand pages and no workspace approval chain,
**Pushword is the better default.** Two reasons, and the first
one matters more than the second.

**It may be simpler to work in, every day.** An editor can publish without a deployment
window. With [Flat](/extension/flat) enabled and synchronized, pages also become Markdown
files in your git, so file-based content changes can be reviewed and reverted there.
One installation serves a whole fleet of hosts and locales from shared templates. The
entire site can be exported to static HTML with one command. Your developers work with
Twig templates and Symfony services instead of TYPO3-specific Fluid templates, TypoScript
configuration, TCA arrays and Extbase controllers. Pushword also offers documented CLI
and API surfaces for agents. Details are in
[what you gain](#what-you-gain-beyond-subtraction), and every item is shipped, not roadmap.

**The sustainability objection matters too.** A smaller project has a different support
and migration profile, and neither side has a risk-free answer:

- TYPO3's free support is **time-boxed**. v12's free support ended on 30 April 2026.
  Continuing to receive v12 security fixes means upgrading or buying ELTS; [the v12 ELTS
  price](https://news.typo3.com/article/new-elts-pricing-v124) was set at €3,200 per
  licence/year before discounts, about 14% above the previous v10/v11 price. v14's free
  support runs to 2029, after which an upgrade or optional ELTS is the choice.
- The upgrade you are deferring is **not one upgrade**. It is your core, plus every TER
  extension you depend on, each of which must have survived the same major. One abandoned
  extension can stall the whole project.
- Meanwhile **cross-CMS migration takes mapping work**. TYPO3 content is a graph of
  `tt_content` rows, FAL references and translation records. TYPO3 provides
  [Import/Export](https://docs.typo3.org/c/typo3/cms-impexp/13.4/en-us/Introduction/Index.html),
  but moving to a different CMS still requires mapping records and behaviours.

Pushword changes those trade-offs, but **it does not offer a support promise or eliminate
end-of-life risk**. TYPO3 itself is [GPL-licensed open source](https://github.com/TYPO3/typo3/blob/main/LICENSE.txt),
not a proprietary platform. Pushword's Symfony, Doctrine and Twig foundations are widely
used, and its first-party bundles ship together. With Flat enabled, content can be synced
to Markdown in your git. Moving it to Astro, Hugo, Eleventy or another CMS would still
require mapping routes, templates, media and custom properties.

**Neither exit is free.** Pushword's portable content and familiar PHP stack can reduce
migration or maintenance work, but a small maintainer base remains a real risk. TYPO3's
governance and paid extended support reduce different risks. Budget for the failure mode
your organisation is better equipped to handle.

### Daily use before exit cost

Portability does not help an editor publish on a Tuesday. Daily work comes first.

**Daily work is the reason to choose Pushword.** Portability limits the risk if you later
leave it. If the workflow above does not suit your team, TYPO3's governance may be worth
its additional complexity.

Pushword's roughly 90,000 lines of CMS include the admin, media handling with image
variants, multi-site routing, versioning, search, forms, newsletter, the flat-file round
trip and static export. Flat can give you a readable copy of the content outside the
database.

Four situations favor TYPO3. [The exceptions](#the-four-exceptions) list them near the end.

## Quick overview

The TYPO3 code counts below are a July 2026 snapshot of the **v14.3.5** tag, not a
measurement of the latest patch release. Pushword counts are rounded because they change
as its packages and tests evolve. They describe codebase size, not reliability or speed.

|                          | TYPO3 v14 LTS                                                          | Pushword                                                        |
| ------------------------ | ---------------------------------------------------------------------- | --------------------------------------------------------------- |
| **What it is**           | A complete enterprise CMS platform                                     | A CMS assembled from Symfony bundles                            |
| **First release**        | 1998                                                                   | December 2020 (first commit 2018)                               |
| **PHP / framework**      | PHP 8.2+, Symfony **7.4 LTS** components, Doctrine **DBAL**            | PHP 8.4+, Symfony **8**, Doctrine **ORM**                       |
| **Source size**          | About **593,000 lines** across 36 sysexts at v14.3.5                   | About **90,000 lines** across 25 packages (18 bundles)         |
| **Core alone**           | About 212,000 lines (`core`) + 110,000 (`backend`)                      | About 30,000 lines                                              |
| **Templating**           | Fluid (147 ViewHelpers, 714 templates) + TypoScript                    | Twig                                                            |
| **Content model**        | TCA: 279 files, `pages` alone is 1,012 lines                          | One Doctrine entity + Markdown + declared properties            |
| **Write path**           | `DataHandler`: one class, **9,737 lines**                             | Doctrine, EasyAdmin, REST API, or flat files                    |
| **Content portability**  | Import/Export exists; another CMS needs content mapping                | Markdown sync via Flat; another CMS still needs mapping         |
| **Tests**                | 1,481 test files at v14.3.5                                            | More than 400 test files and 3,000 test methods                 |
| **Support model**        | Free LTS to 2029; optional paid ELTS after that                         | No SLA; self-maintenance is possible under the MIT licence      |
| **Ecosystem**            | Large TER extension marketplace                                        | 25 first-party packages, on the Symfony commons                 |
| **Upgrade unit**         | Core **and** every TER extension, every ~18 months                     | One monorepo, released and tested together                      |

TYPO3's extension directory is larger, and that row is
the real argument for staying. Read the next section before deciding how much it is worth
to you, because ecosystem size is not purely an asset.

## The sustainability question

Project survival matters, but it does not tell you what leaving a platform would cost.

Exit cost matters if a major upgrade becomes unaffordable or an extension stops receiving
updates, even when the CMS itself survives.

### What TYPO3's guarantee actually guarantees

TYPO3's governance is real and it is well run. Its [published support model](https://typo3.org/cms/roadmap)
offers free LTS for a defined period and optional paid extended support after that:

- **Free security support is time-boxed.** TYPO3 v12 reached the end of free support on
  30 April 2026. Sites still on it are choosing between an upgrade project and an ELTS
  subscription.
- **ELTS is priced.** The [v12 price](https://news.typo3.com/article/new-elts-pricing-v124)
  is €3,200 per licence/year before discounts; terms and future prices should be budgeted.
- **The cadence is not optional.** A major roughly every 18 months, LTS supported around
  three years. Standing still is a decision that eventually costs money.

TYPO3 provides upgrade wizards and an Extension Scanner that flags removed API calls in
extensions. That is valuable tooling Pushword does not match. Its presence reflects
TYPO3's wider API and long upgrade history; it does not mean every TYPO3 upgrade is hard.

### Extension upgrade risk

An extension can save development time, but each third-party package has its own
compatibility schedule. An unmaintained extension can delay an upgrade. Maintained
extensions and TYPO3's upgrade tooling reduce that risk.

**A large extension directory does not remove maintainer risk.** A TYPO3 site with
third-party extensions depends on their upgrade schedules. That is worth auditing, but it
does not make TYPO3's funded core support equivalent to Pushword's smaller maintainer base.

Pushword's 18 bundles are versioned, released and tested together in one monorepo. "Does
the newsletter package work with this version of the admin?" is a question CI checks.
There is far less choice, and one principal maintainer affects all first-party bundles;
that concentration is a real cost, even though dependency compatibility is simpler.

### Five portability factors

Five properties affect the cost of maintaining or leaving Pushword:

1. **Your content can be exported in a familiar format.** With [Flat](/extension/flat)
   enabled and synchronized, pages are Markdown files with YAML frontmatter in your git.
   Astro, Hugo and Eleventy can read Markdown, but routes, properties, media references
   and relationships still need adaptation.
2. **The implementation uses familiar tools.** Twig templates, EasyAdmin and Doctrine
   entities are more familiar to a Symfony team than TYPO3-specific Fluid, TCA and
   Extbase. That helps when maintaining Pushword. It does not mean the admin or templates
   can be dropped unchanged into a different CMS.
3. **Your application is conventional PHP.** Pushword is roughly 90,000 lines of Symfony
   packages. If it stopped tomorrow, a Symfony team could maintain it under the MIT
   licence, but ownership of security fixes and upgrades would become your responsibility.
4. **The limits of portability matter.** Markdown helps preserve content; it does not
   preserve media variants, newsletter delivery, search wiring, the flat sync, or every
   custom property and relationship. TYPO3 has its own Import/Export tooling, but a move
   to another CMS likewise needs mapping and implementation work. Compare real site
   exports, not a promise that either exit is a directory copy.
5. **The shared foundations help, but do not replace CMS maintenance.** Symfony, Doctrine,
   Twig, CommonMark and Flysystem have independent maintainers and security processes.
   Pushword's own integration and application code can also have security defects; the
   small maintainer base remains part of the risk calculation.

That last point matters when counting contributors. Both projects depend on maintained
libraries and on application-specific code. TYPO3 owns more of its CMS-specific APIs and
has an association to support them; Pushword builds more directly on Symfony components
but has a much smaller project team.

### Maintainer risk

Pushword has one principal maintainer. There is no
association, no SLA, no certification programme and no second phone number. If you need a
contract with a company on it, TYPO3 has one and this page cannot give you one.

That does not make it the wrong choice for every site. MIT licensing, a familiar stack and
optional Markdown sync can make the risk
easier to manage, but cannot quantify or cap it. TYPO3's governance and support offer a
different kind of protection. Which matters more depends on your site and team.

## The structural difference: records versus documents

Both projects use Symfony components. They differ in **what a page is**.

**In TYPO3, a page is a graph of typed records.** A `pages` row, a tree of `tt_content`
rows, `sys_file_reference` rows into FAL, translation rows, version rows. Every field is
described in TCA, and every write goes through `DataHandler`: 9,737 lines in a single class
that enforces permissions, references, history, translation and workspace versioning in
one pass. It is impressive engineering, and it is why a TYPO3 editor can be given
permission to edit one field of one record type in one branch of the page tree.

**In Pushword, a page is a document.** One `Page` Doctrine entity stores Markdown body and
properties; Flat can represent those properties in YAML frontmatter. Extra fields are
declared in configuration rather than in a schema-plus-TCA-plus-SQL triple.

That choice helps explain the difference in scope, though line counts alone cannot
measure complexity or quality. TYPO3 models fine-grained records and permissions;
Pushword centres on a page document that can also be represented as a file through Flat.

## What actually disappears

For a TYPO3 developer, Pushword replaces several CMS-specific systems with Symfony and Twig.

| TYPO3 concept                                          | In Pushword                                                    |
| ------------------------------------------------------ | -------------------------------------------------------------- |
| **TCA**: 279 files of nested arrays                   | A Doctrine entity, plus YAML for custom properties             |
| **TypoScript**: 45 `ContentObject` classes            | Symfony configuration and Twig templates                       |
| **Fluid**: 147 ViewHelpers, 714 templates             | Twig, the one you already use in every other Symfony project   |
| **Extbase**: 31,730 lines of CMS-specific MVC         | Symfony controllers and services                               |
| **DataHandler**: 9,737 lines                          | Doctrine-backed services, REST API or Flat sync                |
| **FAL**: drivers, storages, `sys_file_reference`      | Flysystem plus a `Media` entity                                |
| **Install Tool + 22 upgrade wizards**                  | `doctrine:schema:update --force`                               |
| **`ext_localconf.php` / `ext_tables.php`**             | A standard Symfony bundle                                      |

A Symfony developer has a familiar starting point in Pushword's services, Doctrine entities
and Twig templates, but still needs to learn its content and extension model. For an agency,
that may widen the hiring pool compared with a TYPO3-specific implementation; actual
onboarding and staffing costs vary.

### One concrete example: adding a field

In TYPO3, adding a "price from" field to pages means an `ext_tables.sql` entry, a TCA
override in `Configuration/TCA/Overrides/pages.php`, a `showitem` string edit to get it
into a palette, and a ViewHelper or TypoScript to render it.

In Pushword, it is one configuration block:

```yaml
pushword:
  apps:
    - hosts: [example.com]
      page_properties:
        price_from: { type: int, constraints: [{ Positive: ~ }] }
```

Constraints are ordinary Symfony validator constraints, the schema is checked at container
compile time, and `pw:schema:dump` exposes the model to `/api/docs` and to AI agents. The
field is then readable in Twig, writable through the admin, the API and the flat files, and
it round-trips through front matter. See [declared page properties](/page-properties).

The trade is visible in the example: TCA can express inline relations,
per-field permissions, workspace-aware translation behaviour, custom form elements. If you
need those, you need TCA, and TCA is 279 files for a reason.

## What you gain, beyond subtraction

You can test the workflow below on a small site. [Trying it](#trying-it) covers the
installation and a sample import; the editing and publishing steps show what your team
would use day to day.

Consider a change that arrives on a Tuesday: a client wants a paragraph reworded on twelve
pages across three locales. TYPO3 offers backend edits or a scripted change through its
content APIs. With Pushword's Flat workflow, you can edit twelve Markdown files in a pull
request and run `pw:flat:sync`; if the file change was wrong, revert it and sync again.
That is an option for teams that want content review in git, not a claim that TYPO3 cannot
audit changes.

- **Publishing does not require a deployment.** An editor saves and the affected cached
  page can re-render. TYPO3 also supports direct publishing; this is not unique to
  Pushword.
- **Content changes can be git diffs.** [Flat](/extension/flat) synchronizes pages with
  Markdown files while the admin keeps working, so file edits are reviewable in a pull
  request; rollback requires reverting and syncing. TYPO3's content is DB-backed and its
  [Import/Export](https://docs.typo3.org/c/typo3/cms-impexp/13.4/en-us/Introduction/Index.html)
  serves a different workflow. See the
  [git-integrated content workflow](/extension/flat-git-workflow).
- **The whole site can go static.** [`pw:static`](/extension/static-generator) exports the
  site as HTML for Apache, GitHub Pages or FrankenPHP, incrementally and in parallel.
  Compare TYPO3's extension options if static export matters to your site.
- **One installation, many hosts and locales.** A fleet of client sites sharing templates,
  media and code from one codebase and one admin.
- **AI agents are first-class clients.** Many `pw:*` commands detect an agent and emit a
  single compact JSON line instead of progress bars. See
  [agent-optimized output](/agent-output). The [REST API](/extension/api) is
  OpenAPI-described, `pw:schema:dump` hands an agent the content model, and
  `vendor/pushword/docs/CLAUDE.md` ships instructions for the agent working on *your* site.
  TYPO3 can also be automated; compare the available APIs and editorial workflows.
- **No separate database server by default.** SQLite is the default, and a site does not
  require a cache server. You still need PHP hosting and a deployment plan.
- **The rest ships as maintained bundles.** [Admin](/extension/admin),
  [search](/extension/search) (Loupe, no search server),
  [forms and comments](/extension/conversation), [newsletter](/extension/newsletter),
  [dead-link scanning](/extension/page-scanner), [redirections](/extension/flat),
  [snippets](/extension/snippet), [REST API](/extension/api). They are versioned and tested
  together.

## Shared design choices

Both projects use Symfony components, server-rendered HTML and schema-driven content.

| Idea                                   | TYPO3 v14                                       | Pushword                                             |
| -------------------------------------- | ------------------------------------------------ | ---------------------------------------------------- |
| Symfony components underneath          | Symfony 7.4 LTS components                      | Full Symfony 8 application                           |
| PSR-14 events over magic hooks         | 287 event classes                               | Events, entity filters and tagged providers          |
| Typed schema over untyped arrays       | The v13/v14 Schema API over TCA                 | `PagePropertySchema` + validator constraints         |
| Site configuration as YAML             | `config/sites/*/config.yaml`                    | `pushword.apps` configuration                        |
| Server-rendered HTML, JS opt-in        | Fluid, no mandatory frontend framework          | Twig, JS opt-in per component                        |
| Structured content over a WYSIWYG blob | Content elements                                | Markdown + declared properties + [snippets](/extension/snippet) |

TYPO3 has 287 PSR-14 event classes, and **42 files still read
`$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']`**. A decade into that migration, the old hook
system is still load-bearing. That is what backward compatibility costs at TYPO3's scale,
and it is a compatibility cost a smaller project may face differently.

## The four exceptions

Four situations can favor TYPO3. If one is yours, weigh it before switching.

The need for these controls depends on how your team publishes. A staged approval chain is a
feature of organisations with a compliance function or a legal review step, not of
organisations with editors. Per-record permissions matter when editors need separate
sections. A team of three colleagues editing the same site may not need them. Check which
controls your team actually uses.

1. **You need workspaces or a staged publishing workflow.** 10,344 lines of sysext
   implementing draft workspaces, preview links, staged publishing and record dependency
   resolution. Pushword has page [versioning](/extension/version) and publication holds.
   That is not the same thing and will not become the same thing.
2. **You need per-editor, per-record permissions.** `BackendUserAuthentication` is 2,219
   lines: page-tree mounts, per-table and per-field access, group inheritance. Pushword has
   five flat roles. Multi-editor scoping is on the roadmap; today it is a gap, not a
   difference.
3. **You depend on specific TER extensions.** Check which are installed. Even two or three that
   solve a real problem can decide this outright.
4. **A contract requires an SLA, a certification, or named-year support.** ELTS and the
   certified-partner network exist for exactly this, and no amount of engineering argument
   answers a procurement checklist.

**Pushword only recently reached
stable `1.0`**, has English and French admin translations, and targets SQLite, PostgreSQL
and MariaDB rather than TYPO3's four database engines. If any of those is a blocker, it is
a blocker.

## Trying it

The reasonable test is an afternoon, on a real site rather than a demo, ideally a small one
you currently maintain on TYPO3 and resent invoicing for:

```shell
composer create-project pushword/new pushword
```

Then check the exit before you commit to the entrance. Import a few pages, run
`pw:flat:sync`, and look at what lands in your content directory. Check how much of your
routes, media and custom data would still need migration; the filesystem answers part of
the sustainability question, not all of it.

## Resources

- **TYPO3**: [typo3.org](https://typo3.org) · [docs.typo3.org](https://docs.typo3.org) · [github.com/TYPO3/typo3](https://github.com/TYPO3/typo3)
- **Pushword**: [pushword.piedweb.com](https://pushword.piedweb.com) · [github.com/Pushword/Pushword](https://github.com/Pushword/Pushword) · [architecture](/architecture) · [extensions](/extensions) · [getting help](/pro)
- Related: [Astro vs Pushword](/blog/astro-vs-pushword) · [CMS comparison: WordPress, Statamic, Sulu](/blog/cms-comparison)

> [!note] About this comparison
>
> Written by the Pushword author (and Claude). We are obviously not neutral, and TYPO3 is
> a vastly more established project with a governance body, a security team and a
> commercial ecosystem Pushword does not have. TYPO3 code counts are a snapshot measured
> from the `v14.3.5` tag (14 July 2026), not the latest patch; support dates and ELTS
> pricing come from TYPO3's own announcements. Pushword code counts are approximate.
>
> Found an error, or think we have been unfair to TYPO3? [Open an issue](https://github.com/Pushword/Pushword/issues); corrections are welcome.

> [!warning] Version
>
> Last updated: September 2026. TYPO3's current v14 patch is
> [14.3.7](https://get.typo3.org/list/version/14), released 8 September 2026; code counts
> above are from v14.3.5. TYPO3 v14 LTS was released 21 April 2026 and is supported until
> 2029. Pushword is on `1.0`. TYPO3 v15
> is in development with an LTS projected for autumn 2027.
