---
title: 'Newsletter, segments and mailing automation for Pushword CMS'
h1: Newsletter
publishedAt: '2026-07-27 10:00'
toc: true
---

Collect contacts, hold their consent, broadcast to a segment, drip a sequence and
mail your readers when you publish — without a CRM, a worker, or a third-party
ESP.

Five entities and one command:

- **Audience** — a mailing list, and the scope of consent. One per brand.
- **Contact** — a person in an audience, with tags and free-form custom
  properties, plus the record of when and where they opted in.
- **Campaign** — one broadcast, to the whole audience or to a segment.
- **Automation** — a criteria-driven drip: enroll whoever matches, send the steps.
- **Content trigger** — publish an article, and the audience hears about it a day
  later. No campaign to write.

`pw:newsletter:tick`, run from cron, is the only moving part at runtime.

## Getting started

Create an audience in the admin (**Newsletter → Audiences**): a slug, the host its
public links belong to, a sender identity, the interests the public form may
attach, and — if you want the links tagged — an analytics source. Then drop the
form into any page:

```twig
{{ newsletter_form('altimood') }}
{{ newsletter_form('altimood', ['AmTrek']) }}
{{ newsletter_form(['altimood', 'altimood-promos']) }}
```

Given several audiences the form offers one ticked checkbox each, and a single
submission opens a subscription per ticked list — each with its own confirmation
mail where the list asks for one. An unknown slug fails the whole submission:
half a subscription is not what anyone ticked.

Finally, add the clock to the server's crontab:

```shell
* * * * * cd /path/to/app && php bin/console pw:newsletter:tick
```

## Consent

The audience *is* the consent scope: subscribing to one says nothing about any
other. A brand spread over seventeen locale hosts stays one audience, so nobody
is ever mailed twice; ten client sites are ten audiences, and an opt-in on one
never leaks to another. The host that served the form is recorded on the contact
as provenance, not as a scope.

Double opt-in is a per-audience flag, on by default: the contact is `pending`,
receives a confirmation mail, and only becomes mailable after clicking. Turn it
off to import a base that has already consented.

Every mail carries `List-Unsubscribe` and RFC 8058 one-click, so leaving never
depends on finding the link in the body. The unsubscribe page acts on `POST`
only — a mail scanner following the link must not opt anyone out on their behalf.

Leaving one list leaves that one. The confirmation page then offers the other
lists **of the same host** the address is subscribed to, to tick one by one or
drop in a single click; the host is the boundary, so one brand's unsubscribe
link never says what another brand knows about the address. Nobody sees that
page during a one-click opt-out — the `POST` is sent by the mailbox provider,
which shows the response to no one — but anyone opening the link themselves
lands on it, before or after the fact.

All public links (confirm, unsubscribe) are built from the audience host's
`base_live_url`, so they keep working when the site itself is statically
generated.

## Segments

One expression language drives a campaign's audience, an automation's enrollment
rule and its stop condition — a flat list of conditions, all of which must hold:

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

An empty list means the whole audience. There is no `OR` and no nesting: a second
automation is cheaper than an expression tree.

Two properties hold whatever you write:

- Every query is scoped to `status = subscribed`, so an unsubscribed or bounced
  address cannot be reached by any expression that can be written.
- `prop.x != y` skips contacts that have no `x` at all — a missing property is
  unknown, not "different from y".

**Count before you send.** The campaign and automation screens both have a button
that reports how many contacts currently match; the API returns
`estimatedRecipients` on any draft campaign.

## Campaigns

Author the body as Markdown (the block editor takes over when
`pushword/admin-block-editor` is installed), pick an audience, optionally a
segment, then **Send** or **Schedule**. `%name%` and `%email%` are substituted in
the subject and the body. The **analytics name** is the campaign's slug; leave it
empty and it is derived from the subject. Either way the send date is prefixed to
it when the campaign goes out.

Sending never blocks: the recipients are frozen into rows up front and the tick
drains them at the audience's cadence. That is also what makes it safe — a row
already sent is never re-sent, so an interrupted run, a deploy or a crash cannot
double-send. A contact who unsubscribed between arming and sending is skipped,
which the ledger records as `skipped` rather than as a failure.

**Send test** mails a copy of any campaign to arbitrary addresses with a `[TEST]`
subject prefix, touching no contact and no counter.

## Automations

An automation enrolls every subscribed contact matching `enrollWhen` and sends
its steps in order, each after its own delay. "Two mails after subscription" is an
empty `enrollWhen` and two steps.

`enrollWhen` says *who*, never *when*: the timing is the step's own delay. A
contact is enrolled as soon as they are subscribed — with double opt-in, the
moment they confirm, since a pending contact matches nothing — and the first
step's delay counts from there. So "two days after the opt-in is validated" is an
empty `enrollWhen` and a first step at 2880 minutes.

`stopWhen` is re-checked before each step, so someone whose situation changed
stops mid-sequence — do not send "discover us" to a customer who just booked.
Unsubscribing stops every active sequence.

**`enrollFrom`** is the guard worth knowing about: contacts registered before that
date are never enrolled. It defaults to the automation's creation date, so
switching one on cannot mail an entire existing base at once. It is a field, not
a criterion, because it must not be possible to forget.

Disabling an automation pauses it: enrollments keep their place and resume.

## Content triggers

An automation watches contacts. A content trigger watches the site: publish an
article and, a configurable delay later, everyone in the segment gets a mail
about it — unattended, with no campaign to write.

Set it up once (**Newsletter → Content triggers**): the hosts to watch, which of
their pages are worth a mail, who receives it, how long to wait, and the subject
and body to send.

```json
[{"field": "slug", "op": "startsWith", "value": "blog/"}]
```

| field | operators |
|---|---|
| `slug` | `startsWith`, `notStartsWith` |
| `template`, `parentPage` | `=`, `!=` — `parentPage` takes the parent's slug |
| `prop.<key>` | `=`, `!=`, `isSet`, `isNotSet` |

Same flat, ANDed shape as a segment, over pages instead of contacts. An empty
list means every published page of those hosts. The two rules read as one
sentence: `pageWhen` picks the article, `segment` picks the readers.

The subject and the body may quote four values of the page:

```
{{ page.h1 }}   {{ page.excerpt }}   {{ page.url }}   {{ page.mainImage }}
```

The braces are borrowed from Twig; nothing is evaluated. They are substituted
once, when the campaign is created, so what gets stored is plain Markdown —
which is why link absolutization and `utm_*` tagging work on it exactly as they
do on a hand-written newsletter. `{{ page.url }}` is built from the page's own
host and its canonical base URL, so it keeps working on a statically generated
site and across an audience that spans several locale hosts.

**What it produces is an ordinary campaign**, scheduled at `publishedAt + delay`
and sent by the same tick. During the delay you can read it, edit it, or cancel
it in the admin; afterwards it reports deliveries, unsubscribes and bounces like
any other. It is never rewritten: editing the page after the campaign exists
changes the article, not the mail already queued about it.

Three things it will not do:

- **Mail a back catalogue.** `triggerFrom` defaults to the moment of creation;
  pages published before it never trigger anything. Like `enrollFrom`, it is a
  field rather than a criterion, because it must not be possible to forget.
- **Mail the same page twice.** A trigger records the pages it has handled, so a
  missed tick only delays work and a tick that runs twice writes nothing new.
- **Mail a dead link.** A page unpublished or deleted before its campaign is
  armed cancels it. Publish it again and it gets its mail — the record went with
  the cancellation.

Not to be confused with [Page Update Notifier](/extension/page-update-notifier),
which mails *you* when content changes. This one mails your readers.

## Link attribution

Set an audience's **analytics source** (`utm_source`, e.g. `newsletter`) and every
link of its mails that points at one of your own sites carries:

```
https://example.com/article?utm_source=newsletter&utm_medium=email&utm_campaign=260728-janvier
```

`utm_campaign` is the campaign's slug, prefixed with the send date as `YYMMDD` so
a year of campaigns reads in order in any report. The name is derived from the
subject unless you set one, and the date is stamped when the campaign is armed —
one scheduled in March and delayed to April is dated April, which is when people
received it. Rewording the subject afterwards renames nothing.

Automation steps carry the automation's name instead, plus `utm_content=step-2`,
so a drip reads both as a whole and step by step. They get no date: a drip is not
sent on a day, it runs.

Four things it will not do: touch a link to somebody else's domain, touch a link
you tagged by hand, touch the unsubscribe link (leaving is not a visit), or tag
anything at all while the audience has no source set.

This is attribution, not click tracking — see below.

Related: a `/slug` link written in a body is made absolute against the site's
canonical base URL before the mail goes out, because a root-relative link has
nothing to resolve against in an inbox.

## Custom properties

Anything the site knows about a person — `lastBoughtProduct`, `plan`, `city` —
lives in `customProperties` and is readable from a segment as `prop.<key>`. Write
them from the API; a `PATCH` merges rather than replaces, and a `null` value
removes a key, so a caller that knows about one property never has to preserve
the others.

## Sending

`pw:newsletter:tick` is stateless and idempotent. Each run, under a lock:

1. schedules a campaign for each newly published page a content trigger matches,
2. arms scheduled campaigns whose date has passed,
3. drains pending recipients at the cadence,
4. enrolls contacts newly matching an enabled automation,
5. sends the automation steps that are due.

Content triggers come first so that a page whose delay has already elapsed goes
out in the pass that noticed it, rather than a minute later.

Pacing is derived from the last mail actually sent rather than from a sleep, so
the command returns immediately and a campaign resumes at the right rate whatever
happened to the previous run. `--batch` caps how many mails one run may send
(default 50, configurable with `newsletter.send_batch`).

```shell
php bin/console pw:newsletter:tick
php bin/console pw:newsletter:send 12    # arm a campaign now; the tick delivers
```

The transport is the site's own `MAILER_DSN`: each site owns its provider and its
reputation.

## API

Available when `pushword/api` is installed, under the same token authentication,
and self-describing at `/api/docs`.

```
GET    /api/newsletter/contact?audience=&status=&tag=&segment=&q=
POST   /api/newsletter/contact          # upsert on (audience, email)
GET    /api/newsletter/contact/{id}
PATCH  /api/newsletter/contact/{id}     # customProperties are merged
DELETE /api/newsletter/contact/{id}
POST   /api/newsletter/contact/{id}/unsubscribe
POST   /api/newsletter/contact/{id}/bounce

GET    /api/newsletter/campaign?audience=&status=
POST   /api/newsletter/campaign
GET    /api/newsletter/campaign/{id}    # includes estimatedRecipients while draft
PATCH  /api/newsletter/campaign/{id}    # drafts only
DELETE /api/newsletter/campaign/{id}
POST   /api/newsletter/campaign/{id}/schedule
POST   /api/newsletter/campaign/{id}/send
POST   /api/newsletter/campaign/{id}/test

GET    /api/newsletter/automation?audience=&enabled=
POST   /api/newsletter/automation
GET    /api/newsletter/automation/{id}  # includes enrollment counts
PATCH  /api/newsletter/automation/{id}
DELETE /api/newsletter/automation/{id}

GET    /api/newsletter/content-trigger?audience=&enabled=
POST   /api/newsletter/content-trigger
GET    /api/newsletter/content-trigger/{id}  # includes waiting pages and reach
PATCH  /api/newsletter/content-trigger/{id}
DELETE /api/newsletter/content-trigger/{id}
```

`POST /contact` follows the audience's double opt-in rule; sending
`"status": "subscribed"` skips the confirmation, which is what an import of an
already-consenting base needs.

The `segment` query parameter takes the same JSON criteria as a campaign, so an
external system can count an audience before asking for a send.

An automation carries its whole sequence: `steps` is an array in the order the
mails go out, and sending it again rewrites the sequence rather than appending to
it. `enrollFrom` defaults to the moment of creation there too, so a drip created
over the API cannot mail an existing base either.

A `GET` on a content trigger reports both sides of its rule — `waitingPages` and
`matchingContacts` — plus `campaignsCreated`. Deleting one keeps the campaigns it
produced: they are ordinary campaigns, and some of them have been sent.

Audiences have no endpoint: a consent scope and a sender identity are set up
once, in the admin.

## Configuration

```yaml
newsletter:
  send_batch: 50
  newsletter_possible_origins: 'https://example.com https://www.example.com'
```

`newsletter_possible_origins` is the CORS allow-list for the subscribe endpoint —
a statically generated site posts to the origin where PHP runs. It falls back to
the conversation setting, so a site that already declared where its forms are
posted from does not have to declare it twice.

Templates are overridable per site through the usual view resolution, under
`/newsletter/`: `form.html.twig`, `email.html.twig`,
`confirm.email.html.twig`, `layout.html.twig`, `confirmed.html.twig`,
`unsubscribe.html.twig`, `unsubscribed.html.twig`, `unknown.html.twig`,
`alert.html.twig`.

## What it deliberately does not do

- **No click or open tracking.** Per-link personal-data logging is a liability in
  the EU for what it returns on a content site. The feedback a campaign records
  is deliveries, failures, unsubscribes and bounces. The `utm_*` parameters above
  are a different thing: no redirect stands between the reader and the page, and
  nothing is written against a contact — your site's own analytics reads them,
  in aggregate.
- **No provider feedback ingestion.** A bounce is recorded when the transport
  refuses the mail at send time, or when something posts it to
  `/api/newsletter/contact/{id}/bounce`. An asynchronous bounce (SES→SNS,
  a webhook, a Return-Path mailbox) needs an adapter that does not exist yet.
- **No SMS.** Contacts are email-keyed; a phone number is a custom property.
