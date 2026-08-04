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

On by default. The form endpoint issues a token, and the subscribe endpoint
answers `403` to a post that does not carry it. Nothing to wire: the placeholder
fetches the form, the form comes back with its token, js-helper posts it.

**Turn it off where the token cannot make the round trip**:

```yaml
pushword:
    apps:
        - hosts: ['example.com']
          newsletter_csrf_protection: false
```

The token lives in the session, so the session cookie has to reach the subscribe
endpoint. It does when the page and `base_live_url` are same-site — the same
domain, or two subdomains of it. It does not when a static build is served from
one domain and PHP runs on another: `SameSite=Lax` withholds the cookie, the
endpoint opens a fresh session per request, and **every subscription fails with
`403`**. Same story for a front end that posts the endpoint itself without
fetching a form first.

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
pick a list, give an address, and the audience's double opt-in rule decides the
rest — the confirmation mail goes out exactly as it would from the public form.
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
subject prefix, touching no contact and no counter.

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
every other mailbox, so there is no IMAP extension to compile, no webhook to
expose and no credentials to store. A mailbox that only exists on a remote IMAP
server is out of scope, the path has to be readable from the filesystem.

What the command does with what it reads:

- it parses the `message/delivery-status` part, never the human-readable one,
  which the remote server writes in its own language and layout,
- it acts on **permanent failures only** (`Status: 5.x.x`). A 4.x.x is a mailbox
  that was full an hour ago, and dropping a reader over one loses an address the
  next retry reaches,
- an address held on several audiences leaves all of them: the server refused
  the address, not one of the lists,
- a bounce for somebody on no list is counted and reported, never acted on. The
  same mailbox collects the failures of everything else the app sends,
- a message it has read is moved to `cur/` with the seen flag, which is what
  keeps the next run from reading it again. One that cannot be moved is only
  counted: re-reading it costs nothing, since marking an address that already
  bounced is a no-op.

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
GET    /api/newsletter/campaign/{id}    # includes estimatedRecipients while draft
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
| `_token` | required unless `newsletter_csrf_protection` is off — read it out of `GET /newsletter/form?audiences=<slug>`, sending that response's session cookie back with the post |

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
  newsletter_possible_origins: 'https://example.com https://www.example.com'
```

`bounce_maildir` is where `pw:newsletter:bounces` reads delivery failures from,
the mailbox `framework.mailer.envelope.sender` points at. Null by default, which
leaves the command with nothing to read.

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

- **No click or open tracking.** Per-link personal-data logging is a liability in
  the EU for what it returns on a content site. The feedback a campaign records
  is deliveries, failures, unsubscribes and bounces. The `utm_*` parameters above
  are a different thing: no redirect stands between the reader and the page, and
  nothing is written against a contact — your site's own analytics reads them,
  in aggregate.
- **No provider webhook ingestion.** A bounce is recorded when the transport
  refuses the mail at send time, when `pw:newsletter:bounces` reads it out of the
  Return-Path mailbox (see [Bounces](#bounces)), or when something posts it to
  `/api/newsletter/contact/{id}/bounce`. A provider's own feedback channel
  (SES→SNS, a Mailgun or Postmark webhook) still needs an adapter that does not
  exist yet. Complaints (FBL) are not ingested by any route.
- **No SMS.** Contacts are email-keyed; a phone number is a custom property.
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
