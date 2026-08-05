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
Deutsche Telekom, Mercedes-Benz, Lufthansa and the FAO, it has a funded association behind
it, a published security process, an 18-month LTS cadence and a support promise that runs
to 2029. Almost nothing else in PHP can say all of that at once.

This page is not an argument that TYPO3 is bad. It is an argument that TYPO3 is **sized for
a particular kind of organisation**, and that if your project is not that organisation, you
are paying for a great deal you will never open.

It is written for someone who already knows what TCA is, has debugged a TypoScript object
path at 11pm, and has an opinion about Extbase. If that is you, you can skip the vocabulary
lessons — this page uses yours.

## The short answer

**Stay on TYPO3 if** several editors need genuinely different permissions, you need
workspaces and a publishing workflow, you have TYPO3 extensions you depend on, or your
client's procurement process requires a vendor, a certification and a paid support contract
with an SLA. Nothing here replaces any of that, and pretending otherwise would waste your
time.

**Look at Pushword if two or more of these describe you:**

- You use maybe 20% of TYPO3 and maintain 100% of it
- Hiring a TYPO3 developer takes months; hiring a Symfony developer takes weeks
- Your last major upgrade cost more than the feature work that year
- Editors want a change live now, not after a deployment window
- You want content in git, reviewable as a diff, not in `tt_content` rows
- AI agents are starting to write or maintain part of your content
- You run a fleet of small-to-medium client sites where TYPO3 is a cannon aimed at a fly

If you recognised yourself in the first paragraph, close this tab with our respect. If you
recognised yourself in the second, the rest of this page is the detail.

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
| **Databases**            | MySQL, MariaDB, PostgreSQL, SQLite                                     | SQLite (default), MariaDB                                       |
| **Tests**                | 1,481 test files                                                       | 402 test files, ~3,000 test methods                             |
| **Support model**        | LTS to 2029, then **paid ELTS**                                        | Rolling `1.0.0-rc`, 1,174 releases, no LTS                      |
| **Ecosystem**            | 5,000+ TER extensions, 342,000 Packagist downloads/month               | 25 first-party packages, 640 downloads/month                    |
| **Governance**           | TYPO3 Association, **€1M budget in 2026**; TYPO3 GmbH sells ELTS       | One developer, MIT, no funding                                  |

Read the last two rows carefully, because they are the honest reason most people should
stay where they are. TYPO3's ecosystem is roughly **500× larger** by download volume, it
has a security team and a coordinated advisory process, and it has a commercial channel of
certified agencies. Pushword has none of that.

### "So why haven't I heard of it?"

The unflattering answer: Pushword was built to run its author's own client sites and has
been doing that since 2018. There was never a launch, a funding round, a conference track
or a growth team. Features got written when a real site needed them.

That cuts both ways. It means a tiny community, no agency network, no certification, and
one person to call — a real cost and the main reason to stay on TYPO3. It also means every
feature described below runs in production somewhere rather than in a demo, and the roadmap
has never been shaped by an adoption target.

---

## The structural difference: records versus documents

Not PHP versions, not "modern versus legacy". Both projects sit on Symfony components and
Doctrine. The real divergence is **what a page is**.

**In TYPO3, a page is a graph of typed records.** A `pages` row, a tree of `tt_content`
rows, `sys_file_reference` rows into FAL, translation rows, version rows. Every field is
described in TCA, and every write goes through `DataHandler` — 9,737 lines in a single
class that enforces permissions, references, history, translation handling and workspace
versioning in one pass. It is genuinely impressive engineering, and it is the reason a
TYPO3 editor can be given permission to edit one field of one record type in one branch of
the page tree.

**In Pushword, a page is a document.** One `Page` Doctrine entity, body in Markdown,
structure in front matter. Extra fields are declared in configuration rather than in a
schema-plus-TCA-plus-SQL triple.

Everything else in this comparison follows from that one choice. TYPO3 is 6.5× larger
because modelling structured content properly *costs* that much. Pushword is small because
it declined the problem.

---

## What actually disappears

This is the section for a TYPO3 developer, because the honest pitch is not "new features"
— it is **subtraction**.

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
on day one, because there is nothing Pushword-specific to learn first — the stack *is*
Symfony, Doctrine and Twig. That is not a small claim for an agency: **you are hiring from
the Symfony pool rather than the TYPO3 pool**, and those two pools are not the same size or
the same price.

The mirror image is equally true. Everything in the left column exists for a reason, and
some of those reasons will be yours. Read the next section before getting excited.

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
per-field permissions, workspace-aware translation behaviour, a custom form element. If you
need those, you need TCA, and TCA is 279 files for a reason.

---

## Where TYPO3 is genuinely the better choice

Said at length, because for a lot of readers it will be true.

- **Workspaces and editorial workflow.** 10,344 lines of sysext implementing draft
  workspaces, preview links, staged publishing and dependency resolution between records.
  Pushword has page [versioning](/extension/version) and publication holds. That is not the
  same thing and will not become the same thing.
- **Granular permissions.** `BackendUserAuthentication` is 2,219 lines: page-tree mounts,
  per-table and per-field access, group inheritance. Pushword has five flat roles.
  Multi-editor scoping is on the roadmap, and it is honest to call it a gap rather than a
  difference.
- **The extension ecosystem.** 5,000+ extensions in the TER. If your site depends on a
  solved problem someone already published, that is decisive and nothing on this page
  argues otherwise.
- **Upgrade tooling.** 22 upgrade wizards and an **Extension Scanner carrying 17,633 lines
  of matchers** that statically flags removed API calls in *your own* extensions before you
  upgrade. This is TYPO3's most underrated asset. Pushword's equivalent is prose upgrade
  notes in `vendor/pushword/docs/content/upgrade/`, and that is a real downgrade.
- **A support promise you can put in a contract.** v14 LTS is supported to 2029, with paid
  ELTS after. Pushword offers no LTS and no SLA. For public-sector procurement this alone
  ends the conversation.
- **Internationalisation at scale.** 432 XLIFF files and a Crowdin workflow, against
  Pushword's English and French.
- **Infrastructure choice.** MySQL, MariaDB, PostgreSQL and SQLite; 13 cache backends
  including Redis and Memcached. Pushword targets SQLite and MariaDB, and leans on static
  output instead of a cache tier.
- **You are not the only maintainer.** The TYPO3 Association ran a **€1,000,000 budget in
  2026**. Pushword's bus factor is one. This is the single most important line in this
  document and no feature comparison outweighs it.

---

## Where Pushword is the better choice

- **You use a fraction of TYPO3.** If you have never opened workspaces, never built a BE
  user group more complex than "editor", and never written an Extbase extension, you are
  maintaining 593,000 lines to use perhaps a tenth of them. Every upgrade prices in all of
  it.
- **Publishing is instant.** An editor saves and that page re-renders. No deployment window,
  no cache-warming ritual.
- **Content lives in git as Markdown.** [Flat](/extension/flat) mirrors every page to a
  Markdown file with YAML front matter, and the admin keeps working. Content changes become
  reviewable diffs, and rollback is `git revert`. TYPO3 has `impexp`, but content is
  fundamentally DB-bound; there is no equivalent workflow and there cannot easily be one.
  See the [git-integrated content workflow](/extension/flat-git-workflow).
- **The whole site can go static.** [`pw:static`](/extension/static-generator) exports the
  site as HTML for Apache, GitHub Pages or FrankenPHP, incrementally and in parallel. TYPO3
  has no core equivalent.
- **One installation, many hosts and locales.** A fleet of client sites sharing templates,
  media and code, from one codebase and one admin.
- **AI agents are first-class clients.** Many `pw:*` commands detect an agent and emit a
  single compact JSON line instead of progress bars — see
  [agent-optimized output](/agent-output). The [REST API](/extension/api) is
  OpenAPI-described, `pw:schema:dump` hands an agent the content model, and
  `vendor/pushword/docs/CLAUDE.md` ships instructions for the agent working on *your* site.
  TYPO3 v14's AI work is editor-facing UX; this is a different bet, and it is currently
  Pushword's sharpest edge.
- **No infrastructure to provision.** SQLite by default, no migration files, no cache
  server. A site is a directory and a `.db` file you can copy.
- **Everything else ships as maintained bundles.** [Admin](/extension/admin),
  [search](/extension/search) (Loupe, no search server),
  [forms and comments](/extension/conversation), [newsletter](/extension/newsletter),
  [dead-link scanning](/extension/page-scanner), [redirections](/extension/flat),
  [snippets](/extension/snippet), [REST API](/extension/api) — versioned and tested
  together in one monorepo.

---

## Where the two agree more than you would expect

Both projects arrived independently at several of the same conclusions, which usually means
the conclusions are right.

| Idea                                | TYPO3 v14                                          | Pushword                                             |
| ----------------------------------- | -------------------------------------------------- | ---------------------------------------------------- |
| Symfony components underneath       | Symfony 7.4 LTS components                         | Full Symfony 8 application                           |
| PSR-14 events over magic hooks      | 287 event classes                                  | Events, entity filters and tagged providers          |
| Typed schema over untyped arrays    | The v13/v14 Schema API over TCA                    | `PagePropertySchema` + validator constraints         |
| Site configuration as YAML          | `config/sites/*/config.yaml`                       | `pushword.apps` configuration                        |
| Server-rendered HTML, JS opt-in     | Fluid, no mandatory frontend framework             | Twig, JS opt-in per component                        |
| Structured content over a WYSIWYG blob | Content elements                                | Markdown + declared properties + [snippets](/extension/snippet) |

The PSR-14 convergence deserves a footnote in TYPO3's favour and against it at once: 287
event classes is real modernisation work, and **42 files still read
`$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']`**. A decade into the migration, the old hook
system is still load-bearing. That is what backward compatibility costs at TYPO3's scale,
and it is precisely the cost a 91,000-line project does not have to pay.

---

## Year three: what maintenance feels like

The question that actually decides platform choices, and the two projects fail in opposite
directions.

|                        | TYPO3                                                | Pushword                                        |
| ---------------------- | ---------------------------------------------------- | ----------------------------------------------- |
| Release cadence        | Major every ~18 months, LTS supported ~3 years       | Rolling `1.0.0-rc`, continuous                  |
| When support ends      | Buy ELTS or upgrade — v12's free support ended 30 April 2026 | Not applicable; there is no LTS to expire |
| Upgrade help           | 22 wizards + Extension Scanner + documented breaks   | Prose upgrade notes per release                 |
| Third-party breakage   | Extensions must follow each major                    | No third-party ecosystem to break               |
| Therefore the risk is  | **Cadence** — a budgeted migration on a clock        | **Concentration** — one maintainer, less choice |

**TYPO3's risk is the treadmill.** Every 18 months a major arrives, and the upgrade is real
work: TCA migrations, deprecated API calls, extensions that need their own upgrades or
replacements. TYPO3 handles this better than any comparable project — the tooling exists,
the breaks are documented, and ELTS gives you an escape hatch you can pay for. But it is a
recurring, non-optional line in the budget, and for a small site it can exceed everything
else spent that year.

**Pushword's risk is concentration.** There is one maintainer. The 18 bundles are released
and tested together so "does the newsletter package work with this admin version?" is a
question CI answers rather than you, and Symfony's own LTS cadence sits underneath. But if
a capability is missing you write it, and if the author stops you are maintaining a Symfony
bundle set. That last point is meant literally, not as reassurance — read the next section
before deciding whether it is acceptable.

It would also be dishonest to skip this: **Pushword is still on `1.0.0-rc` after 1,174
releases.** If your procurement or your own instinct reads that as "not ready", that is a
reasonable reading and we are not going to argue you out of it.

---

## What it costs to be wrong

A smaller project should be judged on its exit cost, not on a feeling. You can verify every
line of this before committing anything:

- **Content** is Markdown with YAML front matter in your own git. Migrating it to Astro,
  Hugo, Eleventy or a headless CMS is largely a directory copy. Migrating *out of* TYPO3
  means writing an exporter against `tt_content`, FAL and the translation model — you have
  probably already priced that job.
- **The database** is a SQLite file you own, or your own MariaDB. No hosted service holds
  anything.
- **Templates** are Twig and the application is a standard Symfony app. There is no
  proprietary rendering layer to reimplement.
- **Media** are ordinary files on disk.
- **The licence** is MIT and the whole monorepo is public.

The asymmetry is worth naming. TYPO3 is far safer to *choose* and considerably more
expensive to *leave*. Pushword is riskier to choose and nearly free to leave. Which of
those matters more depends on how confident you are about the next five years.

The reasonable test is an afternoon:

```shell
composer create-project pushword/new pushword "^1.0.0-rc"
```

Point it at one real client site — ideally a small one you currently maintain on TYPO3 and
resent invoicing for — and see how far you get before you miss something.

---

## Choosing

The questions that actually decide it:

1. **Do you need per-editor, per-record permissions?** Yes → TYPO3, without hesitation.
   Pushword's five roles are not going to become an ACL.
2. **Do you need workspaces or a staged publishing workflow?** Yes → TYPO3.
3. **Do you depend on TER extensions?** Count them honestly. Even two or three can decide
   this.
4. **Does a contract require an SLA, a certification, or support until a named year?**
   → TYPO3, and ELTS exists for exactly this.
5. **What fraction of TYPO3 do you actually use?** If the honest answer is "pages, content
   elements, a couple of extensions and one editor", you are paying a great deal of rent
   for an empty building.
6. **Who will you hire?** Symfony developers are abundant; TYPO3 developers are not. Over a
   five-year site this outweighs most technical arguments above it.
7. **How fast must a published change go live?** Seconds → Pushword. A deployment window is
   acceptable → either.
8. **Should content be reviewable as a git diff?** Yes → Pushword's [flat mode](/extension/flat)
   is the whole point of it.

If your answer to 1–4 is "yes" anywhere, stay on TYPO3 — it is the better tool for that job
and this page has not tried to claim otherwise. If your answers cluster in 5–8, spend the
afternoon.

---

## Resources

- **TYPO3**: [typo3.org](https://typo3.org) · [docs.typo3.org](https://docs.typo3.org) · [github.com/TYPO3/typo3](https://github.com/TYPO3/typo3)
- **Pushword**: [pushword.piedweb.com](https://pushword.piedweb.com) · [github.com/Pushword/Pushword](https://github.com/Pushword/Pushword) · [architecture](/architecture) · [extensions](/extensions)
- Related: [Astro vs Pushword](/blog/astro-vs-pushword) · [CMS comparison — WordPress, Statamic, Sulu](/blog/cms-comparison)

<div class="not-prose p-4 mb-8 bg-blue-50 dark:bg-blue-900/30 rounded-lg shadow">
  <p class="text-sm text-blue-800 dark:text-blue-200">
    <strong>About this comparison</strong><br>
    Written by the Pushword author (and Claude). We are obviously not neutral, and TYPO3 is
    a vastly more established project with a community, a governance body and a commercial
    ecosystem Pushword does not have. Every TYPO3 figure quoted here was measured directly
    from the <code>v14.3.5</code> tag (14 July 2026) and is reproducible with
    <code>find</code> and <code>wc -l</code>; ecosystem figures come from Packagist,
    W3Techs and TYPO3's own published budget. Pushword claims describe shipped features,
    not roadmap.<br>
    <span class="text-xs">Found an error, or think we have been unfair to TYPO3? <a href="https://github.com/Pushword/Pushword/issues" class="underline">Open an issue</a> — corrections are welcome.</span>
  </p>
</div>

<div class="not-prose p-4 mt-8 bg-amber-50 dark:bg-amber-900/30 rounded-lg border border-amber-200 dark:border-amber-800">
  <p class="text-sm text-amber-800 dark:text-amber-200">
    <strong>Version</strong><br>
    Last updated: August 2026. Reflects TYPO3 v14.3.5 (v14 LTS, released 21 April 2026,
    supported until 2029) and Pushword <code>1.0.0-rc</code> as of August 2026. TYPO3 v15
    is in development with an LTS projected for autumn 2027.
  </p>
</div>
