# Plan — `pushword/newsletter`

A self-contained mailing package: collect contacts, hold consent, broadcast to a
segment, drip on criteria. Usable by any Pushword site (altimood, piedweb,
piedvert) without a CRM.

## Scope

1. Subscription form — `audience`, name, email, optional `interests[]`.
2. Contacts API — CRUD + `customProperties` (`lastBoughtProduct`, …).
3. Automations — enroll on internal criteria (`createdAt`, tags, custom
   properties), send an ordered series of mails.
4. Campaigns — immediate or scheduled, to the whole audience or a segment.
5. Campaigns API.
6. Admin interface modelled on GA's `travel-booking-bundle`.
7. Unsubscribe + double opt-in, links built from `base_live_url` so they work on
   statically generated sites.

**Out of scope, deliberately**

- **Click tracking** — dropped 2026-07-27. Per-link personal-data logging is a
  liability in the EU for the value it returns here. No `Click` entity, no link
  rewriting, no HMAC redirect. (GA's version stays GA's.)
- Open tracking pixels — same reason, and never asked for.
- SMS / phone channel.
- Provider feedback webhooks — see *Known limits*.
- Migrating altimood's CSV / AssistantMail / `PiedWeb\Mailer`. Those adapt to
  this package once it exists; they do not shape it.

## Decisions

| Subject | Decision | Why |
|---|---|---|
| Audience | **Entity with admin CRUD** | Holds sender identity, DOI flag and the public interest vocabulary. One row per brand: altimood = 1 across its 17 locale hosts, multi-piedweb = ~10. |
| Consent scope | **The audience** | A subscribe on `danslesalpes.com` says nothing about `courirdeplaisir.com` — different audiences. `optinHost` is recorded as provenance, not as a scope. |
| Contact identity | `UNIQUE(audience, email)` | One person, one row per brand. |
| Subscription form | **Own route in the package** | Fixed field set; no `Message` row to convert. Reuses `conversation_possible_origins` for CORS. |
| Double opt-in | **Per-audience flag**, default on | Web signups confirm; API imports of an already-consenting base do not. |
| Segments | **One criteria language**, three uses | `Campaign.segment`, `Automation.enrollWhen`, `Automation.stopWhen` compile to the same query builder. |
| Body | `bodyMarkdown` column | Plain textarea, upgraded to the EditorJS widget when `pushword/admin-block-editor` is installed — an optional dependency, not a hard one. |
| Clock | **`pw:newsletter:tick` on system cron** | No target site runs a Messenger worker (`sync://` everywhere); every host has cron. |
| Transport | Per-site `MAILER_DSN` | Each site owns its provider and its reputation. |
| Tags vs interests | One list on `Contact`; `Audience.interests` is the subset the **public form** may write | The endpoint is public and cross-origin — free-form tags would let a bot write your segmentation. Unknown values are dropped silently. |

## Data model

Namespace `Pushword\Newsletter`, tables prefixed `newsletter_`.

```
Audience
  slug, name
  mainHost                 → SiteConfig, gives base_live_url for public links
  fromName, fromEmail, replyTo
  requireDoubleOptIn       bool, default true
  interests[]              json — vocabulary the public form may write
  rateSeconds              default cadence for this audience's sends

Contact                                      "registered at" = createdAt
  audience                 ManyToOne
  email                    lowercased, trimmed
  name, locale
  status                   pending | subscribed | unsubscribed | bounced
  token                    32 random bytes hex, unique — confirm + unsubscribe
  tags[]                   TagsTrait               (interests ⊆ tags)
  customProperties         ExtensiblePropertiesTrait
  source, optinHost, optinIp
  createdAt, confirmedAt, unsubscribedAt, bouncedAt
  UNIQUE(audience, email) · INDEX(audience, status)

Campaign
  audience, subject, preheader, bodyMarkdown
  segment[]                criteria — empty = the whole subscribed audience
  status                   draft | scheduled | sending | sent
  scheduledAt, sentAt, rateSeconds
  recipientCount, sentCount, failedCount, unsubCount, bounceCount

CampaignRecipient          idempotency ledger: a Sent row is never re-sent
  campaign, contact, state (pending|sent|failed|bounced), sentAt, error
  UNIQUE(campaign, contact)

Automation
  audience, name, enabled
  enrollWhen[]             criteria — empty = every subscribed contact
  stopWhen[]               criteria, optional — checked before each step
  enrollFrom               datetime, defaults to creation
  steps[]                  ordered

AutomationStep
  automation, position, delayMinutes, subject, bodyMarkdown

Enrollment
  contact, automation, position, nextRunAt, status (active|done|stopped)
  UNIQUE(contact, automation)
```

`enrollFrom` is the guard that stops a new automation with an empty `enrollWhen`
from enrolling an entire existing base on its first tick. It is a column, not a
criterion, because it must not be possible to forget.

## Criteria

A flat list of conditions, ANDed. No nesting, no OR — a second automation is
cheaper than an expression tree.

```json
[
  {"field": "tag",                    "op": "has",       "value": "AmTrek"},
  {"field": "createdAt",              "op": "olderThan", "value": "7d"},
  {"field": "prop.lastBoughtProduct", "op": "=",         "value": "tmb"}
]
```

| field | ops |
|---|---|
| `tag` | `has`, `hasNot` |
| `createdAt`, `confirmedAt` | `olderThan`, `newerThan` (duration string) |
| `prop.<key>` | `=`, `!=`, `isSet`, `isNotSet` |
| `locale` | `=`, `!=` |

Every compiled query is implicitly scoped to `audience = … AND status =
subscribed`: an unsubscribed or bounced contact cannot be targeted by any
criteria, ever.

`prop.*` needs a `JSON_EXTRACT` DQL function (SQLite ships JSON1) — register one
rather than post-filtering in PHP, or segment counts stop being a single query.

## Public routes

Generated from the audience's `base_live_url`, never from the static host.

| Route | Purpose |
|---|---|
| `POST /newsletter/subscribe` | form endpoint — honeypot, CORS from `conversation_possible_origins`, rate-limited per IP |
| `GET /newsletter/confirm/{token}` | double opt-in |
| `GET /newsletter/unsubscribe/{token}` | one-click page |
| `POST /newsletter/unsubscribe/{token}` | RFC 8058 one-click |

Every outgoing mail carries `List-Unsubscribe: <mailto:…>, <https://…>` and
`List-Unsubscribe-Post: List-Unsubscribe=One-Click`.

Twig helper for the form, mirroring `conversation()`:

```twig
{{ newsletter_form('altimood', ['AmTrek']) }}
```

## API

Two controllers implementing `Pushword\Api\Controller\ApiControllerInterface` —
auto-tagged, routes loaded by `ApiControllerRouteLoader`, OpenAPI fragment
merged into `/api/docs`. Absent `pushword/api`, none of it is declared.

```
GET    /api/newsletter/contacts?audience=&segment=&page=
POST   /api/newsletter/contacts          upsert on (audience, email)
PATCH  /api/newsletter/contacts/{id}     customProperties merge-patch, never replace
DELETE /api/newsletter/contacts/{id}
POST   /api/newsletter/contacts/{id}/unsubscribe

GET    /api/newsletter/campaigns
POST   /api/newsletter/campaigns
PATCH  /api/newsletter/campaigns/{id}
POST   /api/newsletter/campaigns/{id}/{schedule|send|test}
```

`POST /contacts` with `requireDoubleOptIn` honoured by default and an explicit
`status: subscribed` override for importing an already-consenting base.

## Admin (EasyAdmin)

Modelled on `NewsletterCampaignCrudController` — same fieldsets, same action
set, minus everything click-related:

- **Campaign** — *Content* (subject, status badge, preheader, body) · *Audience
  & scheduling* (audience, segment, `scheduledAt`, cadence) · *Performance*
  (recipients, sent, failed, unsub, bounce). Actions: **Send**, **Schedule**,
  **Cancel schedule**, **Send test** (arbitrary addresses, touches no counters).
- **Contact** — index with status badge, tags, `createdAt`; filters on audience,
  status, tag; search on email and name.
- **Automation** — steps inline as a collection, enabled toggle.
- **Audience** — plain CRUD.

The one addition worth the effort: **a live count beside every criteria editor**
(campaign segment, automation enroll/stop). A segment you cannot count is a
segment you will not trust.

## Sending

`pw:newsletter:tick`, once a minute from system cron, under a `symfony/lock`
so overlapping runs are impossible. Four idempotent passes:

1. arm scheduled campaigns whose time has come → materialise `CampaignRecipient` rows
2. drain pending recipients, honouring `rateSeconds`
3. advance enrollments where `nextRunAt` is due, re-checking `stopWhen` first
4. enroll contacts newly matching an enabled automation's `enrollWhen`

Plus `pw:newsletter:send <campaign>` (arm now, the tick drains) and
`pw:newsletter:import` for CSV. Both carry `AgentOutputTrait` + `--format`.

A send failure marks the recipient `failed` with its reason and never retries
blindly; a permanent failure marks the **contact** `bounced`, which removes them
from every future segment.

## Lots

Each is independently useful and shippable.

**Lot 1 — hold contacts.** `Audience`, `Contact`, subscribe route, DOI,
unsubscribe (page + one-click), admin CRUD, contacts API. A site can collect and
prove consent.

**Lot 2 — broadcast.** Criteria engine, `Campaign`, `CampaignRecipient`, the
tick's send passes, `List-Unsubscribe`, campaign admin actions, campaigns API.

**Lot 3 — automate.** `Automation`, `AutomationStep`, `Enrollment`, the tick's
enrollment passes, stop conditions. Delivers "2 mails after subscription".

## Known limits

- **Bounces are only seen at send time.** Without a provider feedback adapter
  (SES→SNS, Brevo/Postmark→webhook, own MTA→Return-Path), an asynchronous bounce
  never reaches us. Build one adapter when a provider is actually chosen — it is
  a separate concern from the transport DSN, and nothing speculative before then.
- **`rateSeconds` defaults are guesses** until n0c's hourly cap is confirmed.
- **From-alignment** (SPF/DKIM per audience domain) is a deploy prerequisite, not
  package code. multi-piedweb's ~10 brands each need it before their first send.
