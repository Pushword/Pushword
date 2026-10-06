---
title: 'Newsletter, segments and mailing automation for Pushword CMS'
h1: Newsletter
publishedAt: '2026-07-27 10:00'
toc: true
---

Collect contacts, record their consent, broadcast to segments, drip sequences and
mail your readers when you publish — without a CRM, a worker or a third-party ESP.

- **Audience** — a mailing list and the scope of consent. One per brand.
- **Contact** — a person in one audience: tags, free-form custom properties, and
  when and where they opted in.
- **Campaign** — one broadcast, to the whole audience or to a segment.
- **Automation** — a sequence of mails started by a *trigger source*: a contact
  coming to match a rule, a page being published, or a source your bundle
  registers.

At runtime the only moving part is `pw:newsletter:tick`, run from cron.

## Getting started

Create an audience in **Newsletter → Audiences**: a slug, the host its public
links belong to, a sender identity, the interests the public form may attach and,
optionally, an analytics source. Then drop the form into a page:

```twig
{{ newsletter_form('altimood') }}
{{ newsletter_form('altimood', ['AmTrek']) }}
{{ newsletter_form(['altimood', 'altimood-promos']) }}
{{ newsletter_form('altimood', [], 'footer') }}
```

The form asks for a name and an email. Given several audiences it stays one form,
and one submission opens one subscription per list, each with its own
confirmation mail where the list requires one. An unknown slug fails the whole
submission.

The call renders a placeholder; the form is fetched from the live host when the
page loads, so nothing per-visitor (above all the CSRF token) is baked into a
statically generated page. `@pushword/js-helper` does both steps: `liveBlock()`
swaps the placeholder for the form fetched from its `data-live` URL, then binds
the returned `.live-form`, posts it in the background and replaces it with the
response. **Without js-helper, no form appears.**

The third argument names where the form sits and is stored on the contact as the
opt-in source. It defaults to the slug of the page the form was rendered on, so
name two forms on one page apart.

`newsletter_form_url()` takes the same arguments and returns the URL alone, for a
front end that fetches the form itself — typically a modal that loads it when
opened rather than on every page view. It returns null when no slug matches an
audience (where `newsletter_form()` renders an empty string).

```twig
{{ newsletter_form_url('altimood', [], 'footer') }}
```

Finally, add the tick to the server's crontab:

```shell
* * * * * cd /path/to/app && php bin/console pw:newsletter:tick
```

## Styling

The form and the alert replacing it use plain Tailwind utilities, the same as
the conversation form. js-helper's `app.css` (built by the default
`vite.config.js`) already scans them; if you build your stylesheet from your own
entry point, read *Tailwind content sources* in [managing assets](/manage-assets).

Each element's classes are a `pwNewsletter*Class` default. Redefine one as a
**Twig global** to restyle that element without forking the template:

```yaml
# config/packages/twig.yaml
twig:
    globals:
        pwNewsletterInputClass: 'w-full rounded-md border border-gray-300 px-3 py-2'
        pwNewsletterSubmitClass: 'rounded-md bg-brand-600 px-4 py-2 text-white'
```

`pwNewsletterFormClass`, `pwNewsletterLabelClass`, `pwNewsletterInputClass`,
`pwNewsletterEmailInputClass`, `pwNewsletterSubmitClass`, and for the response
fragment `pwNewsletterAlertClass` plus `pwNewsletterAlertSuccessClass` /
`pwNewsletterAlertErrorClass`.

Values are HTML-escaped, so an arbitrary variant containing `&` or `>`
(`[&>p]:mt-0`) arrives mangled — put those in CSS. Tailwind only emits classes
it scans: a class named only in `twig.yaml` needs that file in your `@source`
list.

For structural changes, override `/newsletter/form.html.twig` and
`/newsletter/alert.html.twig` in the site's views.

The confirmation and opt-out pages render on the live host even when the site is
a static build, so `/newsletter/layout.html.twig` uses none of the site's assets
and carries a small inline stylesheet. Override that layout to brand them.

## CSRF

On by default, including for a statically generated site posting to another
domain. The form endpoint issues a token and the subscribe endpoint answers `403`
to a post without one. Nothing to wire: the placeholder fetches the form, the
form carries its token, js-helper posts it.

The token is signed with the app secret and carries its own expiry instead of
pointing at a session. A session cookie would be a third-party cookie for a
static site on another domain — dropped by Safari, partitioned elsewhere — and
every subscription would fail with `403`.

The endpoint is anonymous and cross-origin by design, so there is no ambient
authority to protect: a forged post obtains nothing a direct `curl` would not.
The token only proves that a form was fetched first, which a script spraying the
endpoint has not done. The honeypot, the rate limits and, above all, double
opt-in — an unrequested subscription stays inert until the address owner clicks —
cover the rest.

Turn it off only for a front end that posts without ever fetching a form:

```yaml
pushword:
    apps:
        - hosts: ['example.com']
          newsletter_csrf_protection: false
```

## Consent

The audience *is* the consent scope: subscribing to one says nothing about
another. A brand spread over seventeen locale hosts is one audience, so nobody is
mailed twice; ten client sites are ten audiences, and an opt-in never leaks
between them. The host that served the form is recorded on the contact as
provenance, not scope.

Double opt-in is a per-audience flag, on by default: the contact stays `pending`
until they click the confirmation mail. Turn it off to import a base that already
consented.

**A contact with no address skips it.** There is no mail to click, so a
phone-only contact is `subscribed` at once, whatever the audience asks. The
consent is that of whoever entered the number, and `source` records who
(`admin:<user>`, `import:…`, `api`).

Every mail carries `List-Unsubscribe` with RFC 8058 one-click. The link in the
body is one click too: it opts out without asking to confirm — a link that makes
people work becomes a spam report.

**A click, not a fetch.** A `GET` opts out only when the browser sends
`Sec-Fetch-User: ?1`, which marks a user-driven navigation. A plain HTTP fetch or
a prefetch does not, so it lands on a confirmation page whose button sends the
same `POST` as RFC 8058. This stops link-following mail scanners and browser
prefetch, not a scanner driving a real browser (Chromium sends `?1` for a
scripted top-level navigation). Browsers without the header (Safari before 16.4)
cost their reader one extra click, never the wrong outcome.

**Undo is one click too.** The opt-out page carries a button that puts the
address back with no confirmation mail — the token reached that mailbox and no
other — and gives the campaign back the unsubscribe it was credited with. It does
not revive a bounced address, nor resume an automation the opt-out stopped.

Leaving one list leaves only that one. The page then offers the address's other
lists **on the same host**, to leave one by one or all at once; the host boundary
keeps one brand's link from revealing what another brand knows. An RFC 8058
`POST` shows that page to nobody; someone opening the link does see it.

All public links (confirm, unsubscribe) are built from the audience host's
`base_live_url`, so they work when the site is statically generated.

### The consent ledger

A contact's dates describe the current state: `confirmedAt` keeps the first
confirmation, `unsubscribedAt` and `bouncedAt` the latest, and a new opt-in
clears those two. Article 7(1) GDPR asks for more: being able to *demonstrate*
that consent was given.

So every act that moves a subscription appends a row nobody edits or deletes —
**opted in**, **confirmed**, **unsubscribed**, **put back**, **bounced** — with
its date and provenance. The *Consent history* panel on the contact page lists
them oldest first.

Provenance belongs to that act: the page slug or the caller's `source` for an
opt-in, `link` for a token link in a mail, `admin:<user>` for an editor, `api`,
`mailbox` for an address a mail server refused. **Only consent-giving acts record
a host and an IP.** An editor confirming by hand records neither: the IP would be
the editor's.

A **merge** moves the absorbed row's history onto the survivor. **Deleting a
contact** deletes their ledger, which is what an erasure request means.
Subscriptions older than the ledger show an empty panel.

#### Splitting an audience in two

`ContactManager::splitFrom($contact, $target)` carries a live subscription onto a
second list — one brand's readers divided in two, a locale given its own
audience. State and ledger are treated differently:

| | carries | why |
|---|---|---|
| **State** — `confirmedAt`, `source`, `optinHost`, `optinIp`, `clickTrackingConsentAt` | the origin's, unchanged | the split divides a consent, it does not renew it |
| **Ledger** — one `split:<origin slug>` row | today's date, no host, no IP | nobody consented to anything today |

The target audience must already exist: its host, sender, double opt-in rule and
vocabulary are editorial decisions. Interests carry over only where the target
declares them; the new row gets its own token, so each list's unsubscribe link
governs its own list.

- **Idempotent.** A row already on the target is returned untouched, whatever
  its status, so a second pass never resubscribes someone who has left it.
- **Subscribed contacts only.** A pending contact has no consent to divide, and
  its copy could never be confirmed. Carry it over from a listener on its
  transition to `subscribed` instead.
- A contact who is not subscribed, or a target equal to the contact's own
  audience, throws `InvalidArgumentException` (a mistyped target would otherwise
  return the origin contact and read as a success).

A batch resolves its subscribed set up front, so someone who leaves mid-run
throws on their turn. Check `isSubscribed()` right before each call, catch per
contact and report how many were skipped.

### Mail with no way out

A service message — an order confirmation, a booking reminder, a password reset —
owes no unsubscribe link.

**Transactional** is a checkbox on the audience, the campaign and the automation.
It drops the unsubscribe link from the HTML and text footers and the
`List-Unsubscribe` headers together, so a mail cannot keep one and lose the
other. The postal address stays.

The three flags are ORed: the audience's flag covers everything it sends and
cannot be overridden; a campaign's or an automation's covers only itself. A
broadcast copies its automation's flag onto each campaign it schedules, and
**Send test** reflects it.

Recipients are unchanged — the audience's subscribed, mailable contacts. The flag
is a claim about the mail, not something that makes it true: promotional mail
without a way out is unlawful under GDPR and CAN-SPAM, and inboxes penalise bulk
mail without `List-Unsubscribe`, costing deliverability on everything else the
audience sends. Prefer a separate audience for service mail; only a transactional
audience may leave its postal address empty.

### Subscribed is not the same as mailable

A contact is keyed on an address **or** a phone number (a phone booking, a paper
form). Each field is optional and at least one is required; `phone` is stored as
digits with an optional leading `+` and is unique per audience, like the address.

- `subscribed` — they agreed to hear from the site.
- **mailable** — subscribed *and* holding an address.

Everything that sends asks the second question. A contact without an address is
never a campaign recipient, never enrolled in an automation and never counted in
reach: the audience page, the campaign preview and the API's audience payload
show `subscribed` and `mailable` side by side.

They are otherwise ordinary contacts — tagged, segmented, exported — and
filterable with `isSet` / `isNotSet` on `email` and `phone`:

```json
[{"field": "email", "op": "isNotSet"}]
```

**No country is inferred.** `+33 6 12 34 56 78` and `+33612345678` are one row;
`+33 (0)6…` is another, because reading `(0)` as a trunk prefix is a guess that
could merge two people. Normalise at import if needed.

**An identifier another row holds is refused, never moved.** Writing a number or
an address that another row of the audience already carries returns `409` from
the upsert, and a validation error from `PATCH` and the admin form, naming that
row. Joining the two decides which consent record and which unsubscribe token
survive, so it is an explicit merge (below), never a side effect.

**The public form stays email-only.** A number arrives over the API or through
the admin's *Opt in a contact*.

### Two rows, one person

The row holding the **address** survives a merge, whichever side asked for it:
the confirm and unsubscribe links already in inboxes are keyed on it, and its
consent record is the one to produce if the opt-in is questioned.

It keeps its id, token, status and consent dates, and gains:

- the number;
- the name and the language, **only where it had none**;
- the tags, added to its own;
- the custom properties it was missing;
- every campaign, enrollment and drip step either row was sent, and both
  [consent ledgers](#the-consent-ledger). Where both rows have a line for the
  same campaign or the same automation run, the kept row's line stays.

Only an addressed row and a phone-only row can be joined. **Two addresses are two
people**: that merge is refused, as is one where the kept row already holds a
different number. Delete one row, or write the identifier onto the row to keep.

In the admin, a save refused for a taken identifier offers the merge under the
form, naming both rows and which one stays. Over the API, `?merge=true` on the
upsert or on `PATCH` asks for it (`409` when it still cannot be done). `PATCH` may
then answer with **another id than the one in the path**: patching an address
onto a phone-only row leaves the person on the addressed row.

```bash
curl -X POST 'https://example.tld/api/newsletter/contact?merge=true' \
  -H 'Authorization: Bearer <token>' -H 'Content-Type: application/json' \
  -d '{"audience": "readers", "email": "reader@example.tld", "phone": "+33612345678"}'
```

A merge sends nothing and never reopens a confirmation.

### One contact row per list

A contact belongs to exactly one audience — `(audience, email)` is unique.
Somebody on three lists is three rows, each with its own tags, custom properties
and consent record.

In the admin, the **audience** select on a contact *moves* that subscription; it
does not add one. **Subscriptions**, at the bottom of the contact page, lists
every row for the same address, whatever its audience and status.

Mailing the same address from two hosts separately therefore takes two audiences.
`optinHost` is provenance, never scope: an audience spanning several locale hosts
stays one list and one row.

### Opting somebody in by hand

**Newsletter → Contacts → Opt in a contact**: pick a list, give an address or a
phone number, and the audience's double opt-in rule applies — the confirmation
mail goes out as it would from the public form. A number alone subscribes at
once. Tick *they already consented* only for consent you can produce (a paper
form, a written reply): it skips the mail, like `status: subscribed` over the API.

The row records `source: admin:<user>`. There is no **New** button: a contact
written field by field would have no recorded opt-in.

From a contact, **Add to another list** opens the same page with the address
prefilled. **Confirm by hand**, **Unsubscribe** and **Put back on the list** run
the same code as the public links, so campaign counters and running automations
stay consistent.

## Segments

One expression language drives a campaign's audience, an automation's enrollment
rule and its stop condition — a list of conditions that must all hold:

```json
[
  {"field": "tag",                    "op": "has",       "value": "AmTrek"},
  {"field": "createdAt",              "op": "olderThan", "value": "7d"},
  {"field": "prop.lastBoughtProduct", "op": "=",         "value": "tmb"}
]
```

| field | operators |
|---|---|
| `tag` | `has`, `hasNot` |
| `createdAt`, `confirmedAt` | `olderThan`, `newerThan` — a duration like `90m`, `6h`, `7d`, `2w` |
| `prop.<key>` | `=`, `!=`, `isSet`, `isNotSet` |
| `locale` | `=`, `!=` |
| `email`, `phone` | `=`, `!=`, `isSet`, `isNotSet` |

An empty list means the whole audience.

**A rule that needs `OR` says so**, and every condition of that list then belongs
to the one operator:

```json
{"any": [
  {"field": "tag", "op": "has", "value": "AmTrek"},
  {"field": "tag", "op": "has", "value": "AmTrek-VIP"}
]}
```

`{"all": [...]}` spells out the default. Prefer one `any` rule to two campaigns:
a contact carrying both tags would be in both and mailed twice.

**A condition may itself be a group** — "either tag, but only among customers".
Keep the flat form for whatever it can express, and nest when the alternative is
two overlapping campaigns:

```json
[
  {"field": "prop.lastBoughtProduct", "op": "isSet"},
  {"any": [
    {"field": "tag", "op": "has", "value": "AmTrek"},
    {"field": "tag", "op": "has", "value": "AmTrek-VIP"}
  ]}
]
```

A nested group always names its operator, `all` included: a bare list there
would be read as a condition.

Whatever you write:

- every query is scoped to `status = subscribed`, so no expression can reach an
  unsubscribed or bounced address;
- `prop.x != y` skips contacts with no `x` — a missing property is unknown, not
  "different from y".

**The admin writes it for you.** Every rule — a campaign's segment, an
automation's trigger, its recipients, its stop condition — is edited as rows
(field, operator, value) under `All`/`Any`, with one level of grouping. The fields
offered come from the rule's own vocabulary, so choosing `page` as an
automation's source swaps them, and values are suggested from what the site
already has: its tags, templates, section slugs and property keys in use.

**Edit as text** switches to the JSON at any time. A rule the builder cannot show
(a `pages_list` search) stays text. Both write the same field and pass the same
validator.

**Count before you send.** The count under each rule follows what you type — how
many subjects are waiting, or how many subscribed contacts a broadcast would
reach, with the first few named — and a malformed rule answers there with the
save's error message. A *trigger* must be saved once before it can be counted
(subtracting what the automation already handled needs it to exist), and a new
automation counts zero because its **Active from** is now; *Ignore the start
date* counts anyway. The campaign and automation lists keep a count button for
saved rows; the API returns `estimatedRecipients` on any draft campaign.

## Campaigns

Write the body in Markdown (the block editor takes over when
`pushword/admin-block-editor` is installed), pick an audience and optionally a
segment, then **Send** or **Schedule**. `%name%` and `%email%` are substituted in
the subject and the body. The **analytics name** is the campaign's slug, derived
from the subject when empty; the send date is prefixed to it when the campaign
goes out.

Sending never blocks: recipients are frozen into rows up front and the tick
drains them at the audience's cadence. A row already sent is never re-sent, so an
interrupted run, a deploy or a crash cannot double-send. A contact who
unsubscribed between arming and sending is recorded as `skipped`, not as a
failure.

**Send test** mails a copy to any addresses with a `[TEST]` subject prefix,
touching no contact and no counter. Once the campaign carries translations, it
asks which language to send.

### Email Markdown

Mail bodies and `SendContext.footerMarkdown` use an email-specific CommonMark
renderer: standard Markdown plus attributes, tables, strikethrough, task lists and
authored HTML. Pushword web shortcodes and media references are not expanded —
use ordinary links, absolute image URLs and email-compatible HTML.

- Bare addresses, `<address@example.com>` and Markdown `mailto:` links stay
  readable and clickable, with no JavaScript. Web page email obfuscation is
  unchanged.
- Images take an absolute public URL:
  `![Descriptive alt text](https://your-public-host/photo.jpg)`. They render as a
  plain full-width `<img>` (inline `width:100%;max-width:100%;height:auto`, no
  `height` attribute) — no lightbox, `<picture>`, lazy loading or media lookup.
  The URL is kept as supplied.
- A placeholder may hold a complete Markdown image or an empty string. Put it on
  its own line, followed by a blank line; an empty value then leaves no image and
  no empty paragraph.

Links still go through UTM tagging and consented click tracking. In a drip, list
sensitive authentication URLs in `untrackedUrls` to keep them direct (see
[Preparing a drip at delivery](#preparing-a-drip-at-delivery)).

### Languages

One campaign carries one body per locale, so an audience spanning several locale
hosts is mailed once, each reader in their own language. One campaign per
language, each filtered on `locale`, would mail bilingual readers twice and have
to be armed once per language.

The **Languages** fieldset holds them as JSON, one entry per locale:

```json
{
  "de": {"subject": "Hallo", "preheader": "…", "bodyMarkdown": "Lies das."},
  "it": {"subject": "Ciao"}
}
```

Each reader gets, resolved when the mail goes out:

1. `translations[<the contact's locale>]`,
2. otherwise its language part — `de-ch` reads `de`,
3. otherwise the campaign's own subject, preheader and body.

Each field falls back on its own, so a translated subject with the default body
is valid. A blank field is not stored, so **nobody receives an empty mail**; the
fieldset shows how many of the audience's languages are covered before anyone
presses Send.

Over the API, `translations` merges like `customProperties`, one level deeper: a
`PATCH` writes only the fields it names, `""` clears one field, and a locale set
to `null` drops the whole entry. Locales are stored lowercase and dash-separated,
so `"DE"` and `"de_CH"` address `de` and `de-ch`.

Resolution happens at send, not at arming: a recipient row freezes *who*, never
*what* (as with `%name%`), so a typo can still be fixed mid-campaign.

Counters stay per campaign; a per-language split is `CampaignRecipient` joined to
`Contact.locale`. `locale` remains a segment field, so one campaign per market
(different offers, not translations of one) still works.

### Who it went to

**Recipients** opens the rows behind a campaign's counters: one per contact, with
its state (`pending`, `sent`, `skipped`, `failed`, `bounced`) and, for a failure,
the transport's message — kept nowhere else, since `failedCount` only sums the
rows. The button appears once the campaign is armed; the list is read-only.

## Automations

One screen covers "two mails after subscription" and "announce every article the
day after it goes out". An automation has:

- a **trigger source** — what it watches;
- **`triggerWhen`** — which of that source's subjects start the sequence,
  written in the source's own vocabulary;
- **steps** — the mails, in order, each after its own delay.

Two sources ship with the bundle (contacts and pages); a bundle of yours can add
more (see [Custom trigger sources](#custom-trigger-sources)).

**`activeFrom`**: nothing that happened before that date triggers anything. It
defaults to the automation's creation date, so switching one on cannot mail an
entire existing base or announce a back catalogue. It is a field rather than a
criterion so that it cannot be forgotten.

Disabling an automation pauses it: running sequences keep their place and
resume, and nothing new is picked up.

### Two ways a sequence is delivered

The source decides per occurrence: is it about *one person*, or about *the site*?

**One person** — a new contact, a customer who ordered: the steps are dripped at
them. An enrollment holds their place, and `stopWhen` is re-checked before each
step, so someone whose situation changed stops mid-sequence (no "discover us" to
a customer who just booked). Unsubscribing stops every active sequence.

**The site** — an article was published: each step becomes an ordinary scheduled
**campaign**, sent to whoever `recipientWhen` selects. `stopWhen` does not apply:
recipients are resolved when each campaign is armed, so someone who stopped
matching between step one and step two is not in step two.

**`recipientWhen` is read at send time, not at trigger time**, so it can select
on reader state unrelated to the article:

```json
[{"field": "prop.lastSeenAt", "op": "olderThan", "value": "30d"}]
```

— every article, but only to subscribers not seen for a month.

### Watching contacts

`triggerWhen` is an ordinary [segment](#segments). Every subscribed contact who
comes to match is enrolled once. "Two mails after subscription" is an empty
`triggerWhen` and two steps.

It says *who*, never *when*: timing is each step's delay. A contact is enrolled
once subscribed — with double opt-in, when they confirm, since a pending contact
matches nothing — and the first step's delay counts from when they signed up. "Two
days after sign-up" is an empty `triggerWhen` and a first step at 2880 minutes.

### Watching pages

Publish an article and, a delay later, everyone `recipientWhen` selects gets a
mail about it, with no campaign to write. Set the source to `page`, name the
hosts to watch, and write `triggerWhen` over pages:

```json
[{"field": "slug", "op": "startsWith", "value": "blog/"}]
```

| field | operators |
|---|---|
| `slug` | `startsWith`, `notStartsWith` |
| `template`, `parent` | `=`, `!=` — `parent` takes the parent page's slug |
| `ancestor` | `=`, `!=` — the slug of a page it sits under, at any depth |
| `tag` | `has`, `hasNot` — as on a contact, and as a bare [`pages_list`](/pages-list) search |
| `prop.<key>` | `=`, `!=`, `isSet`, `isNotSet` |

The shape is a segment's, `{"any": [...]}` included. An empty list means every
published page of those hosts. `triggerWhen` picks the article, `recipientWhen`
the readers.

You can also write a [`pages_list`](/pages-list) search. It is translated into
the list above and stored as such, so you can see what it understood:

```
ancestor:blog AND (tag:featured OR tag:pinned)
```

The search grammar is wider than this vocabulary: a `title:` search or a
`children` is refused by name — an automation has no current page to be relative
to.

Before reaching for `any`, use what already groups pages. A blog whose rubrics
attach root-level articles by `parent` shares no slug prefix, but `ancestor` or a
tag covers it in one condition, including next month's new rubric:

```json
[{"field": "ancestor", "op": "=", "value": "blog"}]
[{"field": "tag", "op": "has", "value": "blog"}]
```

Whatever the rule, the hosts, `activeFrom` and the pages already handled are ANDed
with all of it: `any` widens which pages match, never past those.

### What a step may quote

A step's subject and body may quote what the occurrence lends. A page lends six
values:

```
{{ page.h1 }}           {{ page.excerpt }}   {{ page.chapeau }}
{{ page.mainContent }}  {{ page.url }}       {{ page.mainImage }}
```

A contact lends `{{ contact.name }}` and `{{ contact.email }}`; a custom source
lends whatever it declares.

The braces are borrowed from Twig; nothing is evaluated. Values are substituted
once, when the occurrence is handled, so what is stored is plain Markdown and
link absolutization and `utm_*` tagging apply as on a hand-written mail. An
unknown name is left in place, so a typo shows up in the preview. `{{ page.url }}`
is built from the page's own host and canonical base URL, so it works on a static
site and across an audience spanning several locale hosts.

Values are frozen when the sequence starts: a three-step drip quotes the same
title in its last mail as in its first, even if the article was retitled.

`{{ page.excerpt }}` is the article's own opening — never the `searchExcerpt`
property, which is written for search results. First match wins:

1. **the chapeau** — what sits before `<!--break-->`, as authored;
2. **the intro** — every paragraph before the first heading, on a page that asked
   for a table of contents;
3. **the opening paragraph**, as text, cut at 300 characters on a word boundary.

The third skips whatever precedes it (a figure, an interactive block). A page
with no paragraph at all lends nothing, and the mail keeps its title, image and
link. Give such a page a `<!--break-->` if it deserves an accroche.

`{{ page.chapeau }}` is the lede alone: empty without a break, and identical to
`{{ page.excerpt }}` on a page with a break but no table of contents — quote one
or the other, not both.

`{{ page.mainContent }}` is how much of the article a mail can carry: the page's
paragraphs from the very top (a chapeau included), joined by blank lines and cut
at 900 characters on a word boundary. Use it when the mail should be worth
reading on its own, and `{{ page.excerpt }}` when it only points at the page.
Only paragraphs are quoted: headings, figures and interactive blocks are left
out, and a page with no paragraph lends nothing.

**The subject gets plain text, the body the markup.** An `h1` may carry `<em>`,
`<br>` or a `<span>`, and an excerpt can be rendered HTML. In the subject, tags
are dropped (each leaving a space, so `<br>` does not glue words) and entities
decoded; the body keeps the inline HTML.

### What a drip records

A drip has no campaign, so it keeps its own ledger: **Deliveries** in the
newsletter menu, one row per step per contact, with the subject as received
(placeholders filled) and its final state (`sent`, `failed`, `bounced`).

A step the transport refuses is skipped, not retried — retrying would freeze the
contact's sequence on that mail — so this row is the only record that someone
missed a step, and the only place the transport's reason is kept.

A bounce or an unsubscribe is credited to the last mail that person received.
When that is a drip step, its row is marked and no campaign is charged:
campaign counters mean "caused by this send".

### Preparing a drip at delivery

Drips run synchronously in `pw:newsletter:tick` (cron/CLI), through Symfony's
configured `TransportInterface`. They bypass `MailerInterface`'s Messenger bus,
including a `SendEmailMessage` route to `async`. The same DSN, transport
listeners, envelope sender and failover configuration apply. Campaigns,
confirmations and test mails still use the usual mailer. Never call the drip
runner from HTTP. A result means the transport accepted the submission, not inbox
delivery.

For a targeted CLI rehearsal, use
`AutomationRunner::advanceOne(Enrollment $enrollment, ?DateTimeImmutable $now = null): bool`.
It processes only that managed enrollment, checks active status and
`nextRunAt <= now`, applies the same source/contact guards and delivery hooks, and
flushes the result. It never triggers occurrences or advances other enrollments.
`now` defaults to the current time and drives this enrollment's due check and
next-step scheduling; it does not change the source's application clock or bypass
its eligibility rules. `true` means transport acceptance; paused, stopped,
completed, not-yet-due or vetoed runs return `false`.

Before **every** step, the runner checks mailability, `stopWhen` and the source's
`stillMatches(subjectId)`. A false answer stops the enrollment with
`stopReason = subject_no_longer_matches`; a missing source stops it with
`source_unavailable`. A disabled automation pauses. Trigger logs remain, so a
stopped subject is not enrolled again.

Subscribe to `Pushword\Newsletter\Event\PrepareAutomationDelivery` with
`#[AsEventListener]`. It exposes `enrollment`, `automation`, `step`, `contact` and
`subjectId`, and runs only during actual synchronous delivery — never during
count, preview or enrollment. Higher-priority listeners run first: place
validation before the listener that reserves a subject and issues sensitive
credentials.

```php
#[AsEventListener]
public function prepare(PrepareAutomationDelivery $event): void
{
    if ($event->automation->source !== 'your_source') {
        return;
    }

    // Re-read application facts and consent; reserve with an atomic conditional
    // update in your own short transaction. Commit before returning, never SMTP
    // under that transaction. A previously reserved subject must veto forever.
    if (! $this->reserve($event->subjectId, $event->contact)) {
        $event->veto('subject_already_reserved');

        return;
    }

    $url = $this->issueLink($event->subjectId);
    $event->placeholders['subject.resumeUrl'] = $url;
    $event->untrackedUrls[] = $url;
}
```

`veto(reason)` stops propagation and the enrollment; the reason is kept in
`Enrollment::stopReason` (255 characters). Use stable codes, never credentials. A
veto creates no delivery row and no result event. The application owns consent,
subject eligibility and cross-automation reservations.

Late placeholders override frozen values **only in the body**; they never reach
the enrollment, the subject line, a delivery row or a queue. Escape Markdown/HTML
in the producer: placeholders are literal substitution, not Twig. Give
`untrackedUrls` absolute URLs: exact matches (after HTML entity decoding) bypass
both UTM and click rewriting, even with both tracking consents. Rendered mail and
URLs are never passed to result listeners or logged by the runner, and exception
messages are kept out of its logs and ledger — only the class is retained, since
the text can contain credentials.

Subscribe to `Pushword\Newsletter\Event\AutomationDeliveryResult` for the
outcome. `enrollment` still points to the attempted step; `delivery` exposes
automation, contact, subjectId, position, `state` (`Sent`/`Failed`) and `error`
(exception class). It runs before advancement and before the runner flushes its
ledger; use `Sent` to record transport acceptance in the application. A
result-listener exception stops the enrollment with `result_notification_failed`,
without changing or retrying the transport outcome. A preparation, render or
transport exception records `Failed` and advances past the step.

The runner never retries SMTP, and there is no drip `SendEmailMessage` for
Messenger to retry. A crash can still fall between the application's reservation,
SMTP acceptance and the ledger flush: guaranteeing **at most one authorized
submission** (with a possibly lost or uncertain result) requires the application's
durable reservation. Never clear it automatically on failure. Transport
failover/retry stays the configured transport's; use a single transport if an
ambiguous failure must never reach a second provider.

### Sending in an occurrence's site and language

An occurrence addressed to a contact may provide `locale` and a
`Pushword\Newsletter\Delivery\SendContext`:

```php
new TriggerOccurrence(
    subjectId: $subjectId,
    occurredAt: $occurredAt,
    placeholders: $safeValues,
    contact: $contact,
    locale: 'de',
    sendContext: new SendContext(
        mainHost: 'www.example.de',
        fromEmail: 'newsletter@example.de',
        fromName: 'Example Reisen',
        replyTo: 'info@example.de',
        postalAddress: "Example Reisen\n12 Example Street",
        footerMarkdown: '[Legal information](https://www.example.de/legal)',
        audienceName: 'Example Reisen',
        systemLinkBaseUrl: 'https://www.example.de',
    ),
);
```

The runner snapshots both on the enrollment, without changing `Contact.locale` or
the contact's audience. Without a context it snapshots the automation's audience;
without a locale, the contact's. Enrollments with no snapshot use their current
audience and contact. One step per host then needs no step translations. Contexts
apply to contact drips only; broadcast segmentation is unchanged.

- `mainHost` selects the site's template and the canonical base for relative body
  links.
- `systemLinkBaseUrl` is an optional absolute HTTP(S) base for the unsubscribe and
  tracked-click endpoints, `List-Unsubscribe` and the HTML/text footer included.
  It overrides the selected site's `base_live_url` (the default) for this
  occurrence only. Include any public path prefix; trailing slashes are removed.
  The chosen origin must serve `/newsletter/unsubscribe/*` and, with click
  tracking, `/newsletter/c/*` — no route, proxy or alias is added for you.
- The unsubscribe token still belongs to the original contact and leaves the
  original consent audience. That audience and the automation still decide
  transactional status, UTM source and tracking consent: presentation cannot
  enable tracking or remove the unsubscribe link.
- Give `footerMarkdown` absolute links. Snapshot only public identity and safe
  content — no authentication URLs in the context or in frozen placeholders.

The default template receives the original `audience` and `contact`, plus
`locale`, `sendContext`, `audienceName`, `postalAddress` and the rendered
`footer`; a site override must use these variables for the occurrence context to
take effect. The default unsubscribe label is translated into English, French,
German, Dutch and Italian; override translations as usual.

### What a broadcast produces

**Ordinary campaigns**, one per step, scheduled at `occurredAt + delay` and sent
by the same tick. During the delay you can read, edit or cancel them in the
admin; afterwards they report deliveries, unsubscribes and bounces like any
other, and each carries the automation it came from. They are never rewritten:
editing the page afterwards does not change a queued mail.

**One language per campaign.** Seventeen locale versions of an article are
seventeen pages, hence seventeen campaigns under one `recipientWhen`. When the
audience holds contacts in more than one locale, each campaign's segment is
narrowed to the language of its page, so a reader gets the article once. An
audience read in a single language keeps its rule as written. The narrowing is
ANDed onto `recipientWhen` (an `any` group stays whole); writing `locale` into the
rule yourself still works.

It will not:

- **mail a back catalogue** — `activeFrom`, as above;
- **mail the same subject twice** — an automation records the subjects it
  handled, so a missed tick only delays work and a doubled tick writes nothing;
- **mail a dead link** — a page unpublished or deleted before its campaign is
  armed cancels it, and republishing it gets it its mail. Once a step has been
  armed, the remaining steps are cancelled and the subject stays handled.

Not to be confused with [Page Update Notifier](/extension/page-update-notifier),
which mails *you* when content changes.

### Custom trigger sources

Anything your application can watch can start a sequence. Implement
`TriggerSource` and tag the service `pushword.newsletter.trigger_source`: it then
appears in the admin's source list, its vocabulary is validated in the same
field, and steps, delays, segments and reporting work as for any automation.

```php
use Pushword\Newsletter\Trigger\{TriggerSource, TriggerOccurrence};

#[AutoconfigureTag('pushword.newsletter.trigger_source')]
final readonly class CustomerTriggerSource implements TriggerSource
{
    public function name(): string { return 'customer'; }

    /** The vocabulary triggerWhen is written in — your own AbstractCriteria subclass. */
    public function criteria(): string { return CustomerCriteria::class; }

    /** @return list<TriggerOccurrence> */
    public function occurrences(Automation $automation, DateTimeImmutable $now, ?int $limit = null): array
    {
        return array_map(fn (Customer $customer) => new TriggerOccurrence(
            subjectId: $customer->getId(),
            occurredAt: $customer->getFirstOrderAt(),
            placeholders: ['customer.firstName' => $customer->getFirstName()],
            contact: $this->contactOf($customer),   // null broadcasts instead
        ), $this->matching($automation, $now, $limit));
    }

    public function count(Automation $automation, DateTimeImmutable $now): int { /* … */ }

    /** Asked during the delay: a refunded order is no longer worth a mail. */
    public function stillMatches(int $subjectId): bool { /* … */ }
}
```

- **`subjectId` is what the automation remembers having handled.** A subject
  returned twice is dropped, so a source without a `LIMIT` is still safe.
- **`contact` picks the delivery** per occurrence: set, the steps are dripped at
  that person; null, they are broadcast to `recipientWhen`.
- **`occurredAt` starts the clock** — the event's own date, not the tick's, so a
  late tick still mails on time.
- **The automation does the remembering**; your source only exposes the query and
  stays stateless.

Your vocabulary is an `AbstractCriteria` subclass — the base the segment and page
languages extend — so `{"any": [...]}`, the JSON round trip through the admin and
the error messages come for free. Its `FieldRegistry` says how each field
compiles. The admin's condition builder reads `FIELD_OPERATORS` to build rows and
dropdowns, and `DURATION_OPERATORS` to ask for an amount and a unit instead of
`7d`.

To suggest values your application already holds (statuses in use, products
bought), implement `CriteriaSuggestions` for your criteria class and tag it
`pushword.newsletter.criteria_suggestions`:

```php
#[AutoconfigureTag('pushword.newsletter.criteria_suggestions')]
final readonly class CustomerCriteriaSuggestions implements CriteriaSuggestions
{
    public function criteria(): string { return CustomerCriteria::class; }

    /** @return array<string, list<string>> by field name */
    public function suggest(array $hosts): array
    {
        return ['status' => $this->statusesInUse(), 'prop.' => $this->propertyKeys()];
    }
}
```

They stay suggestions: a rule may name a value nobody has yet, so an automation
can be written before what it waits for exists. Without suggestions the builder
offers plain text boxes, validated the same way.

## Link attribution

Set an audience's **analytics source** (`utm_source`, e.g. `newsletter`) and every
link in its mails pointing at one of your own sites carries:

```
https://example.com/article?utm_source=newsletter&utm_medium=email&utm_campaign=260728-janvier
```

`utm_campaign` is the campaign's slug prefixed with the send date as `YYMMDD`, so
a year of campaigns sorts in order. The slug is derived from the subject unless
set; the date is stamped when the campaign is armed (scheduled in March, sent in
April: dated April). Rewording the subject afterwards renames nothing.

Automation steps carry the automation's name plus `utm_content=step-2`, and no
date: a drip runs continuously.

It never touches a link to another domain, a link you tagged by hand, or the
unsubscribe link, and tags nothing while the audience has no source.

A root-relative `/slug` link in a body is made absolute against the site's
canonical base URL before sending.

Attribution is aggregate and records nothing per person; per-reader clicks are
the next section.

## Click tracking

Off by default. Attribution answers "which campaign brought the traffic" with no
redirect and nothing stored per person; click tracking answers "which contact
clicked which link", which is personal data. So it needs **two consents**, and
the second is never inferred from the first:

1. **The audience's switch** — `clickTracking`, false by default, editable in the
   admin and over the API. It says the site is willing to track, not that anybody
   agreed.
2. **The contact's own dated consent** — `clickTrackingConsentAt`, a datetime
   because the date is what you must produce if the logging is questioned.
   Collect it however your site likes (a checkbox in its client area, under its
   own privacy policy) and write it over `PATCH /api/newsletter/contact/{id}` or
   on the contact's admin form. Turning the audience switch on tracks nobody
   until each contact's consent is written.

The bundle offers one collection point of its own: **the double opt-in mail**.
When the audience tracks, the confirmation mail states the purpose in one muted
line and carries two links — *confirm* as the button, *confirm (anonymized
links)* below it. Both confirm the subscription; only the first also records the
consent, and only for a navigation the browser attributes to a person
(`Sec-Fetch-User`, as for unsubscribe), so a mail scanner following every link
cannot consent on the reader's behalf. A site overriding
`confirm.email.html.twig` keeps a single button until its template renders
`confirmTrackingUrl` (null when the audience does not track).

With both gates open, body links in that contact's mails — campaigns and drip
steps — are rewritten through `GET /newsletter/c/{payload}`, which records the
click (contact, campaign or step, destination, time) and answers `302` to the
destination. With either gate closed, links stay exactly as written, `utm_*`
included.

- **Only the body's `http(s)` links** are rewritten. The body is rewritten before
  the template wraps it, so template links — the unsubscribe link first — are out
  of reach.
- **UTM tagging runs first**, so the recorded and redirected URL is the tagged one.
- The plain-text part keeps its links as written.

The payload is signed with an HMAC on the kernel secret, so the endpoint is not
an open redirect: a signature that does not verify answers `404`.

**Withdrawal is one write.** Setting `clickTrackingConsentAt` to null, over the
API or by clearing it in the admin, purges every click recorded for that
contact; deleting the contact does too. Links already in their inbox keep
redirecting, since both gates are checked at click time, but stop recording.

Reported:

- per campaign, next to sent, failed, unsubscribed and bounced — on the admin page
  and in the API `stats`;
- per reader, as a clicks column on the campaign's **Recipients** ledger;
- per link in `GET /api/newsletter/campaign/{id}` as `clicksByUrl`: clicks and
  distinct readers;
- for a drip step, against the automation and the step's position.

## Custom properties

Anything the site knows about a person — `lastBoughtProduct`, `plan`, `city` —
lives in `customProperties` and is readable from a segment as `prop.<key>`. Write
them from the API: a `PATCH` merges rather than replaces, and a `null` value
removes a key.

## Sending

`pw:newsletter:tick` is stateless and idempotent. Each run, under a lock:

1. asks every enabled automation's source what newly happened, and starts a
   sequence for each — an enrollment, or a campaign per step;
2. cancels the campaigns whose subject no longer deserves them;
3. arms scheduled campaigns whose date has passed;
4. drains pending recipients at the cadence;
5. sends the drip steps that are due.

Triggering comes first, so something whose delay has already elapsed goes out in
the run that noticed it.

The cadence is **seconds between two mails**: `rateSeconds` on the audience (30
by default), which a campaign may override. Pacing is derived from the last mail
actually sent rather than from a sleep, so the command returns immediately and a
campaign resumes at the right rate whatever happened to the previous run. A
minutely cron at 30 s sends two mails per run.

`--batch` caps the mails one run may send (default 50, set by
`newsletter.send_batch`). It only matters when catching up: after an outage, the
elapsed time would allow hundreds at once, and the cap keeps a resumed campaign
from becoming a burst.

```shell
php bin/console pw:newsletter:tick
php bin/console pw:newsletter:send 12    # arm a campaign now; the tick delivers
```

The transport is the site's own `MAILER_DSN`: each site owns its provider and its
reputation.

## Bounces

A mail refused at send time is recorded immediately. A later bounce — the relay
accepted the message, the remote server refused it afterwards — comes back as a
mail to the **envelope sender**, which differs from the `From:` readers see.
Unread, the dead address stays subscribed and every campaign retries it, spending
sending reputation.

Point the envelope at a mailbox nobody reads by hand, and read it from cron:

```yaml
# config/packages/mailer.yaml
framework:
  mailer:
    envelope:
      sender: bounce@example.com

# config/packages/pushword.yaml
newsletter:
  bounce_maildir: /home/user/mail/example.com/bounce
```

```shell
php bin/console pw:newsletter:bounces --dry-run   # what it would drop
php bin/console pw:newsletter:bounces
```

```cron
0,15,30,45 * * * * cd /path/to/app && bin/console pw:newsletter:bounces -q
```

On a shared host this needs nothing else: a bounce is a file in a maildir — no
extension, webhook or credentials. `--maildir=<path>` reads another maildir than
the configured one.

### When the mailbox is not on this machine

When the app runs on a VPS or in a container and the mail at a provider, the
mailbox is only reachable over IMAP:

```shell
composer require webklex/php-imap
```

```yaml
newsletter:
  bounce_imap_dsn: '%env(NEWSLETTER_BOUNCE_IMAP_DSN)%'
```

```dotenv
NEWSLETTER_BOUNCE_IMAP_DSN=imaps://bounce%40example.com:secret@imap.example.com:993/INBOX
```

The folder is optional and defaults to `INBOX`; `imap://` on port 143 uses
STARTTLS. Percent-encode the credentials — generated passwords often contain `@`
or `/`.

Parsing and rules are the same as for a maildir. Instead of moving a message to
`cur/`, the command sets `\Seen` and searches `UNSEEN` on the next run.

- **Set `bounce_maildir` or `bounce_imap_dsn`, never both.** The command refuses
  to run with both; the check happens at run time because an `%env()%` DSN is
  still an unresolved placeholder when the container builds.
- **Nothing else may read that mailbox.** Anything that marks messages seen, a
  webmail session included, hides them from the command.

Unlike the maildir, which reads only the first 64 KB of each message, IMAP
fetches the whole message; only the parsing is bounded.

### What the command does with what it reads

- It parses the `message/delivery-status` part, never the human-readable one.
- It acts only on reports about a message this site sent — see
  [below](#only-reports-about-mail-this-site-sent).
- It acts on **permanent failures only** (`Status: 5.x.x`); a 4.x.x is a
  temporary failure the next retry may get through.
- An address on several audiences leaves all of them: the server refused the
  address, not a list.
- A bounce for somebody on no list is counted and reported, never acted on (the
  mailbox also collects failures of the app's other mail).
- A message it has read is moved to `cur/` with the seen flag, or flagged `\Seen`
  over IMAP, so the next run skips it. One that cannot be marked is only counted;
  reading it again is harmless.

### Only reports about mail this site sent

The bounce mailbox is published in every newsletter's `Return-Path` and accepts
mail from anyone. Anybody can write a `multipart/report` naming
`Final-Recipient: someone@example.com`, and acting on it would let anyone remove
anyone, permanently, since a bounce is never undone.

So a report must identify **which message** failed, and that message must be one
this install issued. Every newsletter goes out with its own `Message-ID`:

```
Message-ID: <nl.<nonce>.<signature>@example.com>
```

The signature is an HMAC of the nonce and the recipient's address, keyed on
`APP_SECRET`. A delivery report returns a copy of the failed message — whole as
`message/rfc822`, or headers only as `text/rfc822-headers` — and a
`Final-Recipient` is honoured only when one of the ids in that copy verifies for
that same address. Nothing is stored. Forging one needs the secret or a mail
really sent to that address, and reusing a genuine bounce for another address
fails because the signature names the recipient.

A report that proves nothing is counted as `unverified` and ignored. Watch that
number; it has two causes:

- somebody is forging reports against your list, or
- your relay returns no copy of the failed message (RFC 3464 only recommends
  it), in which case no bounce is ever acted on. `--dry-run` on a mailbox with
  real bounces tells you which.

### Hearing about it

`--notify=ops@example.com` mails the summary **only when something moved** — at
least one address dropped or one permanent failure recorded — so a run every
fifteen minutes does not train anyone to filter it. `--dry-run` never mails.

The sender is `notification_email_from`, falling back to `noreply@<host>`.

A bounced contact is terminal: `resubscribe()` refuses to revive one; only a new
explicit opt-in does.

## API

Available when `pushword/api` is installed, under the same token authentication,
and self-describing at `/api/docs`.

```
GET    /api/newsletter/audience?host=
POST   /api/newsletter/audience
GET    /api/newsletter/audience/{slug}  # includes contact counts per status
PATCH  /api/newsletter/audience/{slug}
DELETE /api/newsletter/audience/{slug}  # refused while it holds contacts

GET    /api/newsletter/contact?audience=&status=&tag=&segment=&q=
POST   /api/newsletter/contact          # upsert on (audience, email)
GET    /api/newsletter/contact/{id}
PATCH  /api/newsletter/contact/{id}     # customProperties are merged
DELETE /api/newsletter/contact/{id}
POST   /api/newsletter/contact/{id}/unsubscribe
POST   /api/newsletter/contact/{id}/bounce

GET    /api/newsletter/campaign?audience=&status=
POST   /api/newsletter/campaign
GET    /api/newsletter/campaign/{id}    # includes estimatedRecipients while draft, clicksByUrl once sent
PATCH  /api/newsletter/campaign/{id}    # drafts only
DELETE /api/newsletter/campaign/{id}
POST   /api/newsletter/campaign/{id}/schedule
POST   /api/newsletter/campaign/{id}/send
POST   /api/newsletter/campaign/{id}/test

GET    /api/newsletter/automation?audience=&enabled=
POST   /api/newsletter/automation
GET    /api/newsletter/automation/{id}  # includes progress, subjects waiting and reach
PATCH  /api/newsletter/automation/{id}
DELETE /api/newsletter/automation/{id}
```

`POST /contact` follows the audience's double opt-in rule; `"status":
"subscribed"` skips the confirmation, for importing a base that already
consented.

The `segment` query parameter takes the same JSON criteria as a campaign, so an
external system can count an audience before asking for a send.

An automation carries its whole sequence: `steps` is an array in sending order,
and sending it again replaces the sequence. `activeFrom` defaults to the moment
of creation here too.

`source` decides which vocabulary `triggerWhen` is validated against, so a
request changing both must send the source. An unknown source, or a rule in the
wrong vocabulary, is a `400` naming which of the three rules was wrong.

A `GET` reports `waiting` (subjects the source has right now), `matchingContacts`
(what `recipientWhen` reaches), `handled` and the enrollment `stats`. Deleting an
automation drops its enrollments and markers but keeps the campaigns it produced.

An audience's `mainHost` must be a configured Pushword host (an unknown one would
fall back to the default site and link to another brand); an alias is stored as
its main host. The slug is set once: renaming belongs to the admin, where the
templates quoting it can be fixed at the same time. Deleting an audience that
still holds contacts is refused, since the cascade would drop their consent
records.

## Posting the form yourself

The endpoint behind `newsletter_form()` is public and takes an ordinary form
post, so your own front end (a React island, a static site's markup) can
subscribe directly:

```
POST https://example.com/newsletter/subscribe
```

| field | |
|---|---|
| `email` | required |
| `audience` | required — one slug, or `audiences[]` to subscribe to several at once |
| `name` | optional, substituted as `%name%` |
| `interests[]` | tags to attach; only values the audience declares survive |
| `locale` | defaults to the current site's |
| `source` | where the form sits; defaults to the referer's path |
| `website` | the honeypot — render it hidden, never fill it |
| `_token` | required unless `newsletter_csrf_protection` is off — read it from `GET /newsletter/form?audiences=<slug>`. It is self-contained: no cookie to carry back, and it works from any origin |

The response is an HTML fragment, not JSON (override `alert.html.twig` to
restyle it). Read the status rather than the body: `200` subscribed or awaiting
confirmation, `400` a missing audience or a malformed address, `403` a missing or
stale token, `404` a slug that matches nothing, `422` the confirmation mail could
not be sent (often a typo in the address), `429` the rate limit.

To expect while testing:

- **Ten subscriptions per IP per hour.** The endpoint is public, cross-origin and
  sends mail; without a ceiling it would deliver confirmation mails to any
  address an attacker chooses.
- **One confirmation mail per address and list every ten minutes, five a day.**
  Beyond that, the submission gets the usual "click the link we just sent you"
  answer, and no new mail.
- **A filled honeypot gets the success response** and creates no contact, so a
  prober cannot tell rejected from accepted. A form whose hidden field your own
  JS fills will silently subscribe nobody.

Posting from another origin requires that origin to be allow-listed (below).

## Configuration

```yaml
newsletter:
  send_batch: 50
  bounce_maildir: /home/user/mail/example.com/bounce
  # or, when that mailbox only exists on a remote server:
  # bounce_imap_dsn: '%env(NEWSLETTER_BOUNCE_IMAP_DSN)%'
  newsletter_possible_origins: 'https://example.com https://www.example.com'
```

`bounce_maildir` is the maildir `pw:newsletter:bounces` reads — the mailbox
`framework.mailer.envelope.sender` points at. Null by default, which leaves the
command nothing to read. `bounce_imap_dsn` reads the same mailbox over IMAP (see
[Bounces](#bounces)); set one or the other, never both.

`newsletter_possible_origins` is the space-separated CORS allow-list for the
subscribe endpoint — a statically generated site posts to the origin where PHP
runs. It falls back to the conversation setting.

Templates are overridable per site under `/newsletter/`: `form.html.twig`,
`email.html.twig`, `confirm.email.html.twig`, `layout.html.twig`,
`confirmed.html.twig`, `unsubscribe.html.twig`, `unsubscribed.html.twig`,
`resubscribed.html.twig`, `unknown.html.twig`, `alert.html.twig`.

Override `confirmed.html.twig` first: the reader landing on
`/newsletter/confirm/{token}` just clicked your mail, and the default page offers
one sentence. Give them a next step — a `pages_list()` of what to read first, a
call to action, what the site sells.

## What it deliberately does not do

- **No open tracking.** A pixel fetch says "the mail was rendered", not
  "somebody read it", and a reader cannot meaningfully refuse it. Click tracking
  ([above](#click-tracking)) is off per audience and records nobody without their
  own dated consent. Every campaign records deliveries, failures, unsubscribes
  and bounces regardless; `utm_*` parameters stay the no-consent path, read in
  aggregate by your site's analytics.
- **No provider webhook ingestion.** A bounce is recorded when the transport
  refuses the mail at send time, when `pw:newsletter:bounces` reads it (see
  [Bounces](#bounces)), or when something posts it to
  `/api/newsletter/contact/{id}/bounce`. A provider's feedback channel (SES→SNS,
  a Mailgun or Postmark webhook) needs an adapter that does not exist yet.
  Complaints (FBL) are not ingested.
- **No SMS sending.** A phone number is a first-class field and can key a
  contact on its own — stored, tagged, segmented, exported — but nothing sends to
  it; a phone-only contact is never a campaign recipient.
- **No `OR` between the two sides of a broadcast.** `triggerWhen` selects
  subjects, `recipientWhen` selects contacts, and a broadcast is their product
  (implicit `AND`). An `OR` would be a condition on a (page, contact) pair, which
  no nesting on either side expresses. "This content to that audience, that
  content to this one" takes two automations today, and double-mails a contact in
  both segments: `TriggerLog` is unique on (automation, subject). Fixing it needs
  either a record keyed on (subject, contact) or an automation holding several
  (triggerWhen, recipientWhen) pairs with deduplicated recipients; neither is
  built.
- **No `stopWhen` on a broadcast.** It would mean "drop this contact from the
  remaining steps", which `recipientWhen` already does at each arming.
