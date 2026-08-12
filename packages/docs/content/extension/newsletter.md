---
title: 'Newsletter, segments and mailing automation for Pushword CMS'
h1: Newsletter
publishedAt: '2026-07-27 10:00'
toc: true
---

Collect contacts, hold their consent, broadcast to a segment, drip a sequence and
mail your readers when you publish — without a CRM, a worker, or a third-party
ESP.

Four entities and one command:

- **Audience** — a mailing list, and the scope of consent. One per brand.
- **Contact** — a person in an audience, with tags and free-form custom
  properties, plus the record of when and where they opted in.
- **Campaign** — one broadcast, to the whole audience or to a segment.
- **Automation** — something happens, and a sequence of mails follows. What
  counts as "something happens" is a *trigger source*: a contact comes to match a
  rule, an article is published, or whatever a bundle of yours registers.

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
{{ newsletter_form('altimood', [], 'footer') }}
```

The form asks for a name and an email, nothing else. Given several audiences it
stays the same form and one submission opens a subscription per list — each with
its own confirmation mail where the list asks for one. An unknown slug fails the
whole submission: half a subscription is not what was asked for.

The call leaves a placeholder, not the form: like a conversation form, the markup
is fetched from the live host when someone loads the page. On a statically
generated site this call runs at build time, so anything per-visitor rendered
here — a CSRF token above all — would be one constant baked into a public file.

`@pushword/js-helper` drives both halves. `liveBlock()` fetches the placeholder's
`data-live` url and swaps the element for the form; the form comes back as a
`.live-form`, so the same pass binds its submit, posts it in the background and
replaces it with the answer. **The page needs js-helper**: without it the
placeholder stays empty and no form appears.

The third argument names where the form sits, and is kept on the contact as the
*where* of the opt-in. Left out, it falls back to the slug of the page the form
was rendered on — so two forms on one page are worth naming apart.

`newsletter_form_url()` takes the same arguments and returns the address alone,
for a front end that would rather fetch the form itself:

```twig
{{ newsletter_form_url('altimood', [], 'footer') }}
```

That is what a modal wants. Left to the placeholder, its form is fetched on every
page load for a panel most visitors never open; fetched as the modal opens, the
page pays nothing until someone asks. It returns null when no slug names an
audience, which is the empty string `newsletter_form()` renders in that case.

Finally, add the clock to the server's crontab:

```shell
* * * * * cd /path/to/app && php bin/console pw:newsletter:tick
```

## Styling

The form and the alert it is replaced by are plain Tailwind, reusing the
utilities the conversation form uses so the two look alike on one site. Nothing
extra to load: they are covered by the `@source` lines in js-helper's `app.css`,
which is what the default `vite.config.js` builds. If you build your stylesheet
from an entry point of your own, read *Tailwind content sources* in
[managing assets](/manage-assets) first — a bundle template Tailwind never
scanned renders unstyled.

Every element's utilities are a `pwNewsletter*Class` default. Redefine one as a
**twig global** and that element restyles, no template fork:

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

The value is HTML-escaped, so an arbitrary variant containing `&` or `>`
(`[&>p]:mt-0`) arrives mangled and matches nothing — put those in CSS. And
Tailwind has to *see* your override to emit it: a class named only in
`twig.yaml` is not scanned unless you add that file to your `@source` list.

For anything structural, override `/newsletter/form.html.twig` and
`/newsletter/alert.html.twig` in the site's views.

The pages behind the confirmation and opt-out links are a different case. They
have to render on the live host even when the site itself is a static build, so
`/newsletter/layout.html.twig` deliberately depends on none of the site's assets
and carries a small inline stylesheet instead of Tailwind. Override that layout
to brand them.

## CSRF

On by default, and there is no deployment that has to turn it off — a statically
generated site posting to another domain included. The form endpoint issues a
token, and the subscribe endpoint answers `403` to a post that does not carry
it. Nothing to wire: the placeholder fetches the form, the form comes back with
its token, js-helper posts it.

The token is signed with the app secret and carries its own expiry, rather than
pointing at a session. That is what lets it make the cross-domain round trip: a
session-bound token would need its cookie back, and from a static build on
another domain that cookie is a third-party cookie — Safari drops it outright,
other browsers partition it, and **every subscription would fail with `403`** on
exactly the deployment that needs the form fetched remotely in the first place.

What the token buys is not the protection of an authenticated action; there is
none, and the endpoint answers cross-origin on purpose. It is that a post has to
have fetched a form first, which a script spraying the endpoint has not done.

Turn it off only for a front end that posts the endpoint itself without ever
fetching a form:

```yaml
pushword:
    apps:
        - hosts: ['example.com']
          newsletter_csrf_protection: false
```

What a token does and does not buy here: this endpoint is anonymous by design, so
a forged cross-site post obtains nothing a direct `curl` would not — there is no
ambient authority for the token to protect. It does raise the cost of driving the
endpoint from someone else's page. The abuse it cannot address is guarded
elsewhere: the honeypot, the per-IP ceiling, and above all the double opt-in,
which keeps a subscription nobody asked for inert until the address owner clicks.

## Consent

The audience *is* the consent scope: subscribing to one says nothing about any
other. A brand spread over seventeen locale hosts stays one audience, so nobody
is ever mailed twice; ten client sites are ten audiences, and an opt-in on one
never leaks to another. The host that served the form is recorded on the contact
as provenance, not as a scope.

Double opt-in is a per-audience flag, on by default: the contact is `pending`,
receives a confirmation mail, and only becomes mailable after clicking. Turn it
off to import a base that has already consented.

**A contact with no address skips it.** Pending means waiting for a click on a
link sent by mail, and there is none to send — so a contact keyed on a phone
number alone is `subscribed` at once, whatever the audience asks. That is not a
hole in the consent model, it moves the burden: a number entered by hand carries
the consent of whoever entered it, and `source` records who (`admin:<user>`,
`import:…`, `api`) — the same evidence a hand-made opt-in already owes.

Every mail carries `List-Unsubscribe` and RFC 8058 one-click, so leaving never
depends on finding the link in the body. The link in the body is one click too:
clicking it opts the address out, it does not ask to confirm what was just
clicked. A link that makes people work is what turns an opt-out into a spam
report.

**A click, not a fetch.** The page acts on `GET` only when the browser sends
`Sec-Fetch-User: ?1`, the header marking a navigation it attributes to whoever
is driving it. A plain HTTP fetch sends no `Sec-Fetch-*` at all and a prefetch
sends them without this one, so both land on a confirmation page and opt nobody
out; the `POST` behind that page's button is the same one RFC 8058 sends.

Know what this buys. It stops **fetchers** — which is what a mail scanner
following a link almost always is — and browser prefetch. It does **not** stop a
scanner driving a real browser: Chromium marks a scripted top-level navigation
as user-driven and sends `?1`. Browsers too old to send the header (Safari
before 16.4) read as a fetch, which costs their reader one click and never the
wrong outcome. Whatever slips through is recoverable: the page it lands on
carries an undo.

**Undo is one click too.** The page carries a button that puts the address back,
with no confirmation mail: the token reached that mailbox and nowhere else,
which is the proof a confirmation would ask for again. It is what makes the
opt-out safe to do in one click, and it hands the campaign back the unsubscribe
it was credited with. A bounced address is not revived this way — the mail
server refused it, and a click on a page says nothing about that. Nor does it
restart an automation the opt-out stopped; a half-finished drip does not resume.

Leaving one list leaves that one. The page it lands on then offers the other
lists **of the same host** the address is subscribed to, to tick one by one or
drop in a single click; the host is the boundary, so one brand's unsubscribe
link never says what another brand knows about the address. Nobody sees that
page during an RFC 8058 opt-out — the `POST` is sent by the mailbox provider,
which shows the response to no one — but anyone opening the link themselves
lands on it.

All public links (confirm, unsubscribe) are built from the audience host's
`base_live_url`, so they keep working when the site itself is statically
generated.

### Subscribed is not the same as mailable

A contact is keyed on an address **or** on a phone number — a booking taken over
the phone, a client met on site, a number on a paper form. Both fields are
optional on their own and at least one is required; `phone` is kept as digits
and a leading `+`, and is unique per audience like the address.

Consent and reachability then become two questions:

- `subscribed` says they agreed to hear from the site.
- **mailable** — subscribed *and* holding an address — says a mail can carry it.

Everything that sends asks the second. A contact with no address is never a
campaign recipient, never enrolled in an automation, and never counted in what a
send will reach: the audience page, the campaign preview and the API's audience
payload report `subscribed` and `mailable` side by side, because conflating them
is how somebody believes a campaign reached 1406 people when it reached 1137.

They are otherwise ordinary contacts — tagged, segmented, exported, and
filterable with `isSet` / `isNotSet` on `email` and `phone`:

```json
[{"field": "email", "op": "isNotSet"}]
```

**No country is inferred.** `+33 6 12 34 56 78` and `+33612345678` are one row;
`+33 (0)6…` is another, because deciding that the `(0)` is a French trunk prefix
is a guess, and a wrong guess silently merges two people. Normalise at the
import if a base needs it.

**An identifier somebody else holds is refused, never moved.** Writing a number
onto a contact when another row of the same audience already carries it — or an
address, the same rule read the other way — comes back as `409` from the upsert
and as a validation error from `PATCH` and the admin form, naming the row that
holds it. The two rows may well be one person, and that is exactly why somebody
is writing it; but joining them means deciding which consent record survives and
which token the unsubscribe links already in inboxes keep working with. So it is
a merge somebody asks for, never the side effect of filling a field in.

**The public form stays email-only.** A number reaches the base over the API, or
through the admin's *Opt in a contact* — never from a page anybody can post to.

### Two rows, one person

The row holding the **address** is the one that survives a merge, whichever side
asked for it. That is not a preference for mail: the address is what the confirm
and unsubscribe links are keyed on, so keeping the other row would quietly break
every link already in somebody's mailbox, and the consent record the addressed
row carries is the one that has to be produced if the opt-in is questioned.

It keeps its id, its token, its status and its consent dates, and it gains:

- the number,
- the name and the language, **only where it had none** — what somebody wrote on
  the kept row is not overruled,
- the tags, added to its own,
- the custom properties it was missing,
- every campaign, enrollment and drip step either row was sent. A merge costs no
  history; that is what makes it something other than deleting a row. Where both
  rows have a line for the same campaign or the same run of an automation, the
  kept row's own line stays and the duplicate goes.

Only an addressed row and a phone-only row can be joined. **Two addresses are two
people** until somebody says otherwise, and no rule can pick which of the two
consent records to throw away — as with a kept row that already holds a different
number, the merge is refused rather than arbitrated. Delete one row, or write the
identifier onto the row you mean to keep.

In the admin, a save refused for a taken identifier offers the join under the
form: it names both rows, says which one stays, and performs it in one click.
Over the API, `?merge=true` on the upsert or on `PATCH` asks for the same thing —
`409` when it still cannot be honoured. Note that `PATCH` may then answer with
**another id than the one in the path**: patching an address onto a phone-only
row leaves the person on the addressed row.

```bash
curl -X POST 'https://example.tld/api/newsletter/contact?merge=true' \
  -H 'Authorization: Bearer <token>' -H 'Content-Type: application/json' \
  -d '{"audience": "readers", "email": "reader@example.tld", "phone": "+33612345678"}'
```

A merge never sends anything and never re-opens a confirmation: the address was
already confirmed, or was not, and gaining a number does not change that.

### One contact row per list

A contact belongs to exactly one audience — the pair `(audience, email)` is
unique. Somebody on three lists is three rows, each with its own tags, custom
properties and consent record: one shared row could not carry three confirm
dates, three opt-in IPs, or an unsubscribe from one list and not the others.

The consequence in the admin: the **audience** select on a contact *moves* that
subscription, it does not add one. Every row for the same address is listed
under **Subscriptions** at the bottom of the contact page, whatever its audience
and status, so the person is visible and not only the subscription being edited.

Two hosts wanting the same address twice is therefore a matter of two audiences —
one per host — not of anything on the contact. `optinHost` is provenance, never
a scope; an audience spanning several locale hosts stays one list and one row,
which is what keeps a reader from being mailed twice.

### Opting somebody in by hand

**Newsletter → Contacts → Opt in a contact** opens a subscription from the admin:
pick a list, give an address or a phone number, and the audience's double opt-in
rule decides the rest — the confirmation mail goes out exactly as it would from
the public form. A number alone subscribes at once; there is nothing to confirm.
Tick *they already consented* only for consent you can produce (a paper form, a
written reply): it skips the mail and subscribes at once, as `status:
subscribed` does over the API.

The row records who opened it (`source: admin:<user>`), which is the evidence a
hand-made opt-in owes. There is still no **New** button: a contact written field
by field would have no recorded opt-in at all.

From a contact, the same page reached through **Add to another list** comes with
the address prefilled — the way to put one person on a second list. **Confirm by
hand**, **Unsubscribe** and **Put back on the list** are the other three, and go
through the same code as the public links, so campaign counters and running
automations stay in agreement.

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
| `email`, `phone` | `=`, `!=`, `isSet`, `isNotSet` |

An empty list means the whole audience.

**A rule that needs `OR` says so**, and then every condition of that list belongs
to the one operator:

```json
{"any": [
  {"field": "tag", "op": "has", "value": "AmTrek"},
  {"field": "tag", "op": "has", "value": "AmTrek-VIP"}
]}
```

`{"all": [...]}` spells the default out. Two campaigns do not replace that `any`:
a contact carrying both tags would be in both, and be mailed twice.

**A condition may itself be a group**, which is how you say "either of those tags,
but only among the customers". Keep the flat form for everything it can express —
one operator is read at a glance — and reach for nesting when the alternative is
two campaigns that overlap:

```json
[
  {"field": "prop.lastBoughtProduct", "op": "isSet"},
  {"any": [
    {"field": "tag", "op": "has", "value": "AmTrek"},
    {"field": "tag", "op": "has", "value": "AmTrek-VIP"}
  ]}
]
```

A nested group always names its operator, `all` included: coming back from the
textarea as a bare list, it would be read as a condition.

Two properties hold whatever you write:

- Every query is scoped to `status = subscribed`, so an unsubscribed or bounced
  address cannot be reached by any expression that can be written.
- `prop.x != y` skips contacts that have no `x` at all — a missing property is
  unknown, not "different from y".

**The admin writes it for you.** Every rule — a campaign's segment, an
automation's trigger, its recipients, its stop condition — is edited as rows: a
field, an operator, a value, with `All`/`Any` above them and one level of
grouping. The fields offered are the ones the rule's own language declares, so
picking `page` as an automation's source swaps the vocabulary under the rows,
and values are suggested from what the site already has — its tags, its
templates, the slugs that name a section, the property keys in use.

**Edit as text** hands the JSON back at any point, which is what everything above
is written in. A rule the builder cannot show — a `pages_list` search — simply
stays text. Nothing about the stored format changes either way: the builder types
into the same field, and the same validator has the last word.

**Count before you send.** The count under each rule follows what is being typed:
how many subjects are waiting, or how many subscribed contacts a broadcast would
reach, with the first few named. A malformed rule answers there, in the words the
save would have used. Two things it cannot do: a *trigger* has to be saved once
before it can be counted — subtracting what the automation has already handled
needs the automation to exist — and a fresh automation counts zero by
construction, its **Active from** being now, which is what *Ignore the start
date* is for. The campaign and automation lists keep a button that reports the
same count on a saved row; the API returns `estimatedRecipients` on any draft
campaign.

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
subject prefix, touching no contact and no counter. Once the campaign carries
translations it also asks which language to proofread.

### Languages

One campaign carries one body per locale, so an audience spanning several locale
hosts is mailed once and each reader gets their own language. The alternative —
one campaign per language, each carrying
`[{"field":"locale","op":"=","value":"xx"}]` — mails a bilingual reader twice
and has to be armed eight times.

The **Languages** fieldset holds them as JSON, one entry per locale:

```json
{
  "de": {"subject": "Hallo", "preheader": "…", "bodyMarkdown": "Lies das."},
  "it": {"subject": "Ciao"}
}
```

What a given reader is sent is resolved when the mail goes out, in three steps:

1. `translations[<the contact's locale>]`,
2. otherwise its language part — `de-ch` reads the `de` written once for eight
   languages over seventeen hosts,
3. otherwise the campaign's own subject, preheader and body.

Each of the three fields falls back on its own, so translating a subject without
its body is allowed and means what it looks like. A blank field is not stored at
all, which is what keeps a locale somebody opened and left alone from mailing an
empty body. **Nobody ever receives an empty mail**: a missing translation sends
the default text, and the fieldset says how many of the languages the audience is
read in are covered before anyone presses Send.

Over the API, `translations` merges the way `customProperties` does, one step
deeper: a `PATCH` writes only the fields it names, so sending the German subject
keeps the German body and the seven other languages. A field set to `""` clears
that field alone, and a locale set to `null` drops the whole entry — which is the
only way to take one back. Locales are matched as they are stored, lowercased and
dash-separated, so `"DE"` and `"de_CH"` address `de` and `de-ch`.

Resolution happens at send, not at arming: a recipient row freezes *who*, never
*what* — as is already true of `%name%`. Freezing the rendered body per recipient
would multiply the ledger by the body size for no gain and make fixing a typo
mid-campaign impossible.

Counters stay per campaign. A campaign is one broadcast; splitting `sentCount` by
language is a reporting question, answerable from `CampaignRecipient` joined to
`Contact.locale` if it ever comes up.

The segment stays orthogonal: `locale` remains a segment field, so one campaign
per market — different offers per market, not the same offer translated — keeps
working exactly as before. This removes an obligation, it does not impose a
model.

### Who it went to

The counters on a campaign say how many; **Recipients** opens the rows behind
them — one per contact, with its state (`pending`, `sent`, `skipped`, `failed`,
`bounced`) and, for a failure, the message the transport gave back. That message
lives nowhere else: `failedCount` only sums the rows. The button appears once a
campaign has been armed, which is when the rows exist, and the list is read-only:
a row records a send that happened, and editing it would make the ledger disagree
with what left the server.

## Automations

One screen covers "two mails after subscription" and "announce every article the
day after it goes out". An automation is three things:

- a **trigger source** — what it watches;
- **`triggerWhen`** — which of that source's subjects deserve the sequence,
  written in that source's own vocabulary;
- **steps** — the mails, in order, each after its own delay.

Two sources ship with the bundle, and a bundle of yours can add more (see
[Custom trigger sources](#custom-trigger-sources)).

**`activeFrom`** is the guard worth knowing about, whatever the source: nothing
that happened before that date ever triggers anything. It defaults to the
automation's creation date, so switching one on cannot mail an entire existing
base, nor announce an entire back catalogue, at once. It is a field rather than a
criterion, because it must not be possible to forget.

Disabling an automation pauses it: sequences under way keep their place and
resume, and nothing new is picked up.

### Two ways a sequence is delivered

The source decides, per occurrence, and it decides by answering one question: is
this about *one person*, or about *the site*?

**About one person** — a new contact, a customer who ordered — and the steps are
dripped at them. An enrollment holds their place, and `stopWhen` is re-checked
before each step, so someone whose situation changed stops mid-sequence: do not
send "discover us" to a customer who just booked. Unsubscribing stops every
active sequence.

**About the site** — an article was published — and each step becomes an ordinary
scheduled **campaign**, broadcast to whoever `recipientWhen` selects. `stopWhen`
has no meaning there and needs none: a campaign's recipients are resolved when it
is armed, so someone who stopped matching `recipientWhen` between step one and
step two is simply not in step two.

That second half is worth stating plainly, because it is what makes the two
halves compose: **`recipientWhen` is read at send time, not at trigger time.** A
publication can therefore mail a state of the reader that has nothing to do with
the publication —

```json
[{"field": "prop.lastSeenAt", "op": "olderThan", "value": "30d"}]
```

— *every article, but only to subscribers we have not seen in a month.* The
article picks the moment; the contact's own history picks the audience.

### Watching contacts

`triggerWhen` is an ordinary [segment](#segments). Every subscribed contact who
comes to match is enrolled once and receives the steps. "Two mails after
subscription" is an empty `triggerWhen` and two steps.

It says *who*, never *when*: the timing is the step's own delay. A contact is
enrolled as soon as they are subscribed — with double opt-in, the moment they
confirm, since a pending contact matches nothing — and the first step's delay
counts from their registration. So "two days after the opt-in is validated" is an
empty `triggerWhen` and a first step at 2880 minutes.

### Watching pages

Publish an article and, a delay later, everyone `recipientWhen` selects gets a
mail about it — unattended, with no campaign to write. Set the source to `page`,
name the hosts to watch, and write `triggerWhen` over pages instead of contacts:

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

Same shape as a segment, over pages instead of contacts — including
`{"any": [...]}`. An empty list means every published page of those hosts. The two
rules read as one sentence: `triggerWhen` picks the article, `recipientWhen` picks
the readers.

Or write it as a [`pages_list`](/pages-list) search, in the words you already use in
a template. What you type is translated into the list above and stored as one, so
you can always see what it understood:

```
ancestor:blog AND (tag:featured OR tag:pinned)
```

The search grammar is wider than this vocabulary, and the vocabulary wins: a
`title:` search, or a `children`, is refused by name rather than quietly compiled —
an automation has no page being rendered to be relative to.

Before reaching for `any`, reach for what already groups pages — the same two axes
a `pages_list` search leans on. A blog split in rubrics, whose articles sit at the
root and are attached by `parent`, shares no slug prefix and would otherwise
need one automation per rubric. Either axis covers it in one condition, and unlike
an enumeration it covers the rubric added next month too:

```json
[{"field": "ancestor", "op": "=", "value": "blog"}]
[{"field": "tag", "op": "has", "value": "blog"}]
```

Whatever the rule, the hosts, the `activeFrom` and the pages already handled are
ANDed with the whole of it: `any` widens which pages match, never past those.

### What a step may quote

A step's subject and body may quote what the occurrence lends. A page lends six
values:

```
{{ page.h1 }}           {{ page.excerpt }}   {{ page.chapeau }}
{{ page.mainContent }}  {{ page.url }}       {{ page.mainImage }}
```

A contact lends `{{ contact.name }}` and `{{ contact.email }}`; another source
lends whatever it says it lends.

The braces are borrowed from Twig; nothing is evaluated. They are substituted
once, when the occurrence is handled, so what gets stored is plain Markdown —
which is why link absolutization and `utm_*` tagging work on it exactly as they
do on a hand-written newsletter. A name nobody lent is left where it stands, so a
typo shows up in the preview instead of vanishing. `{{ page.url }}` is built from
the page's own host and its canonical base URL, so it keeps working on a
statically generated site and across an audience that spans several locale hosts.

The values are frozen when the sequence starts. A three-step drip quotes the same
title in its last mail as in its first, even if the article was retitled in
between: a sequence has to read as one conversation.

`{{ page.excerpt }}` is the article's own opening, and deliberately never the
`searchExcerpt` custom property: that one is written for a search result page,
and a meta description read in an inbox sounds like one. Three candidates, in
order:

1. **the chapeau** — what sits before `<!--break-->`, as authored;
2. **the intro** — every paragraph before the first heading, however many, on a
   page that asked for a table of contents;
3. **the opening paragraph alone**, as text, cut at 300 characters on a word
   boundary. An extract rather than an accroche, hence the cut.

The third one skips whatever precedes it: an article may open on a figure or on
an interactive block, and the labels inside that block are not an opening. A
page holding no paragraph at all — a tool with a heading and a widget — lends
nothing, and the mail keeps its title, its image and its link. That is the
intended outcome, not a gap to fill: an empty excerpt says less, but it says
nothing false. Give such a page a `<!--break-->` if it deserves a real accroche.

`{{ page.chapeau }}` asks for the lede itself, whatever the excerpt resolved to.
On a page with no break it is empty — and on a page with a break but no table of
contents it renders exactly what `{{ page.excerpt }}` does, so quote one or the
other, not both.

`{{ page.mainContent }}` answers the other question: not what the author wrote as
an opening, but how much of the article the mail can carry. It is the page's
paragraphs from the very top — above the `<!--break-->`, so a chapeau leads here
too — run together with a blank line between them and cut at 900 characters on a
word boundary.

Reach for it when the newsletter is meant to be worth opening on its own, and for
`{{ page.excerpt }}` when the mail only points at the page. The difference shows
on an article that opens on a one-line hook: the excerpt stops at that line,
which is all the author wrote as an opening, while `{{ page.mainContent }}` keeps
reading until it has enough to make clicking through a decision rather than a
guess.

It quotes paragraphs and nothing else. Headings, figures and interactive blocks
are left out rather than flattened: run together as text they read as noise, and
a budget spent on a widget's labels is a budget wasted. A page holding no
paragraph lends nothing here either.

**The subject gets plain text, the body gets the markup.** An `h1` commonly
carries an `<em>`, a `<br>` or a `<span class="…">`, and an excerpt falling back
to the article's opening is rendered HTML by construction — in a subject line
that would reach the inbox as literal markup, so tags are dropped there (each one
leaving a space, so a `<br>` does not glue two words) and entities decoded. The
body keeps everything: inline HTML is legitimate Markdown.

### What a drip records

A broadcast reports through the campaigns it produces. A drip has no campaign, so
it keeps its own ledger: **Deliveries** in the newsletter menu, one row per step
per contact, with the subject as that person actually received it — placeholders
already filled — and the state it ended in (`sent`, `failed`, `bounced`).

It is worth knowing about for one reason: a step the transport refuses is stepped
over rather than retried, because retrying it forever would freeze that contact's
sequence at the same mail. The row is the only lasting record that somebody is
missing a step, and the only place the transport's reason is kept.

A bounce or an unsubscribe is credited to the last mail that person actually
received, whichever half of the bundle sent it. When that is a drip step it is
marked on its own row and no campaign is charged for it — a campaign's counters
mean "caused by this send", and a send somebody had already read something else
after did not cause anything.

### What a broadcast produces

**Ordinary campaigns**, one per step, scheduled at `occurredAt + delay` and sent
by the same tick. During the delay you can read them, edit them, or cancel them
in the admin; afterwards they report deliveries, unsubscribes and bounces like
any other, and each carries the automation it came from. They are never
rewritten: editing the page after the campaign exists changes the article, not
the mail already queued about it.

**One language per campaign, when there is more than one.** Seventeen locale
versions of an article are seventeen pages, so a page automation produces
seventeen campaigns — and `recipientWhen` is one rule for all of them. As soon
as the audience holds contacts in more than one locale, each campaign's segment
is narrowed by the language of the page that triggered it, so a reader gets the
article once rather than once per language. An audience read in a single
language has nothing to disambiguate and keeps its rule exactly as written.

The narrowing is ANDed onto `recipientWhen`, never replaces it: an `any` group
is kept whole. Writing `locale` into the rule yourself still works and still
means what it says — one campaign per market, for editorial reasons rather than
technical ones, is a model this does not take away.

Three things it will not do:

- **Mail a back catalogue.** `activeFrom`, as above.
- **Mail the same subject twice.** An automation records the subjects it has
  handled, so a missed tick only delays work and a tick that runs twice writes
  nothing new.
- **Mail a dead link.** A page unpublished or deleted before its campaign is
  armed cancels it. Publish it again and it gets its mail — the record went with
  the cancellation. Once a step has been armed the article has been announced,
  and the remaining steps are cancelled without clearing the record.

Not to be confused with [Page Update Notifier](/extension/page-update-notifier),
which mails *you* when content changes. This one mails your readers.

### Custom trigger sources

Anything your application knows how to watch can start a sequence. Implement
`TriggerSource` and tag the service `pushword.newsletter.trigger_source`; it then
appears in the admin's source list, its vocabulary validates in the same
textarea, and the steps, the delays, the segment and the reporting are the ones
every other automation already uses.

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

Four things worth knowing:

- **`subjectId` is the identity an automation remembers having handled.** Return
  the same subject twice and the second one is dropped, so a source written
  without a `LIMIT` is still safe to call.
- **`contact` picks the delivery**, per occurrence. Set it, and the steps are
  dripped at that person; leave it null, and they are broadcast to
  `recipientWhen`.
- **`occurredAt` starts the clock**, and it is the event's own date rather than
  the tick's — a delayed tick still mails on time.
- **Remembering is not yours to do.** The automation writes the marker; your
  source exposes the query and stays stateless.

Your vocabulary is an `AbstractCriteria` subclass — the same base the segment and
page languages extend — so `{"any": [...]}`, the JSON round trip through the admin
textarea, and the error messages come for free. Its `FieldRegistry` is what says
how each field compiles. The admin's condition builder reads it too: whatever
`FIELD_OPERATORS` declares becomes rows and dropdowns, and `DURATION_OPERATORS`
is what makes an editor ask for an amount and a unit rather than for `7d`.

To fill those dropdowns with what your application already holds — the statuses
in use, the products bought — implement `CriteriaSuggestions` for your criteria
class and tag it `pushword.newsletter.criteria_suggestions`:

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

They stay suggestions: a rule may name a status nobody has yet, which is what
lets an automation be written before the thing it waits for exists. A source that
ships none is offered plain text boxes, and its language validates them exactly
the same.

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

This is attribution, not click tracking — the difference, and the consent model
that separates them, is the next section.

Related: a `/slug` link written in a body is made absolute against the site's
canonical base URL before the mail goes out, because a root-relative link has
nothing to resolve against in an inbox.

## Click tracking

Off by default, and staying off is the design: attribution answers "which
campaign brought the traffic" with no redirect and nothing written against a
person, while click tracking answers "which contact clicked which link" — and
that answer is personal data. So it only ever happens behind **two consents**,
and the second is never inferred from the first:

1. **The audience's switch.** `clickTracking` on the audience, false by
   default, editable in the admin and over the API. It says the site is willing
   to track, not that anybody agreed to be.
2. **The contact's own dated consent.** `clickTrackingConsentAt` — a datetime
   rather than a boolean, because the date is what has to be produced if the
   logging is ever questioned. Collecting it can stay the site's business: a
   checkbox in its own client area, worded by its own privacy policy, written
   over `PATCH /api/newsletter/contact/{id}` or on the contact's admin form.
   Flipping the audience switch tracks nobody: every contact stays untracked
   until their own consent is written, one by one.

The bundle also offers one collection point of its own: **the double opt-in
mail**. When the audience tracks, the confirmation mail states the purpose in
one muted line, then carries two links — a bare *confirm* put forward as the
button, *confirm (anonymized links)* one line beneath. Either opens the subscription; only the first also writes the
dated consent, and only for a navigation the browser attributes to a person
(`Sec-Fetch-User`, the same test the unsubscribe link uses), so a mail scanner
following every link of the mail cannot consent to per-click logging on the
reader's behalf — it merely confirms the subscription, exactly as the plain
link always has. A site that overrode `confirm.email.html.twig` keeps its
single button until its template renders the new `confirmTrackingUrl` variable
(null whenever the audience does not track).

Both gates open, and the links of that contact's mails — campaigns and
automation steps alike — are rewritten through `GET /newsletter/c/{payload}`,
which records the click (contact, campaign or step, destination, when) and
answers `302` to the destination. Either gate closed, and the mail keeps
exactly the links it carries today, `utm_*` tags included: a base where three
people consented is tracked for three people and nobody else.

What the rewrite touches, and what it never touches:

- **Only the body's `http(s)` links.** The template's own links — the
  unsubscribe link first among them — are out of reach by construction: the
  body is rewritten before the template wraps it. Leaving is not a visit, and
  not a click worth recording either.
- **The UTM pass runs first**, so the URL recorded and redirected to is the
  tagged one — the destination's analytics reads exactly what it read before.
- The plain-text part keeps its links as written: it is the Markdown source,
  authored to be read raw.

The payload is signed with an HMAC on the kernel secret. The endpoint redirects
wherever the payload says, so an unsigned payload would be an open redirect
wearing your domain: a signature that does not recompute is a `404`, never a
redirect.

**Withdrawal is one write.** Setting `clickTrackingConsentAt` to null — over
the API or by clearing the field in the admin — purges every click recorded
for that contact: the rows were collected under a consent and go with it.
Deleting the contact takes them along the same way. The links already sitting
in their inbox are not broken by any of it: both gates are asked again at
click time, so those links keep redirecting and stop recording.

What it reports, where:

- the campaign's click count, next to sent, failed, unsubscribed and bounced —
  on the campaign's admin page and in the API `stats`;
- a clicks column on the campaign's **Recipients** ledger, per reader;
- `GET /api/newsletter/campaign/{id}` details `clicksByUrl`: each link, how
  many clicks, how many distinct readers;
- a drip step's clicks are recorded against the automation and the step's
  position — a drip has no campaign, here as everywhere.

## Custom properties

Anything the site knows about a person — `lastBoughtProduct`, `plan`, `city` —
lives in `customProperties` and is readable from a segment as `prop.<key>`. Write
them from the API; a `PATCH` merges rather than replaces, and a `null` value
removes a key, so a caller that knows about one property never has to preserve
the others.

## Sending

`pw:newsletter:tick` is stateless and idempotent. Each run, under a lock:

1. asks every enabled automation's source what newly happened, and starts a
   sequence at each of them — an enrollment, or a campaign per step,
2. cancels the campaigns whose subject stopped deserving them,
3. arms scheduled campaigns whose date has passed,
4. drains pending recipients at the cadence,
5. sends the drip steps that are due.

Triggering comes first so that something whose delay has already elapsed goes out
in the pass that noticed it, rather than a minute later.

Pacing is derived from the last mail actually sent rather than from a sleep, so
the command returns immediately and a campaign resumes at the right rate whatever
happened to the previous run. `--batch` caps how many mails one run may send
(default 50, configurable with `newsletter.send_batch`).

The cadence itself is **seconds between two mails**: `rateSeconds` on the
audience, 30 by default, which a campaign may override with its own for a
one-off — a small list you want out now, or a big one your provider would rather
receive slowly. It is what sets the rate; a minutely cron at 30 s sends two mails
a run and nothing you do to `--batch` changes that. The batch is the other
bound, and it only bites when catching up: after an outage the elapsed time
allows hundreds at once, and the cap is what keeps a resumed campaign from
becoming the burst the cadence existed to avoid.

```shell
php bin/console pw:newsletter:tick
php bin/console pw:newsletter:send 12    # arm a campaign now; the tick delivers
```

The transport is the site's own `MAILER_DSN`: each site owns its provider and its
reputation.

## Bounces

A mail refused at send time is recorded as it happens. The harder case is the
one that comes back minutes later: the relay accepted the message, the remote
server refused it afterwards, and the failure is returned as a separate mail to
the **envelope sender**, which is a different address from the `From:` a reader
sees. Left unread, a dead address stays subscribed and is retried by every
campaign, which is how a sending reputation is spent.

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

On a shared host this needs nothing else: a bounce is a file, delivered next to
every other mailbox, so there is no extension to compile, no webhook to expose
and no credentials to store.

### When the mailbox is not on this machine

The filesystem premise holds for a site whose PHP and whose mail live on the same
host. It fails for the other common arrangement — the app on a VPS or in a
container, the mail at a provider — where the envelope sender's mailbox is
reachable by IMAP and by nothing else. Point the command at it instead:

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
STARTTLS instead. Percent-encode the credentials — a generated password holds
`@` and `/` often enough that not doing so authenticates as somebody else.

Everything below is unchanged: the same parsing, the same `5.x.x`-only rule, the
same multi-audience drop. What replaces the `cur/` move is `\Seen`, and the
command searches `UNSEEN` on the next run — same property, same consequence when
it fails.

Two caveats a maildir does not have:

- **Set one or the other, never both.** They are two ways to read one mailbox,
  and configuring both stops the command with a message saying so. The check
  happens when the command runs rather than when the container builds, because
  an `%env()%` DSN is still an unresolved placeholder at build time and would
  read as set whatever the environment holds.
- **Nothing else may read that mailbox.** That is already the premise — a
  mailbox nobody reads by hand — but anything that marks messages seen, a webmail
  session included, takes them out of the command's reach. Pointing the DSN at a
  real inbox is a temptation a filesystem path never offered.

One thing it does not reproduce: the maildir reads 64 KB off disk per message and
stops, while IMAP hands back what it asked for in one piece — so a returned
message still crosses the wire whole, and only the parse is bounded.

### What the command does with what it reads

- it parses the `message/delivery-status` part, never the human-readable one,
  which the remote server writes in its own language and layout,
- it acts only on reports about a message this site really sent — see
  [below](#only-reports-about-mail-this-site-sent),
- it acts on **permanent failures only** (`Status: 5.x.x`). A 4.x.x is a mailbox
  that was full an hour ago, and dropping a reader over one loses an address the
  next retry reaches,
- an address held on several audiences leaves all of them: the server refused
  the address, not one of the lists,
- a bounce for somebody on no list is counted and reported, never acted on. The
  same mailbox collects the failures of everything else the app sends,
- a message it has read is moved to `cur/` with the seen flag — or flagged
  `\Seen` over IMAP — which is what keeps the next run from reading it again. One
  that cannot be marked is only counted: re-reading it costs nothing, since
  marking an address that already bounced is a no-op.

### Only reports about mail this site sent

The bounce mailbox is a real address, published in the `Return-Path` of every
newsletter, and by design it accepts mail from any server on the internet. A
`multipart/report` arriving there proves nothing on its own: anybody can write
one naming `Final-Recipient: someone@example.com`, and acting on it would let
anybody take anybody off the list — permanently, since a bounce is never taken
back.

So a report has to say **which message** failed, and that message has to be one
this install issued. Every newsletter goes out with a `Message-ID` of its own
making:

```
Message-ID: <nl.<nonce>.<signature>@example.com>
```

The signature is an HMAC of the nonce and the recipient's address, keyed on the
site's `APP_SECRET`. A delivery report returns a copy of the message that failed
— the whole thing as `message/rfc822`, or its headers alone as
`text/rfc822-headers` — and a `Final-Recipient` is honoured only when one of the
ids in that copy recomputes for that same address.

Nothing is stored: the id carries its own evidence, so there is no ledger to
keep and no key to distribute. Producing a valid one needs the secret, or a copy
of a mail really sent to that address — which is exactly what a mail server
returning our message has. Lifting the headers out of a genuine bounce of one's
own and putting somebody else's address on them does not work either: the
signature names the recipient.

A report that proves nothing is counted as `unverified` and left alone. That
number is worth watching, because it has two causes:

- somebody is writing reports at your list, or
- your relay returns no copy of the message it failed to deliver — RFC 3464 only
  recommends it. Then no bounce is ever acted on, and dead addresses stay
  subscribed. `--dry-run` on a mailbox with real bounces in it is how you find
  out which.

### Hearing about it

`--notify=ops@example.com` mails the summary, **only when something actually
moved** — at least one address dropped or one permanent failure recorded. The
command runs four times an hour; a site that mails its operator every run trains
them to filter it, which costs the one message that mattered. Zero movement, zero
mail, and `--dry-run` never mails at all.

The sender is `notification_email_from`, falling back to `noreply@<host>` like
every other notification the install sends.

A bounced contact is terminal. `resubscribe()` refuses to revive one, because a
click says nothing about a mail server's refusal; only a new explicit opt-in
does.

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

`POST /contact` follows the audience's double opt-in rule; sending
`"status": "subscribed"` skips the confirmation, which is what an import of an
already-consenting base needs.

The `segment` query parameter takes the same JSON criteria as a campaign, so an
external system can count an audience before asking for a send.

An automation carries its whole sequence: `steps` is an array in the order the
mails go out, and sending it again rewrites the sequence rather than appending to
it. `activeFrom` defaults to the moment of creation there too, so an automation
created over the API cannot mail an existing base either.

`source` decides which vocabulary `triggerWhen` is validated against, so a request
changing both must send the source — an unknown one, or a rule written in the
wrong language, is a `400` naming which of the three rules was wrong.

A `GET` reports both sides — `waiting` (subjects the source has for it right now)
and `matchingContacts` (what `recipientWhen` reaches) — plus `handled` and the
enrollment `stats`. Deleting an automation drops its enrollments and its markers
but keeps the campaigns it produced: they are ordinary campaigns, and some of them
have been sent.

An audience can be created over the API too, so a site is set up without opening
the admin first. Its `mainHost` must be one of the configured Pushword hosts — an
unknown one would fall back to the default site and mail links pointing at
another brand; an alias is stored as the main host it belongs to. The slug is the
identity forms, contacts and campaigns all quote, so it is set once and a rename
belongs to the admin, where the templates quoting it can be fixed in the same
sitting. Deleting an audience that still holds contacts is refused: the cascade
would drop their consent records without ever naming them.

## Posting the form yourself

`newsletter_form()` gives you one, but the endpoint behind it is public and takes
an ordinary form post, so a front end of your own — a React island, a static
site's own markup — can subscribe on its own:

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
| `_token` | required unless `newsletter_csrf_protection` is off — read it out of `GET /newsletter/form?audiences=<slug>`. It is self-contained, so there is no cookie to carry back and it works from any origin |

The response is an HTML fragment, not JSON: the built-in form replaces itself
with it, and it is `alert.html.twig` you override to restyle it. Read the status
rather than the body — `200` subscribed (or awaiting confirmation), `400` a
missing audience or a malformed address, `403` a missing or stale token, `404` a
slug that matches nothing, `429` the rate limit.

Two behaviours to expect while testing:

- **Ten subscriptions per IP per hour.** The endpoint is public, cross-origin and
  sends a mail on success — without a ceiling it is a way to deliver confirmation
  mails to an address of someone else's choosing.
- **A filled honeypot gets the success page**, and no contact. A prober must not
  be able to tell a rejected submission from an accepted one — which does mean a
  form whose hidden field your own JS populates will silently subscribe nobody.

Posting from another origin needs that origin allow-listed, below.

## Configuration

```yaml
newsletter:
  send_batch: 50
  bounce_maildir: /home/user/mail/example.com/bounce
  # or, when that mailbox only exists on a remote server:
  # bounce_imap_dsn: '%env(NEWSLETTER_BOUNCE_IMAP_DSN)%'
  newsletter_possible_origins: 'https://example.com https://www.example.com'
```

`bounce_maildir` is where `pw:newsletter:bounces` reads delivery failures from,
the mailbox `framework.mailer.envelope.sender` points at. Null by default, which
leaves the command with nothing to read. `bounce_imap_dsn` reads the same mailbox
over IMAP when it is not on this machine (see [Bounces](#bounces)); set one or
the other, never both.

`newsletter_possible_origins` is the CORS allow-list for the subscribe endpoint —
a statically generated site posts to the origin where PHP runs. It falls back to
the conversation setting, so a site that already declared where its forms are
posted from does not have to declare it twice.

Templates are overridable per site through the usual view resolution, under
`/newsletter/`: `form.html.twig`, `email.html.twig`,
`confirm.email.html.twig`, `layout.html.twig`, `confirmed.html.twig`,
`unsubscribe.html.twig`, `unsubscribed.html.twig`, `resubscribed.html.twig`,
`unknown.html.twig`, `alert.html.twig`.

`confirmed.html.twig` is the override to make first. The reader landing on
`/newsletter/confirm/{token}` just clicked a mail from you — nobody on the site
is more engaged, and the default page spends that attention on one sentence.
Override it with a next step: a `pages_list()` of what to read first, a call to
action, what the site sells.

## What it deliberately does not do

- **No open tracking.** The spy pixel is refused by design: an image fetch says
  "the mail was rendered", not "somebody read it", and there is no way to offer
  it that a reader can meaningfully refuse. Click tracking exists
  ([above](#click-tracking)) but never by default and never wholesale — off per
  audience, and recording nobody whose own dated consent is not written on
  their row. The feedback every campaign records regardless is deliveries,
  failures, unsubscribes and bounces; the `utm_*` parameters stay the
  no-consent path, read by your site's own analytics in aggregate with nothing
  written against a contact.
- **No provider webhook ingestion.** A bounce is recorded when the transport
  refuses the mail at send time, when `pw:newsletter:bounces` reads it out of the
  Return-Path mailbox (see [Bounces](#bounces)), or when something posts it to
  `/api/newsletter/contact/{id}/bounce`. A provider's own feedback channel
  (SES→SNS, a Mailgun or Postmark webhook) still needs an adapter that does not
  exist yet. Complaints (FBL) are not ingested by any route.
- **No SMS sending.** A phone number is a first-class field and a contact may be
  keyed on it alone — stored, tagged, segmented, exported over the API. Nothing
  in the bundle sends to it; a phone-only contact is never a campaign recipient.
- **No `OR` between the two sides of a broadcast.** `triggerWhen` selects
  subjects, `recipientWhen` selects contacts, and a broadcast is their product:
  each matching page becomes a campaign, sent to the matching contacts. `AND`
  between them is implicit; an `OR` would be a condition on a (page, contact)
  *pair*, which is a different model — no amount of nesting inside either side
  reaches it.

  The intent behind wanting it is real: *this content to that audience, that
  content to this one, in one automation.* Today the answer is two automations,
  and it double-mails: `TriggerLog` is unique on (automation, subject), so two
  automations keep independent records and a contact in both segments gets two
  mails about the same article. Fixing it properly means either a record keyed on
  (subject, contact) or an automation holding a list of
  (triggerWhen, recipientWhen) pairs with the recipients deduplicated. Neither is
  built.
- **No `stopWhen` on a broadcast.** It would have to mean "drop this contact from
  the remaining steps", which is what `recipientWhen` already does at each arming
  — an inverted duplicate of a rule that is read at the right moment anyway.
