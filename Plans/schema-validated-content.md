# Schema-validated content

Opt-in, partial, Symfony-native declaration of what a page's custom properties may hold.

> **Status: shipped (2026-08-01).** All six phases implemented; docs in
> `packages/docs/content/page-properties.md`. Execution amendments to this plan:
>
> - `AppsConfigParser` is dead code (referenced nowhere) — the merge and the new
>   compile-time schema validation live in `PushwordConfigFactory::processAppConfig()`.
> - Compile-time validation is not free: `page_properties` sits in a variableNode, so the
>   factory eagerly builds every schema at container compile (typo'd type/option/
>   constraint/descriptor key → failed build). Constraint instantiation from the YAML
>   shape is net-new: name → FQCN resolved in `Symfony\Component\Validator\Constraints`,
>   options as named constructor arguments.
> - `required` is **reported only** (schema dump, flat summary `missing_required`) —
>   never enforced on the entity, so a fleet with legacy gaps keeps saving.
> - Phase 3's class constraint runs *before* the textarea-merge callback, so the
>   validator reads `getEffectiveCustomProperties()` — the pending YAML applied without
>   mutation.
> - Phase 5's data evidence was wrong: values are stored as JSON numbers and SQLite's
>   `json_extract` is typed, so the lexical hazard is MySQL/MariaDB-only
>   (`JSON_UNQUOTE`). The declared type licenses a `JSON_NUMBER` cast there; keys are
>   charset-guarded before DQL interpolation, directions whitelisted, NULLs sort last.
> - The phase-6 "one release" migration window is actually **permanent**: the stale-form
>   hazard reopens every time any site declares a new property. Schema keys registered by
>   generated fields write through the merge instead of throwing, forever.
> - Absent ≠ empty needed a client-side signal: the server rebuilds every form child on
>   submit, so a hidden marker lists the keys the client's form actually rendered — it
>   also distinguishes a stale form from an unchecked checkbox.
> - The largest slice (package-owned properties) got a declaration source:
>   `PagePropertiesProviderInterface` (tagged), core declaring `toc`, `tocTitle`,
>   `searchExcerpt`; the dump unions managed keys and the api's frontmatter columns.

## Three tiers, and why tier 2 exists

Extra content data already has three homes. The feature's first job is to make the choice
between them explicit; only tier 2 needs building.

|                             | storage                 | validated     | `prop.` query    | sortable     | flat round-trip | setup               |
| --------------------------- | ----------------------- | ------------- | ---------------- | ------------ | --------------- | ------------------- |
| **1. free-form**            | `customProperties` JSON | no            | `=` `!=` `isSet` | no           | free            | none                |
| **2. declared** (this plan) | `customProperties` JSON | yes           | typed operators  | yes          | free            | ~10 lines of config |
| **3. own entity**           | own table, own columns  | yes (its own) | no               | yes, indexed | hand-written    | a class + a CRUD    |

Tier 3 needs no core change and is documented in `packages/docs/content/` (see the last
section). Tier 2 earns its keep on the one column tier 3 cannot fill: **customProperties
round-trips through the markdown frontmatter for free**. A satellite entity is invisible to
`PageExporter`, to `pw:flat:sync`, and to the API's frontmatter shape.

## Grounding: what the fleet actually stores

Inventory of every frontmatter key across 1,527 pages in `multi-piedweb.com`, `altimood` and
`piedvert.com` (~20 hosts), minus the core columns and `mainImageFormat`:

| property | uses | owner |
| --- | --- | --- |
| `searchExcerpt` | 586 | package — `Page.php:446`, has a field (`PageSearchExcreptField`) |
| `tocTitle` | 486 | package — `SplitContent.php:53`, no field |
| `published_by` | 446 | brand — altimood only (62 + 24 × 16 locale hosts); `Robin` (429), `Alice` (17) |
| `author_bio` | 102 | brand |
| `subtitle` | 56 | brand |
| `h1_class` | 48 | brand — raw Tailwind, genuinely free-form |
| `main_image_panorama` | 18 | brand |
| `toc` | 12 | package — `SplitContent.php:53` |
| `product`, `price_from`, `level`, `duration`, `badge_text`, `description`, `train_station`, `night_accomodation`, `hero_overlay` | 6–11 each | site — alpescheval.com |
| `publishedBy` | 9 | site — courirdeplaisir.com, a *different* property (see below) |
| `priority` | 5 | brand |
| `phoneNumber` | 3 | brand |

Three owners, and they map onto the declaration levels — with one project per level, which is
why both levels earn their place:

- **package-owned** (~1,084 uses) — declared by the bundle, not the site. The tagged-service
  source from the original sketch, and the largest slice by far.
- **brand-owned** — root-level `page_properties`. `altimood` is the case: 17 locale hosts of a
  single brand, where every host wants the same declarations.
- **site-owned** — per-app `page_properties`. `multi-piedweb.com` is the case: 10 hosts of
  unrelated brands, where alpescheval's product cluster must not leak onto the others.

### What the inventory actually found

Running it turned up one confirmed defect, not the four first claimed. The three that were
withdrawn are recorded because *why* they looked like defects is itself the argument.

**Confirmed — `toc_title` on 2 piedvert pages.** `equipement-bivouac.md` and its EN pair
`bivouac-gear.md` set both `toc: true` and `toc_title: Navigation`, but
`piedvert.com/page/_content.html.twig:16` reads `page.tocTitle|default('Nav')`, and
`normalizePropertyName` folds nothing but `parent` → `parentPage`
(`PageImporter.php:337-344`) — there is no snake→camel normalisation on the storage key. Both
pages render the heading **"Nav"** instead of "Navigation". Cosmetic, silent, long-standing.

**Withdrawn — `publishedBy` vs `published_by` are two different properties**, not one drifting
name. `published_by` is a string author name read by `altimood/templates/published_by.html.twig`;
`publishedBy` is a list of emails resolved against `templates/<host>/authors.json` by
`multi-piedweb.com/src/Twig/AppRuntime.php:36-49`. Cleanly separated by host — courirdeplaisir
has zero files using the string form. Working as intended.

**Withdrawn — `night_accomodation`** is misspelled but internally consistent:
`alpescheval.com/component/card.html.twig:47-50` reads the same spelling.

**Withdrawn — the "abandoned one-offs" are all live.** `mainImageXl` and `main_image_multiple`
are read at `altimood/templates/page/_content_hero.html.twig:43-46`, `uniqueGalleryId` in two
gallery templates. Only `translation:` (empty value, 17 altimood files) is genuinely inert —
and it never reaches the database at all, since the converter skips nulls
(`PageImporter.php:297-300`). File noise, not data.

**The better evidence** is what made three of those look wrong in the first place —
`altimood/templates/page/_content_hero.html.twig:43`:

```twig
{% set main_image_panorama = page.main_image_panorama ?? page.mainImagePanorama ?? page.mainImageXl %}
```

A three-way spelling fallback for one concept, written defensively because nothing constrains
the name. Only `main_image_panorama` (18) and `mainImageXl` (1) occur in the data;
`mainImagePanorama` never does — that branch has never been true. This is the absence of a
schema showing up as dead branches in template code, and it is a more honest case for the
feature than a defect count.

### The schema already exists — as prose

`content/alpescheval.com/CLAUDE.md:33-44` is a hand-written YAML schema for AI authors, with
`content/CLAUDE.md` carrying the shared keys. It has already drifted from its own data: the
example reads `level: 'Initie'` while all five real pages use `Initié`. This is what phase 2
replaces with generated output — the reason to move `pw:schema:dump` early is that the fleet
is *already paying* to maintain it by hand.

### Type evidence for phase 5

- `price_from`: 30, 55, 350, 380, 390, 560, 675, 750. Sorting a product list by price is the
  obvious query, and as unCAST JSON strings the order is 30, 350, 380, 390, 55, 560, 675, 750.
  The hazard in phase 5 is not hypothetical, it is this data.
- `duration`: `'1 jour'`, `'2 jours'`, `'6 jours'` — a number wearing a string, unsortable.
  A schema pushes this toward `durationDays: int` before the listing needs it.
- `level`: `Initié`, `Débutant`, `Galop 3` → `Choice`. `priority`: 1, 2 → `int`.
  `product: 1` → a flag.

### What that config looks like

Two projects, two shapes. `altimood` — one brand, 17 locale hosts, everything at the root:

```yaml
pushword:
  page_properties:
    published_by: { type: string, required: true, constraints: [{ Choice: { choices: [Robin, Alice] } }] }
    author_bio: { type: string }
    subtitle: { type: string }
    h1_class: { type: string }
    main_image_panorama: { type: string }
```

`multi-piedweb.com` — 10 unrelated brands, so almost nothing is fleet-wide and the two author
models stay scoped to their own hosts rather than colliding under one name:

```yaml
pushword:
  page_properties:
    h1_class: { type: string }

  apps:
    - hosts: [www.courirdeplaisir.com]
      page_properties:
        publishedBy: { type: list } # emails, resolved against templates/<host>/authors.json

    - hosts: [alpescheval.com, live.alpescheval.com]
      page_properties:
        product: { type: bool }
        price_from: { type: int, constraints: [{ Positive: ~ }] }
        level: { type: string, constraints: [{ Choice: { choices: [Débutant, Initié, Galop 3] } }] }
        duration: { type: string }
        night_accomodation: { type: string }
        train_station: { type: string }
        badge_text: { type: string }
        hero_overlay: { type: string }
```

## Corrections to the original sketch

Three claims did not survive reading the code.

**`registerManagedPropertyKey()` does not produce a form field.** It only filters the key out
of the textarea (`ExtensiblePropertiesTrait.php:45-49`) and makes the merge throw if the
textarea still carries it (`:141-147`). Causality runs the other way: the field registers
itself, and disappearance from the textarea is the consequence. Register a schema key without
emitting a field and the key becomes invisible — not in the textarea, not in any form.

**The registry already carries two meanings.** `PageFrontmatterMapper` calls it on every
custom property (`:194, :205, :231, :262`) purely to shield them from the destructive merge —
"don't wipe this", not "a field owns this". It must be split before the schema feeds it.

**Flat has no validator.** No `ValidatorInterface`, no `->validate()` anywhere in
`packages/flat/src`; the importer writes straight through `setCustomProperty()`
(`PageImporter.php:299`). Phase 4 introduces validation there, it does not extend it.

## Build order

Phases 1–2 ship the agent-facing value with no runtime behaviour change. 5 is the real
payoff. 6 is the largest and last.

### 1 — Declare

Declarable at two levels — fleet-wide at the root, per-site under `apps`:

```yaml
pushword:
  # every site
  page_properties:
    author: { type: string, required: true }

  apps:
    - hosts: [example.com]
      # this site only: adds cta, tightens author, drops readingTime
      page_properties:
        cta: { type: string, constraints: [{ Choice: { choices: [newsletter, contact] } }] }
        author: { type: string, required: true, constraints: [{ Length: { max: 60 } }] }
        readingTime: ~
```

- Constraint syntax is Symfony's own validation-YAML shape, verbatim. Nothing new to learn or
  document, and `Length(max: 60)` is expressible — `constraints: [Positive]` is not.
- No `type: enum` / `values`: that is `Choice`. One concept each — `type` drives storage,
  query typing and widget kind (`string|int|float|bool|date|list`), constraints drive
  validation only.
- Deliverable: `PagePropertySchema` value object + a registry service resolving per-app
  through `apps->get()`. Nothing changes at runtime. Tests on the parser.

#### Scope resolution

The two levels need no new machinery — `app_fallback_properties`
(`Configuration.php:31-47`) already lists which root keys fall into each app, and
`custom_properties` is the precedent for *merging* rather than *replacing*
(`AppsConfigParser.php:39-45`). Three changes:

1. add `page_properties` to `DEFAULT_APP_FALLBACK`;
2. a root `variableNode('page_properties')->defaultValue([])`;
3. extend the `elseif ('custom_properties' === $property)` branch to merge `page_properties`
   the same way.

Semantics: `array_merge(global, app)`, shallow, keyed by property name. A site **adds**
properties, and redeclaring one **replaces its whole descriptor** — not a deep merge of
`constraints`. Tightening a rule means restating the property. Predictable beats clever here.

A null value (`readingTime: ~`) un-declares a globally declared property for that site.
Needed the moment one shared schema covers a fleet with one odd site; without it the only
escape is to stop declaring globally.

Two notes:

- Per-site already half-works today. `SiteConfig` swallows every unrecognised app key into its
  property map (`SiteConfig.php:64-75`), so `apps[].page_properties` is readable right now via
  `getArray('page_properties')`. What phase 1 actually buys is the **root level** and
  **compile-time validation** — `apps` is a `variableNode` (`Configuration.php:232`), so a
  typo'd constraint name currently surfaces as a per-request crash instead of a failed
  container build.
- `app_fallback_properties` is itself overridable. A project that replaced the whole list
  loses the inheritance; that is already true of every key in it.

### 2 — Expose: `pw:schema:dump`

Cheapest real value, and it works before any validation exists.

- `AgentOutputTrait` + `--format` (auto|agent|text), human writes gated behind `$this->agentMode`.
- Emits, per host: declared properties, and the core columns the frontmatter already accepts.
  Reuse `PageFrontmatterMapper::RESERVED_FRONTMATTER_KEYS` as the source for the latter — do
  not duplicate the list.
- Add the command to `packages/docs/content/agent-output.md`.
- OpenAPI addition follows in the same phase.

An agent authoring a page stops inferring valid properties from examples.

### 3 — Validate

The trait's `#[Assert\Callback]` is entity-local and cannot reach app config, so this is a
class constraint on `Page` with a validator service injecting the registry — not an extension
of `validateUnmanagedProperties()`.

- Undeclared keys pass through untouched. Existing sites are unaffected.
- Violations attach at `buildValidationAtPath` so the admin textarea surfaces them and the API
  returns 422.
- Tests: admin form, API 422, and undeclared-key passthrough.

### 4 — Flat import reports

- Inject `ValidatorInterface` into `PageImporter`, validate after the page is built.
- **Policy: import and report, do not block.** One bad frontmatter must not fail a deploy.
  Exit code unchanged; the agent-output JSON gains `invalid: [...]`.

### 5 — Typed queries and sorting

The payoff the original sketch missed, and the reason a declared _type_ is worth having.

- `PageFieldRegistry::strategy()` returns a bare `JsonPropertyStrategy` for any `prop.*`
  (`:44-47`), which supports only `= != isSet isNotSet`. Give the registry the schema so a
  declared `int`/`float`/`date` gets `< > <= >=`, as `ColumnStrategy` does for `publishedAt`.
- Sorting is impossible today: `PageRepository::orderBy()` builds `p.<key>` as a raw column
  path (`:742`). Teach it `prop.<key>` → `JSON_SCALAR(p.customProperties, '$.key')`.
- `JSON_SCALAR` unquotes on MySQL/MariaDB and yields a string, so ordering an int property
  without a `CAST` sorts lexically (10 before 9). The declared type is what tells the compiler
  to emit the cast. Verify on both platforms — `composer test` and `composer test-mariadb`.

### 6 — Admin fields

Largest phase. Two prerequisites, both real work.

**6a. Split the registry.** Introduce a distinct `preserveCustomProperty()` for the API's
shield semantic; keep `registerManagedPropertyKey()` for field-owned. Update the four
`PageFrontmatterMapper` call sites. Pure refactor, covered by `CustomPropertiesTraitTest`.

**6b. Generic field + writeback.** EasyAdmin cannot map a field to `customProperties[author]`,
so every generated field needs the unmapped+writeback dance. `mainImageFormat` is the only
precedent and it takes four pieces:

| piece                                     | file                                       |
| ----------------------------------------- | ------------------------------------------ |
| field, `mapped:false` + manual `data`     | `PageAdvancedMainImageFormField.php:33-38` |
| extract submitted value, write to the bag | `AdminFormEventSubscriber.php:83-89`       |
| flat round-trip                           | `MainImageFormatConverter`                 |
| the registration                          | `:31`                                      |

Generalize the first two: a `SchemaPropertyFormFieldProvider` yielding one field per declared
property (type → widget, a `Choice` constraint → dropdown, `required` → required), and one
submit subscriber writing every declared key back into `customProperties`.

**Absent ≠ empty — the writeback must not clobber.** A payload that omits a declared key must
leave the stored value alone; a payload that carries it empty must clear it. The precedent
reads the raw request and returns early on null (`AdminFormEventSubscriber.php:83-87`), which
collapses both cases into "don't write" — fine for `mainImageFormat`, wrong for a generic
string, since the user could never clear one. `extractSubmittedValue` cannot be reused as-is:
its `'' !== (string) $candidate` test (`:99`) is exactly the collapse. Use
`array_key_exists()` on `$request->request->all()` to separate them.

This is a permanent rule, not a migration workaround — any path rendering a subset of the
fields (role-gated panels, partial forms) would otherwise blank the omitted ones.

**Migration — accept the stale value, do not drop it.** An editor who has `author:` typed in
the textarea hits `page.customProperties.notStandAlone` (`:143`, `:160`) the moment `author`
is declared and registered. Walk the actual sequence for a form opened *before* the deploy and
submitted after:

1. entity loads with `customProperties['author'] = 'Robin'`;
2. the stale textarea submits `author: Robin`;
3. `mergeUnmanagedProperties()` — the removal loop skips managed keys (`:125-135`), so the
   value survives; the set loop (`:141-147`) is where it throws today;
4. `BeforeEntityUpdatedEvent` → the generic writeback runs, and `author` is **absent** from the
   payload because the field did not exist when the form was rendered.

Dropping the textarea copy at step 3 and writing null at step 4 loses the value. So: for one
release, a managed key found in the YAML is **written into the bag** like any unmanaged key —
neither dropped nor thrown. Combined with the absent-key rule above it composes correctly:

| case | YAML carries it | payload carries it | result |
| --- | --- | --- | --- |
| stale form | yes | no | YAML value wins — the editor's change is kept |
| fresh form | no (filtered out at `:45-49`) | yes | field value wins |
| fresh form, key retyped in the textarea | yes | yes | field value wins — it overwrites at step 4 |

After that release the set loop can go back to throwing. Test all three rows.

Labels follow the Twig i18n convention (camelCase keys, alphabetical,
`packages/<package>/translations/messages.<locale>.yaml`), falling back to the property name.

## Out of scope

**No `column: true` in the schema.** Generating DDL from a config file is an ORM inside YAML,
and it collides with "no migrations, `doctrine:schema:update --force`". Tier 3 stays
hand-written.

## Tier 3, for the docs

Works today, no core change:

```php
namespace App\Entity;

#[ORM\Entity]
#[ORM\Table(name: 'page_seo')]
class PageSeo
{
    #[ORM\Id, ORM\OneToOne(targetEntity: Page::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Page $page;

    #[ORM\Column(type: Types::INTEGER)]
    public int $readingTime = 0;
}
```

- `onDelete: CASCADE` is not optional — an admin page delete would otherwise hit the FK.
  ~~`pw:flat:sync` never removes pages~~ **Stale since the rc807 deletion work:**
  `PageSync::deleteMissingPages()` removes every page whose `.md` file disappeared, and
  `resetHostPages()` removes all of a host's pages on `--force` — the sync is very much a
  deletion path, which makes the cascade more load-bearing, not less.
- **The satellite row is never created for you.** No Pushword path — admin create, API, or
  `pw:flat:sync` — inserts one, and there is no cascade from `Page` because `Page` has no
  inverse side. Every page therefore starts without it, so **query with `LEFT JOIN`**: an
  `INNER JOIN page_seo` to sort by `readingTime` silently drops every page that has no row,
  which at first is all of them.
- Do *not* auto-create the row from a `postPersist` listener. It writes a row per page whether
  or not there is data — which is the point of a sparse satellite table — and puts a write in
  the path of every bulk flat import. `LEFT JOIN` plus a null-coalescing sort is the answer.
- Owning side only. `page.seo` in Twig would require editing `Page`; read through the
  repository instead.
- Gains: indexed columns, `ORDER BY`, own repository, own EasyAdmin CRUD.
- Loses: invisible to `PageExporter`, to `prop.` queries in `pages_list`, and to the API
  frontmatter. Data that must survive a flat export/import cycle belongs in tier 2.

New doc page under `packages/docs/content/`; a moved or renamed page needs a
`redirection.csv` 301.
