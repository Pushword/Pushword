---
title: 'Pushword Support — Getting Help, and Paid Assistance'
h1: 'Getting help'
publishedAt: '2025-12-21 21:55'
toc: true
---

Pushword is MIT-licensed and costs nothing. Nothing on this page is required to run it.

The page exists to answer one question plainly: **when something breaks, who fixes it?**
Four answers, cheapest first.

## Read the documentation

It ships with the release you installed. The docs live in the same repository as the code
and land in `vendor/pushword/docs/content/`, so they describe your version, not the
latest one.

- [Architecture](/architecture) — bundle map, package table, development environment
- [Extensions](/extensions) — what each bundle does, and its extension points
- [Installation](/installation) — requirements, FrankenPHP, Docker, static hosting
- [Upgrade guide](/upgrade) — one note per release, with the migration steps

`php bin/console list pw` prints every command your install actually has.

## Ask your coding agent

This is the rung most CMS projects cannot offer, so it is worth being specific about why
it works here.

- Every install carries `vendor/pushword/docs/CLAUDE.md`: entity map, content structure,
  commands, conventions, quality gates. Point your project's `CLAUDE.md` or `AGENTS.md` at
  that file and an agent starts oriented instead of guessing.
- The `pw:*` commands detect when an agent is running them and emit one compact JSON line
  instead of progress bars and colours. See [agent output](/agent-output).
- Content is Markdown with YAML front matter and templates are plain Twig, so an agent
  edits your site with the tools it already has, without learning a proprietary field
  format. With [Flat](/extension/flat), it edits files in Git.
- Pushword's source is roughly 89,000 lines of PHP. That is small enough for an agent to
  read the part that concerns your bug in full.

Ecosystem size is normally the support argument: more users, more answers already written
down. The inverse works too. A codebase an agent can read end to end needs fewer answers
written down in advance.

## Open an issue

For bugs, regressions, and questions the documentation does not cover, use the #[{{ svg('github') }} issue tracker](https://github.com/Pushword/Pushword/issues).

This is best-effort and free. Maintainers give their time; a clear reproduction case gets
a far better answer than a bug report that does not have one.

**Security issues do not go there.** Report them privately to
{{ mail('contact@piedweb.com') }}, as described in #[SECURITY.md](https://github.com/Pushword/Pushword/blob/main/.github/SECURITY.md).

## Pay for help

The channels above are best-effort and have no deadline. This one has both a deadline and
a scope.

**First response within two business days.**

**Robin, who wrote Pushword, does the work.** There is no tier-one triage and no ticket
routing. The person who answers your mail is the person who wrote the line you are asking
about.

What it covers:

- Advanced installation and deployment, including FrankenPHP, Docker and static hosting
- Migrating an existing site into Pushword
- Bespoke development: custom extensions, entity filters, templates, admin behaviour
- An architecture or SEO review of a site already running on Pushword

**Rate:** $160 per hour. Anything larger than an afternoon is quoted as a fixed price
before work starts.

**Ask:** {{ mail('contact@piedweb.com') }}

### What this does not promise

Worth stating, because vague support pages are how projects lose the trust they were
trying to buy.

- The two-day window covers the **first response**, not the fix.
- There is no 24/7 rota and no out-of-hours escalation. One person cannot honestly sell
  one.
- Bespoke work is scheduled, not on demand. A request can be declined or queued.

What replaces the promises is deliberately not us.

**You are not hiring from a list of one.** Pushword is Symfony, Doctrine and Twig with no
proprietary runtime on top, and the admin is EasyAdmin. The people who can take over your
site are every Symfony developer, not every *Pushword* developer, and that is a market with
a going rate rather than a single phone number.

**And the licence is the floor under all of it.** Your content is Markdown, your templates
are Twig, your data is in a database you own, and the code is MIT. If this arrangement
stops working for you, you are not locked in — the limits of that portability are set out
honestly in [the TYPO3 comparison](/blog/typo3-alternative).

## Work with Pushword professionally?

Freelancers and agencies building on Pushword can be listed here. Open a pull request
adding yourself to `packages/docs/content/pro.md`, with a link to at least one production
site running Pushword. See [contribute](/contribute).

The list is empty for now. It is an open call, not a directory that emptied out.

## Support the project

Pushword takes no money to use and has no commercial edition. If it saves you time and you
want to keep it maintained, one-off or recurring support goes through #[Liberapay](https://liberapay.com/RobinPiedWeb).
