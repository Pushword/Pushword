---
title: 'the newsletter bounce reader no longer takes an address off the list on anybody''s say-so, a Docker container no longer adds a default-credential super admin to a restored database, nor starts from a var/cache built somewhere else, a campaign translation PATCH merges per field, a template named by page content or a snippet, but missing, no longer 500s the page, a page-scanner directive shown in a code sample no longer silences the page showing it, a blockquote opening on `> [!label]` renders as a notice, a clickable card follows its own title again, a horizontal list whose cards all fit stops fading its edges and drawing dots, a media uploaded after the pages naming it gets its usage rows, and a fenced code block holding a blank line stays one block in the editor'
run:
    - 'pw:media:usage:rebuild'
publishedAt: '2099-01-01 00:00'
parentPage: upgrade
---

**Concerns:** `pushword/admin`, `pushword/admin-block-editor`, `pushword/core`, `pushword/newsletter`, `pushword/page-scanner`, `pushword/snippet`, `@pushword/js-helper`

## Newsletter: anybody could take any address off the list, if you read bounces

**Affects sites that set `newsletter.bounce_maildir` or `newsletter.bounce_imap_dsn` and
ran `pw:newsletter:bounces`, on rc832 through rc844.** A site that never configured the
bounce reader was never exposed.

The bounce mailbox is the envelope sender: a real address, published in the `Return-Path`
of every newsletter, which by design accepts mail from any server on the internet. The
reader decided a message was a bounce from its own `Content-Type: multipart/report`, and
took the address to drop out of the `Final-Recipient` that message named. Nothing tied
the report to a mail this install had sent. So a message written by hand to
`bounce@example.com`, naming `Final-Recipient: rfc822; someone@example.com` with
`Action: failed` and `Status: 5.1.1`, made the next run mark that address `Bounced` on
every audience it belonged to, stop its automation enrollments and charge the bounce to
the last campaign. One message could name as many addresses as its writer liked.

It is terminal by design: `resubscribe()` refuses to revive a bounced contact and the
admin hides the action, because a click says nothing about a mail server's refusal. The
`--notify` recap reported the whole thing as ordinary attrition.

A report must now say **which message** failed, and that message must be one this install
issued. Every newsletter goes out with a signed `Message-ID`
(`<nl.<nonce>.<hmac>@example.com>`, keyed on `APP_SECRET`); a delivery report returns a
copy of the message it failed to deliver; and a `Final-Recipient` is honoured only when
one of the ids in that copy recomputes for that same address. Nothing to store, nothing
to configure — see
[Bounces](/extension/newsletter#only-reports-about-mail-this-site-sent).

**Audit what was dropped while the reader was open.** A bounce recorded before this
release cannot be told apart from a forged one, so look at the list rather than the code:

```shell
php bin/console dbal:run-sql \
    "SELECT id, email, bounced_at FROM newsletter_contact WHERE bounced_at IS NOT NULL ORDER BY bounced_at"
```

An address you have reason to think is alive was opted in by a person, so put it back the
way they did — a fresh opt-in, not an `UPDATE`. `resubscribe()` refuses a bounced contact
on purpose; `POST /api/newsletter/contact` re-subscribes one properly.

Two things to expect on the first runs after upgrading:

- **Bounces for mail sent before the upgrade no longer verify**, those messages carrying
  no signed id. They are counted `unverified` and left alone, so an address that died
  during that window stays subscribed until a later campaign bounces it again.
- **`unverified` climbing on new sends means your relay returns no copy of the message it
  failed to deliver** — RFC 3464 only recommends it — and then no bounce is acted on at
  all. `pw:newsletter:bounces --dry-run` over a mailbox holding real bounces is how to
  tell that apart from a quiet mailbox.

The command's report gains that `unverified` count, in the console output, in the
`--notify` mail and in the `--format=agent` JSON.

## Docker: check your instance for an `admin@example.tld` you did not create

**If you run the Docker image on a restored database, audit your accounts.**

The entrypoint seeds one super admin when `var/.pushword-seeded` is absent, and decided
"this database already has an account" from `pw:user:create` *failing*. The only thing
that fails it is the unique constraint over `email`. So the check worked for a database
whose admin is `admin@example.tld` — and for no other. Restore a backup whose admin is
your own address and the insert succeeded: a second `ROLE_SUPER_ADMIN`, on the published
default credentials `admin@example.tld` / `p@ssword`, with an API token, announced by a
single log line.

The documented backup makes this the normal path rather than a corner case: it copies
`app.db` alone, so a restore into a fresh volume has no marker to suppress the seeding.

The entrypoint now asks the database whether it holds any user before creating one. To
check an instance you already booted this way:

```shell
docker compose -f compose.prod.yaml exec pushword \
    php bin/console dbal:run-sql 'SELECT id, email, roles FROM user'
```

Delete an account you do not recognise with
`php bin/console pw:user:delete <email>`. Its API token dies with it. If you cannot rule
out that it was used, treat the instance as compromised: rotate the tokens of the
accounts you do keep.

If you have edited `docker/docker-entrypoint.sh` by hand, replace the seeding block's
condition — the guard is the database, not the insert:

```sh
if php bin/console dbal:run-sql "SELECT 'PW_HAS_USER' AS marker FROM user LIMIT 1" 2>/dev/null | grep -q PW_HAS_USER; then
	echo '~~ No account created: this database already has one.'
elif php bin/console pw:user:create …
```

## Docker: the entrypoint drops a `var/cache` built elsewhere

A compiled Symfony container holds absolute paths — `%kernel.project_dir%/var/app.db`
is baked as wherever the project stood when the cache was written. In production `var/`
is a volume, so it commonly arrives from somewhere else: a restored backup, or the
project `composer create-project` built on the host. Booting on such a volume made every
path point at a directory the container does not have, and the container died on the
entrypoint's schema update with an opaque `SQLSTATE[HY000] [14] unable to open database
file`.

The entrypoint now empties `var/cache` before its first console call. Nothing to do if
you let `pw:docker:init` manage the file; if you have edited `docker/docker-entrypoint.sh`
by hand, copy the new block in — it goes above the `doctrine:schema:update` line:

```sh
if [ -d var/cache ]; then
	find var/cache -mindepth 1 -delete
fi
```

Development is unaffected: `compose.yaml` already keeps the container's `var/cache` in
its own volume, for the same reason.

## Newsletter: a campaign `translations` PATCH now merges per field

`PATCH /api/newsletter/campaign/{id}` merged `translations` per locale: the entry it
named was replaced by the fields the request carried, so
`{"translations": {"de": {"subject": "Hallo!"}}}` silently dropped the German body and
the campaign then mailed a German subject over the default-language body. It now merges
per field, like `customProperties` merges per key — a field the request leaves out keeps
its stored value.

If a caller relied on the replacement to clear a field, send that field as `""`; blanking
every field of a locale drops the locale, as does `{"de": null}`. The drop also matches
the way locales are stored — lowercased, dashes for underscores — so `{"DE": null}` now
removes `de` instead of answering `200` and removing nothing.

## Core: a template named by page content or a snippet, but missing, no longer 500s

Page content that names a template it cannot load — most easily
`pages_list(view: 'foo')`, which resolves a bare name to
`/component/pages_list_foo.html.twig` by convention — used to take the whole public page
down with a 500. It now degrades like any other content-render error: the block renders
as an invisible marker, a warning is logged, `pw:page-scan` reports it and a logged-in
editor sees a badge on the page. Snippet content, which had the same gap, degrades the
same way — one snippet used site-wide no longer 500s every page embedding it.

So a variant you rename or delete, and a page flat-synced to a host whose templates never
had it, now fail quietly where they used to fail loudly. If you have been relying on 500s
to catch them, watch instead:

```shell
php bin/console pw:page-scan
```

## Page scanner: a directive shown in a code sample no longer silences the page

`<!-- page-scanner-ignore: … -->` and `<!-- page-scanner-ignore-link: … -->` were read
from the raw content, code samples included. A page documenting the syntax was therefore
applying it: the fenced block showing `page-scanner-ignore: image-alt-missing` silenced
that finding for the whole page. Both directives are now read outside fenced blocks and
backticks only.

So a page that quotes the syntax — the scanner's own documentation, an editorial guide —
may report findings it was silencing by accident. Run the scan once and look at those
pages first:

```shell
php bin/console pw:page-scan
```

If a page really meant to silence something, move the comment out of the sample. To name
a URL containing a comma, which used to be impossible since the comma separates two
patterns, escape it: `<!-- page-scanner-ignore-link: https://maps.example/@45.1\,4.5* -->`.

## Page scanner: a dead external link is re-checked after an hour, not a day

External URL verdicts were all cached for `external_url_cache_ttl` (24h by default),
successes and failures alike, with nothing to clear them. A link fixed in the morning
kept being reported until the next day, and a single scan run without a working
connection reported the whole corpus as dead for just as long.

Failures now expire on their own, shorter TTL, and the whole pool can be dropped for a
run:

```yaml
pushword_page_scanner:
  external_url_failure_cache_ttl: 3600   # default; capped by external_url_cache_ttl
```

```shell
php bin/console pw:page-scan --recheck
```

Nothing to set: the default applies to sites that never configured the cache. Set it to
`external_url_cache_ttl` to keep the previous behaviour.

## Core: a blockquote opening on `> [!label]` now renders as a notice

Markdown gained notices — the DocFX / GitHub alert syntax, with a free label and an
optional title:

```markdown
> [!warning] Version
>
> Last updated: August 2026.
```

Nothing to run, but content already holding that syntax changes appearance: a blockquote
whose **first line** is such a marker now renders through
`/component/notice.html.twig` instead of showing `[!NOTE]` as text — most often a page
pasted from a GitHub README. To keep the literal text, escape the bracket:

```markdown
> \[!NOTE] this stays a quotation
```

The label is free (`note`, `tip`, `important`, `warning` and `caution` ship with a
palette, anything else renders neutral) and the wrapper carries `notice notice-<label>`,
so a theme can style its own. Override the component to change the palette or the markup.
Sites building their stylesheet from the `app.css` shipped by `@pushword/js-helper` are
covered; a custom entry point needs the vendor `@source` line documented in
[manage assets](/manage-assets), or the notice renders unstyled.

## A `.clickable` box follows the link you mark, not its last one

A `.clickable` box became pure CSS at rc835: every link it holds stretches a
pseudo-element over the whole box. Stretched that way they are coincident, and the hit
test keeps the last of them in DOM order — so a card whose description holds a link, any
markdown link, has been leading there from everywhere since, its own title included. The
title itself was unreachable.

Only the link marked `.clickable-link` stretches now, and the other links in the box keep
their own area, whatever the DOM order puts first. Core's `component/card.html.twig` marks
its title link, so a site rendering its cards through the bundle has nothing to do beyond
rebuilding its assets (`yarn build` / `npm run build`).

Mark the link yourself in two cases:

- a `card.html.twig` you override — its title link becomes
  `link(title, link, {class: 'clickable-link'}, obfuscateLink)`;
- any `.clickable` box of your own holding more than one link (a second link in the body, a
  button): give the class to the one the box follows. An obfuscated link takes it too — the
  class rides on the `span[data-rot]` and onto the `<a>` it becomes.

A box holding a single link keeps working with no marker at all.

## A horizontal list whose cards all fit no longer fades its edges, nor draws dots

`pages_list(view: 'horizontalScroll')` faded both edges of a row with nothing behind
either, and — with `horizontal-scroll-dots` — drew a row of position dots under a list
already visible in full. One cause for both: the fade and the dots' fill are scroll-driven
animations, a scroll timeline is *inactive* where there is nothing to scroll, and an
animation on an inactive timeline contributes no value at all, so each property fell back
to the value written for a browser without the feature. The arrows were right all along —
they read `:disabled` — and the fade and the dots now go quiet with them.

Nothing to run beyond rebuilding your assets (`yarn build` / `npm run build`). A site that
copied `.horizontal-scroll` into a stylesheet of its own wants the three lines that carry
it in `utility.css`: the two `--horizontal-scroll-fade-*: 0px` declarations beside the fade
animations, and `scroll-marker-group: var(--horizontal-scroll-markers)` in place of a
constant `after`.

## Core: a media uploaded after the pages naming it now gets its usage rows

`media_usage` was written on page writes only, and a reference resolves against the media
that exist *at that moment*. So a media created afterwards got no row, and nothing ever
went back for it. Two ordinary flows land there:

- a page saved naming `photo.jpg` before `photo.jpg` is uploaded;
- a media deleted and re-uploaded corrected under the same name — new id, same filename,
  and the pages keep rendering it.

The media then read as referenced by no page. That is the list
`pw:media:clean-unused --force` deletes, entity and file, and the "used on" panel on the
media screen gave the same wrong answer.

Media are tracked on their own now: at the end of a flush that created any, the pages
naming those files go back through extraction. Collected per flush rather than per media,
so a bulk upload costs a handful of scans instead of one per file.

**Your existing rows are not repaired by the upgrade** — nothing knows which media lost
theirs. Rebuild once:

```bash
php bin/console pw:media:usage:rebuild
```

Until you do, keep reading `pw:media:clean-unused` as the dry run it defaults to. If you
have already run it with `--force` since rc835, compare its output against your backup
before assuming the deletions were all correct.

## Block editor: a fenced code block holding a blank line is one block again

The editor cuts markdown into blocks on blank lines, and did so without knowing where a
code fence starts or ends. A fenced block holding a blank line was therefore cut in two,
and since the halves are classified independently, a line like `## Step` inside the code
was read as a real heading — the outline rail listed a section that does not exist, whose
span owned the closing ```` ``` ````. Deleting or moving that phantom section from the
rail took the closing fence with it and left the rest of the page inside an open fence.

Nothing to run. A fence is now atomic to the editor, as it already was to the renderer:
CommonMark rules, so up to three spaces of indent, a closing run at least as long as the
opening one, and an unclosed fence running to the end of the document. Content already
holding such a fence opens as a single code block where it used to open as several
blocks — a page saved from the old split kept whatever the split produced, so check any
page whose code samples contain blank lines.

Rail edits also stop rewriting blank lines they did not touch: deleting one block used to
rejoin the whole field with exactly one blank line between blocks, normalising every
other separator in the file. The document's own spacing is preserved now, which keeps a
flat-file site's diffs to the block that actually changed.

<!--
The upgrade note for the next release. `.scripts/release` renames this file to
`upgrade/rc<N>.md`, adds its row to the table in `upgrade.md` and empties it back
to this scaffold, at the tag.

Write here, in the same commit as the change, whenever a release asks something of
a site that upgrades: a command to run, a config key to set, a template to copy, a
behaviour that changed under an unchanged call. A change `composer update` fully
absorbs needs no note.

- `title:` — the "What changed" cell of the index table. One line, lower case,
  written from the site's side ("the newsletter form is fetched, and CSRF-protected")
  rather than the diff's ("refactor NewsletterFormController"). Required as soon as
  the note has a section; the release stops if it is still empty.
- `run:` — the command(s) the release expects, without `php bin/console`. Omit the
  key when there is none. A list runs in the order given.
- `**Concerns:**` — first line of the body, listing every package a site has to
  install to be affected. Alphabetical, full composer names, `@pushword/js-helper`
  last. Add the packages your change touches to the line, keep the others.
- One `##` section per change, saying what breaks and what to do about it.

Several changes land here between two tags: append to the file, do not replace it.
-->
