---
title: 'TYPO3 Alternative - Pushword for People Who Already Know TYPO3'
h1: 'A TYPO3 Alternative, Explained to TYPO3 People'
publishedAt: '2026-08-05 10:00'
parentPage: blog
template: /page/blog.html.twig
toc: true
---

TYPO3 is one of the few content management systems that genuinely deserves the word
"enterprise", and this page starts by saying so. It has been shipping since 1998, it runs
Deutsche Telekom, Mercedes-Benz, Lufthansa and the FAO, it has a published security
process, an 18-month LTS cadence and a funded association behind it. Almost nothing else in
PHP can say all of that at once.

This page is written for someone who already knows what TCA is and has debugged a
TypoScript object path at 11pm. It does not argue that TYPO3 is bad.
It argues something narrower and, if you run small-to-medium sites, more useful: **TYPO3 is
sized for a particular kind of organisation, and if you are not that organisation, the
sustainability argument you think protects you is pointing the other way.**

## The short answer

For most sites below the enterprise line — a few editors, a few thousand pages, no
workspace approval chain — **Pushword is the better default.** Two reasons, and the first
one matters more than the second.

**It is better to work in, every day.** An editor saves and the page is live: no
deployment window, no cache-warming step. Every page is simultaneously a Markdown file in
your git, so content changes arrive as reviewable diffs and roll back with `git revert`.
One installation serves a whole fleet of hosts and locales from shared templates. The
entire site can be exported to static HTML with one command. Your developers write Twig and
Symfony rather than Fluid, TypoScript, TCA and Extbase: four proprietary languages you
stop maintaining fluency in. And your CMS talks to AI agents as first-class clients rather
than treating them as a UX feature. Details are in
[what you gain](#what-you-gain-beyond-subtraction), and every item is shipped, not roadmap.

**And the objection you are about to raise points the other way.** The reflex against a
small project is sustainability. That reflex is right to exist and wrong about the
direction here:

- TYPO3's support promise is **conditional and time-boxed**. v12's free support ended on
  30 April 2026. Continuing to receive security fixes now means buying ELTS, whose price
  rose about 15% that same month. "Supported until 2029" means "until 2029, then pay or
  migrate."
- The upgrade you are deferring is **not one upgrade**. It is your core, plus every TER
  extension you depend on, each of which must have survived the same major. One abandoned
  extension can stall the whole project.
- Meanwhile the **exit is expensive by construction**. Your content is a graph of
  `tt_content` rows, FAL references and translation records. Leaving requires writing an
  exporter against a model 593,000 lines deep.

Pushword inverts all three. To be precise about what that does and does not mean: **it is
not a support promise, and we are not offering you one.** It is the absence of a
proprietary platform that can reach end-of-life. The stack is Symfony, Doctrine and Twig,
each with its own LTS policy and thousands of contributors. There is no third-party
extension ecosystem that can block an upgrade. And the content is already Markdown in your
git, portable to Astro, Hugo, Eleventy or a headless CMS in an afternoon.

**The maximum downside of choosing Pushword is bounded. The maximum downside of choosing
TYPO3 is an unbudgeted migration or a permanent support subscription.** Bounded is not the
same as zero. Inheriting 91,000 unmaintained lines is real work, just work you can price
in advance and hire an ordinary Symfony developer to do.

### "An exit is not a reason to choose an entrance"

Correct, and worth stating plainly because it is the strongest objection to everything
above. Portability is not a feature you enjoy on a Tuesday. Nobody buys a CMS for how
gracefully it can be abandoned.

So read the two arguments in the right order. **The daily-work case is the reason to
choose Pushword. The portability case is only the answer to why choosing it is not
reckless.** If the first paragraph of this section did not describe something you want,
none of the durability arithmetic should persuade you — go back to TYPO3 with our respect.

The related objection deserves the same directness: *if leaving is that cheap, are you not
just selling me Symfony?* Partly, yes — deliberately. What sits on top is 91,000 lines of
CMS you would otherwise write and then own: the admin, media handling with image variants,
multi-site routing, versioning, search, forms, newsletter, the flat-file round trip, static
export. The claim is not that this layer is worthless. It is that this layer does not hold
your content hostage, which is a different and rarer property.

Four situations genuinely reverse this, and they are listed honestly in
[the exceptions](#the-four-exceptions) near the end. If none of them describe you, the rest
of this page is the detail.

---

## Quick overview

Figures below are measured, not remembered: TYPO3 from the **v14.3.5** tag (14 July 2026),
Pushword from its `main` branch in August 2026. You can reproduce all of them with `find`
and `wc -l`.

|                          | TYPO3 v14 LTS                                                          | Pushword                                                        |
| ------------------------ | ---------------------------------------------------------------------- | --------------------------------------------------------------- |
| **What it is**           | A complete enterprise CMS platform                                     | A CMS assembled from Symfony bundles                            |
| **First release**        | 1998                                                                   | December 2020 (first commit 2018)                               |
| **PHP / framework**      | PHP 8.2+, Symfony **7.4 LTS** components, Doctrine **DBAL**            | PHP 8.4+, Symfony **8**, Doctrine **ORM**                       |
| **Source size**          | **593,000 lines** across 36 sysexts                                    | **91,000 lines** across 25 packages (18 bundles)                |
| **Core alone**           | 212,000 lines (`core`) + 110,000 (`backend`)                           | 27,000 lines                                                    |
| **Templating**           | Fluid (147 ViewHelpers, 714 templates) + TypoScript                    | Twig                                                            |
| **Content model**        | TCA — 279 files, `pages` alone is 1,012 lines                          | One Doctrine entity + Markdown + declared properties            |
| **Write path**           | `DataHandler` — one class, **9,737 lines**                             | Doctrine, EasyAdmin, REST API, or flat files                    |
| **Content portability**  | Bespoke exporter against `tt_content` + FAL                            | Already Markdown files in your git                              |
| **Tests**                | 1,481 test files                                                       | 402 test files, ~3,000 test methods                             |
| **Support model**        | LTS to 2029, then **paid ELTS**                                        | No SLA — and no proprietary platform that can reach end-of-life |
| **Ecosystem**            | 5,000+ TER extensions, 342,000 Packagist downloads/month               | 25 first-party packages, on the Symfony commons                 |
| **Upgrade unit**         | Core **and** every TER extension, every ~18 months                     | One monorepo, released and tested together                      |

TYPO3's ecosystem is genuinely larger (roughly 500× by download volume), and that row is
the real argument for staying. Read the next section before deciding how much it is worth
to you, because ecosystem size is not purely an asset.

---

## The sustainability question, asked properly

Everyone asks "which project is more likely to still be here in five years?" It is the
wrong question, and it is the reason people end up on platforms they cannot afford to
leave.

The right question is: if this goes wrong, what does it cost me to get out? Because
"going wrong" is not only "the project dies". Far more commonly it is the project thriving
in a direction that no longer includes you: a major you cannot afford, or an extension that
never got ported.

### What TYPO3's guarantee actually guarantees

TYPO3's governance is real and it is well run. It is also, structurally, a business model
that monetises the difficulty of upgrading. That is not cynicism, it is the published
product:

- **Free security support is time-boxed.** TYPO3 v12 reached the end of free support on
  30 April 2026. Sites still on it are choosing between an upgrade project and an ELTS
  subscription.
- **ELTS is priced, and the price moves.** v12 ELTS rose roughly 15% in April 2026.
- **The cadence is not optional.** A major roughly every 18 months, LTS supported around
  three years. Standing still is a decision that eventually costs money.

TYPO3 handles this better than any comparable project: 22 upgrade wizards and an Extension
Scanner carrying 17,633 lines of matchers that statically flags removed API calls in your
own extensions. That tooling is excellent and Pushword has nothing like it. But consider
what its existence tells you. An upgrade path needs 17,633 lines of static analysis because
the upgrade is genuinely that hard.

### The 5,000-extension ecosystem, read honestly

A large ecosystem is an asset when you are building and a liability when you are upgrading.
Every TER extension you install becomes something that must survive the next major on
someone else's schedule. The classic TYPO3 failure mode is not core breaking, since core is
well managed. It is one unmaintained extension holding an entire site on an unsupported
version.

There is a second thing worth noticing here, because it reframes the objection people
usually raise about small projects. **A large ecosystem does not remove single-maintainer
risk — it distributes it and makes it harder to see.** Most TER extensions are maintained
by one developer or one small agency, exactly like Pushword. A TYPO3 site with six
extensions is carrying six bus factors, each on its own schedule, none of them on your
balance sheet until the major lands and one of them has not been ported.

Pushword's 18 bundles are versioned, released and tested together in one monorepo. "Does
the newsletter package work with this version of the admin?" is a question CI answers
rather than you. Far less choice, one bus factor instead of several, and nothing outside
your control can block an upgrade.

### What actually protects you

Five concrete properties, none of which is a vendor's promise:

1. **Your content is already portable.** Every page is a Markdown file with YAML front
   matter in your git, kept in sync by [flat](/extension/flat). That is the same shape
   Astro's content collections, Hugo and Eleventy read. Migrating away is close to a
   directory copy.
2. **Your templates and admin outlive the project too.** This is the objection worth
   answering properly: content surviving is no use if the templates and the admin die with
   the project. They do not, because none of that layer is ours. Your templates are Twig,
   the same Twig that runs Symfony, Drupal 10+, Craft and Grav. Your admin is EasyAdmin, a
   standard Symfony bundle you could keep using with the CMS removed
   entirely, and your entities are plain Doctrine. Compare the TYPO3 equivalents: Fluid
   templates, TCA arrays and Extbase controllers are worth precisely nothing outside TYPO3,
   which is what makes leaving expensive.
3. **Your application is not exotic.** Pushword is 91,000 lines of Symfony bundles. If it
   stopped tomorrow, you would be maintaining a Symfony app, an ordinary thing any PHP
   shop can do with the Symfony hiring pool behind it. Compare that with inheriting an
   unsupported TYPO3 v11 and three abandoned TER extensions.
4. **The limits of portability, stated plainly.** Your content, your Twig
   templates, your entities and your admin survive. Pushword-specific behaviour does not:
   media variant generation, the newsletter engine, search wiring, the flat sync itself.
   Leaving is not free, and any page that tells you otherwise is selling. The honest claim
   is narrower: leaving costs you the *behaviour* but never the *content*, and rebuilding
   behaviour on top of files you already hold is a quotable project. Exiting TYPO3 means
   writing the exporter before you can even start that quote.
5. **The commons underneath is enormous.** Pushword is a small project standing on Symfony,
   Doctrine, Twig, CommonMark and Flysystem — each with its own release cadence, LTS
   policy, corporate backing and thousands of contributors. The parts most likely to need
   a security fix at 3am are not Pushword's parts, and they are not maintained by us.

That last point is the one people miss when they count contributors. Repository activity is
not what makes a CMS sustainable. What matters is whether the layers you actually depend on
are maintained, and TYPO3 chose to own those layers: its own MVC, its own template engine,
its own configuration language, its own file abstraction. That is why it needs an
association to keep them alive. Pushword chose not to own them.

### The honest counterweight

Pushword has one principal maintainer. We are not going to dress that up: there is no
association, no SLA, no certification programme and no second phone number. If you need a
contract with a company on it, TYPO3 has one and this page cannot give you one.

What we will push back on is the leap from that fact to "therefore it is the risky choice".
The single-maintainer risk is real and it is bounded by MIT licensing, a standard stack,
and content that is already in a portable format. TYPO3's risks are smaller in
probability and much larger in cost. Which of those you should fear depends on your budget,
not on your instincts about project size.

---

## The structural difference: records versus documents

Both projects sit on Symfony components, so this is not a modern-versus-legacy story. The
real divergence is **what a page is**.

**In TYPO3, a page is a graph of typed records.** A `pages` row, a tree of `tt_content`
rows, `sys_file_reference` rows into FAL, translation rows, version rows. Every field is
described in TCA, and every write goes through `DataHandler`: 9,737 lines in a single class
that enforces permissions, references, history, translation and workspace versioning in
one pass. It is impressive engineering, and it is why a TYPO3 editor can be given
permission to edit one field of one record type in one branch of the page tree.

**In Pushword, a page is a document.** One `Page` Doctrine entity, body in Markdown,
structure in front matter. Extra fields are declared in configuration rather than in a
schema-plus-TCA-plus-SQL triple.

Everything else follows from that one choice. TYPO3 is 6.5× larger because modelling
structured content properly *costs* that much. Pushword is small because it declined the
problem — and portable because a document is a file.

---

## What actually disappears

The honest pitch to a TYPO3 developer is **subtraction**.

| TYPO3 concept                                          | In Pushword                                                    |
| ------------------------------------------------------ | -------------------------------------------------------------- |
| **TCA** — 279 files of nested arrays                   | A Doctrine entity, plus YAML for custom properties             |
| **TypoScript** — 45 `ContentObject` classes            | Symfony configuration and Twig. There is no second language    |
| **Fluid** — 147 ViewHelpers, 714 templates             | Twig, the one you already use in every other Symfony project   |
| **Extbase** — 31,730 lines of CMS-specific MVC         | Symfony controllers and services                               |
| **DataHandler** — 9,737 lines                          | `$em->persist()`, or the REST API, or write the Markdown file  |
| **FAL** — drivers, storages, `sys_file_reference`      | Flysystem plus a `Media` entity                                |
| **Install Tool + 22 upgrade wizards**                  | `doctrine:schema:update --force`                               |
| **`ext_localconf.php` / `ext_tables.php`**             | A standard Symfony bundle                                      |

A Symfony developer with no Pushword experience can read, debug and extend a Pushword site
on day one, because there is nothing Pushword-specific to learn first. The stack *is*
Symfony, Doctrine and Twig. For an agency that is a P&L line rather than an aesthetic one:
**you hire from the Symfony pool rather than the TYPO3 pool**, and those pools are not the
same size or the same price.

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

The trade is visible in the example: TCA can express things this cannot — inline relations,
per-field permissions, workspace-aware translation behaviour, custom form elements. If you
need those, you need TCA, and TCA is 279 files for a reason.

---

## What you gain, beyond subtraction

This is the section that should decide it, so here it is with specifics rather than
adjectives. Since it is also the section you have least reason to take on trust from a
vendor, every claim below is one you can falsify in an afternoon on your own machine rather
than believe. [Trying it](#trying-it) at the end is the short version: install, import a few
real pages, and check whether the workflow described here is the one you get.

Consider a change that arrives on a Tuesday: a client wants a paragraph reworded on twelve
pages across three locales. On TYPO3 that is twelve backend edits through `DataHandler`,
or a one-off script against `tt_content`, and no diff anyone can review afterwards. On
Pushword it is `sed` across twelve Markdown files, a pull request your colleague approves,
`pw:flat:sync`, done — and if it was wrong, `git revert`. Same CMS, same admin still
working for the editor who prefers it. That is the difference the rest of this list is
made of.

- **Publishing is instant.** An editor saves and that page re-renders. No deployment
  window, no cache-warming ritual.
- **Content changes are git diffs.** [Flat](/extension/flat) mirrors every page to Markdown
  with YAML front matter while the admin keeps working, so content is reviewable in a pull
  request and rollback is `git revert`. TYPO3 has `impexp`, but content is fundamentally
  DB-bound and there is no equivalent workflow. See the
  [git-integrated content workflow](/extension/flat-git-workflow).
- **The whole site can go static.** [`pw:static`](/extension/static-generator) exports the
  site as HTML for Apache, GitHub Pages or FrankenPHP, incrementally and in parallel. TYPO3
  has no core equivalent.
- **One installation, many hosts and locales.** A fleet of client sites sharing templates,
  media and code from one codebase and one admin.
- **AI agents are first-class clients.** Many `pw:*` commands detect an agent and emit a
  single compact JSON line instead of progress bars — see
  [agent-optimized output](/agent-output). The [REST API](/extension/api) is
  OpenAPI-described, `pw:schema:dump` hands an agent the content model, and
  `vendor/pushword/docs/CLAUDE.md` ships instructions for the agent working on *your* site.
  TYPO3 v14's AI work is editor-facing UX. This is a different bet.
- **No infrastructure to provision.** SQLite by default, no migration files, no cache
  server. A site is a directory and a `.db` file you can copy.
- **The rest ships as maintained bundles.** [Admin](/extension/admin),
  [search](/extension/search) (Loupe, no search server),
  [forms and comments](/extension/conversation), [newsletter](/extension/newsletter),
  [dead-link scanning](/extension/page-scanner), [redirections](/extension/flat),
  [snippets](/extension/snippet), [REST API](/extension/api) — versioned and tested
  together.

---

## Where the two agree more than you would expect

Both projects arrived independently at several of the same conclusions, which usually means
the conclusions are right.

| Idea                                   | TYPO3 v14                                       | Pushword                                             |
| -------------------------------------- | ------------------------------------------------ | ---------------------------------------------------- |
| Symfony components underneath          | Symfony 7.4 LTS components                      | Full Symfony 8 application                           |
| PSR-14 events over magic hooks         | 287 event classes                               | Events, entity filters and tagged providers          |
| Typed schema over untyped arrays       | The v13/v14 Schema API over TCA                 | `PagePropertySchema` + validator constraints         |
| Site configuration as YAML             | `config/sites/*/config.yaml`                    | `pushword.apps` configuration                        |
| Server-rendered HTML, JS opt-in        | Fluid, no mandatory frontend framework          | Twig, JS opt-in per component                        |
| Structured content over a WYSIWYG blob | Content elements                                | Markdown + declared properties + [snippets](/extension/snippet) |

The PSR-14 convergence deserves a footnote in TYPO3's favour and against it at once: 287
event classes is real modernisation work, and **42 files still read
`$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']`**. A decade into that migration, the old hook
system is still load-bearing. That is what backward compatibility costs at TYPO3's scale,
and it is precisely the cost a 91,000-line project does not have to pay.

---

## The four exceptions

Four situations reverse everything above. They are specific, and if one is yours it is
decisive — stay on TYPO3 and stop reading.

Size them honestly before you assume one applies, though, because "enterprise CMS"
vocabulary makes them sound more universal than they are. A staged approval chain is a
feature of organisations with a compliance function or a legal review step, not of
organisations with editors. Per-record permissions matter when different editors must be
prevented from touching each other's sections — not when three colleagues all edit the
whole site and trust each other. Most small and mid-sized sites need neither, have never
configured either in TYPO3, and are paying for both.

1. **You need workspaces or a staged publishing workflow.** 10,344 lines of sysext
   implementing draft workspaces, preview links, staged publishing and record dependency
   resolution. Pushword has page [versioning](/extension/version) and publication holds.
   That is not the same thing and will not become the same thing.
2. **You need per-editor, per-record permissions.** `BackendUserAuthentication` is 2,219
   lines: page-tree mounts, per-table and per-field access, group inheritance. Pushword has
   five flat roles. Multi-editor scoping is on the roadmap; today it is a gap, not a
   difference.
3. **You depend on specific TER extensions.** Count them honestly. Even two or three that
   solve a real problem can decide this outright.
4. **A contract requires an SLA, a certification, or named-year support.** ELTS and the
   certified-partner network exist for exactly this, and no amount of engineering argument
   answers a procurement checklist.

Also worth naming plainly rather than hiding in a footnote: **Pushword is still on
`1.0.0-rc` after 1,174 releases**, English and French only, and targets SQLite and MariaDB
rather than TYPO3's four database engines. If any of those is a blocker, it is a blocker.

---

## Trying it

The reasonable test is an afternoon, on a real site rather than a demo, ideally a small one
you currently maintain on TYPO3 and resent invoicing for:

```shell
composer create-project pushword/new pushword "^1.0.0-rc"
```

Then check the exit before you commit to the entrance. Import a few pages, run
`pw:flat:sync`, and look at what lands in your content directory. If those Markdown files
are something you would be comfortable owning after we disappeared, the sustainability
question is answered — not by our promises, but by your filesystem.

---

## Resources

- **TYPO3**: [typo3.org](https://typo3.org) · [docs.typo3.org](https://docs.typo3.org) · [github.com/TYPO3/typo3](https://github.com/TYPO3/typo3)
- **Pushword**: [pushword.piedweb.com](https://pushword.piedweb.com) · [github.com/Pushword/Pushword](https://github.com/Pushword/Pushword) · [architecture](/architecture) · [extensions](/extensions) · [getting help](/pro)
- Related: [Astro vs Pushword](/blog/astro-vs-pushword) · [CMS comparison — WordPress, Statamic, Sulu](/blog/cms-comparison)

> [!note] About this comparison
>
> Written by the Pushword author (and Claude). We are obviously not neutral, and TYPO3 is
> a vastly more established project with a governance body, a security team and a
> commercial ecosystem Pushword does not have. Every TYPO3 figure quoted here was
> measured directly from the `v14.3.5` tag (14 July 2026) and is reproducible
> with `find` and `wc -l`; support dates and ELTS pricing come from
> TYPO3's own announcements. Pushword claims describe shipped features, not roadmap.
>
> Found an error, or think we have been unfair to TYPO3? [Open an issue](https://github.com/Pushword/Pushword/issues) — corrections are welcome.

> [!warning] Version
>
> Last updated: August 2026. Reflects TYPO3 v14.3.5 (v14 LTS, released 21 April 2026,
> supported until 2029) and Pushword `1.0.0-rc` as of August 2026. TYPO3 v15
> is in development with an LTS projected for autumn 2027.
