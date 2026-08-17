---
title: 'Declared page properties'
publishedAt: '2026-08-01 10:00'
toc: true
---

# Declared page properties

Extra page data has three homes. Pick by what the data needs:

|                    | storage                 | validated | `prop.` query    | sortable     | flat round-trip | setup               |
| ------------------ | ----------------------- | --------- | ---------------- | ------------ | --------------- | ------------------- |
| **1. free-form**   | `customProperties` JSON | no        | `=` `!=` `isSet` | no           | free            | none                |
| **2. declared**    | `customProperties` JSON | yes       | typed operators  | yes          | free            | ~10 lines of config |
| **3. own entity**  | own table, own columns  | its own   | no               | yes, indexed | hand-written    | a class + a CRUD    |

Tier 2's edge over tier 3: **customProperties round-trips through the markdown
frontmatter for free**. A satellite entity is invisible to the flat exporter,
to `pw:flat:sync` and to the API's frontmatter shape.

## Declaring

Two levels — fleet-wide at the root, per-site under `apps`. A site *adds*
properties; redeclaring one **replaces the whole descriptor** (never a deep
merge); `name: ~` un-declares a property for that site:

```yaml
pushword:
  page_properties:
    author: { type: string }

  apps:
    - hosts: [example.com]
      page_properties:
        level: { type: string, constraints: [{ Choice: { choices: [Débutant, Initié] } }] }
        price_from: { type: int, constraints: [{ Positive: ~ }] }
        author: { type: string, constraints: [{ Length: { max: 60 } }] }
        readingTime: ~
```

- `type`: `string` (default) | `int` | `float` | `bool` | `date` | `list`.
  The type drives validation, the query operators and the admin widget.
- `constraints`: Symfony's validation-YAML shape — a constraint name (short
  name from `Symfony\Component\Validator\Constraints`, or a FQCN) mapping to
  its named constructor options. Nested constraint definitions (`All`,
  `AtLeastOneOf`…) are not resolved.
- `required`: reported by `pw:schema:dump` and the flat import summary —
  **never enforced on save**, so declaring it on a fleet with legacy gaps
  cannot block unrelated edits.

Every schema is built at container compile: a typo'd type, option, constraint
name or descriptor key fails the build instead of a request.

A bundle declares the properties it reads by implementing
`Pushword\Core\PropertySchema\PagePropertiesProviderInterface` (autoconfigured
tag `pushword.page_properties_provider`). Core declares `toc`, `tocTitle` and
`searchExcerpt`. Site config overrides bundle declarations.

Property names are storage keys, verbatim: a frontmatter `price_from` is
stored, declared and queried as `price_from`, never `priceFrom`.

## Reading the schema

`bin/console pw:schema:dump [--host=…]` prints, per host, the declared
properties, the keys managed by dedicated admin fields, and the core
frontmatter columns — agent-optimized JSON when run by an AI agent (see
[agent-output](/agent-output)). The same data ships in `/api/docs` as
`x-pushword-page-properties`.

## Validation

Declared properties are validated on the entity (admin form and API alike —
the API returns 422). Undeclared keys pass untouched. Violations name the
property and land on the extra-properties textarea.

The flat import **reports and never blocks**: `pw:flat:sync`'s summary gains
`invalid` (per-slug violation messages), `undeclared` (keys not in the schema
— the net that catches a `toc_title` typo'd for `tocTitle`) and
`missing_required`. The exit code never changes.

API writes (POST/PUT/PATCH) carry the same findings: a successful write
response gains a `warnings` object (`undeclared`, `missingRequired`) — only
when there is something to say. Informational; the write went through.

## Queries and sorting

Any custom property supports `=`, `!=`, `isSet`, `isNotSet` in `pages_list`.
A property declared `int`, `float` or `date` also compares — `< > <= >=` —
and both compare and sort **numerically on every platform** (SQLite's
`json_extract` is typed; PostgreSQL and MySQL/MariaDB get an explicit cast):

```twig
{{ pages_list([['prop.price_from', '>', 100]], 'prop.price_from ASC') }}
```

Pages missing the property sort last. An unquoted frontmatter date is stored
as a Unix timestamp (that is what YAML parses it to), so `date` properties
compare as timestamps.

## Admin fields

Each declared property renders as its own input on the page form — the type
picks the widget, a `Choice` constraint becomes a dropdown — and disappears
from the free-form textarea. Labels are the humanized property name
(`price_from` → "Price from"), translatable as natural message ids.

Emptying a field removes the key; unchecking a `bool` removes it too (a
stored `false` would still satisfy `null != page.toc` template tests). A form
opened before a declaration deployed still saves: the value typed in its
textarea writes through instead of erroring.

## Tier 3, when you need real columns

Works today, no core change — indexed columns, `ORDER BY`, own repository and
CRUD; invisible to the flat export/import and the API frontmatter:

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

- `onDelete: CASCADE` is not optional. Admin deletes **and `pw:flat:sync`**
  remove pages — the sync deletes every page whose `.md` file disappeared —
  and either would hit the foreign key without the cascade.
- **The satellite row is never created for you.** No Pushword path inserts
  one, so every page starts without it: query with `LEFT JOIN` — an
  `INNER JOIN` silently drops every page that has no row. Do *not* auto-create
  rows from a `postPersist` listener; it defeats the sparse table and puts a
  write in every bulk import.
- Owning side only: read through the repository, not a `page.seo` accessor.
