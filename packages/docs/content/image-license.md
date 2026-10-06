---
title: 'Image license metadata (Google Licensable badge)'
h1: Image license metadata
publishedAt: '2026-07-26 12:00'
toc: true
---

Google Images shows a "Licensable" badge when the page declares who owns an image and
where a licence can be bought. Pushword emits that declaration as schema.org
`ImageObject` structured data, from properties stored on each media.

Reference: <https://developers.google.com/search/docs/appearance/structured-data/image-license-metadata>

{id=what-google-needs}
## What Google needs

An `ImageObject` needs `contentUrl` **plus at least one** of `creator`, `creditText`,
`copyrightNotice` or `license`. An `acquireLicensePage` alone does **not** make an image
eligible.

Structured data and IPTC metadata embedded in the file are two independent methods and
either one suffices; structured data wins when they disagree. Pushword implements
structured data only — it never rewrites your image files.

{id=configuration}
## Configuration

`media_default_license_seed`, per app, describes what the site claims about images it
owns:

```yaml
pushword:
  apps:
    - hosts: ['example.com']
      media_default_license_seed:
        license: 'https://example.com/legal'
        acquireLicensePage: 'https://example.com/contact'
        creditText: 'Example'
        creator:
          - name: 'Example'
            type: Organization        # or Person
        copyrightNotice: '© Example'
```

It is a **seed written at upload**, not a render-time fallback: each media owns concrete
values and rendering reads only the media. Changing the config does not touch existing
media — run [`pw:media:license`](#backfill) to propagate it.

{id=upload}
## What happens on upload

```
imported = read(XMP) ?? read(IPTC-IIM) ?? read(EXIF) ?? read(C2PA)   # per property, first non-empty

if imported has a generator marker:
    record it as digitalSourceType, drop it from creditText
    it does NOT count as a rights value below

if imported still has any rights value:
    import it as-is, seed nothing        → licenseState = thirdParty
else:
    write the configured seed            → licenseState = seeded
```

**A file that claims somebody's rights never receives the site's licensing**: the bytes
cannot tell "commissioned, we hold the rights" from "someone else's photo", so a human
asserts it with one button. The image still emits a valid `ImageObject` from its
imported attribution.

### Where the metadata is read from

Four sources, in that order of precedence, walked out of the container without decoding
any pixels:

| Source | Carried in | Holds |
| --- | --- | --- |
| XMP | JPEG `APP1`, PNG `iTXt`/`zTXt`/`tEXt`, WebP `XMP ` chunk | every property |
| IPTC-IIM | JPEG `APP13` | creator, credit, copyright |
| EXIF | JPEG `APP1` | `Artist`, `Copyright` |
| C2PA | JPEG `APP11`, PNG `caBX`, WebP `C2PA` chunk | `digitalSourceType` only |

In PNG, both `XML:com.adobe.xmp` and ImageMagick's hex-wrapped `Raw profile type xmp`
keywords are read, in any of the three text chunk types, deflated or not.

### AI-generated images

ChatGPT and Gemini stamp their output with `Iptc4xmpExt:DigitalSourceType` and a credit
line of exactly `AI Generated` or `Made with Google AI`. That provenance note moves to
`digitalSourceType`, leaves the credit line, and the image is seeded like any other the
site owns. The match is exact: an agency named "AI Generated Studio Ltd" keeps its credit
and stays third-party.

{id=c2pa}
### C2PA (Content Credentials)

A `gpt-image` PNG carries **no XMP, IPTC or EXIF** — only a C2PA manifest, as written by
OpenAI, Google, Adobe and camera makers. Its `c2pa.actions` assertion uses the IPTC
NewsCode vocabulary:

```
claim_generator_info.name = "OpenAI Media Service API"
softwareAgent             = gpt-image 2.0
digitalSourceType         = …/digitalsourcetype/trainedAlgorithmicMedia
```

Only `digitalSourceType` is read — never the signer, which is not a `creator`. The value
is read rather than the manifest's presence, since a camera writes `digitalCapture`
there. **The signature is not verified**: like XMP, it is what the file says about
itself, not proof, and it stays editable in the admin.

### Replacing the file on an existing media

A replacement **discards the previous values and re-runs the decision** — never a merge.
If the licence had been asserted by hand, a flash message says so. Rotating an image
resets nothing, and re-uploading byte-identical content is a no-op.

{id=properties}
## The properties

| property | source in the file |
| --- | --- |
| `license` | `xmpRights:WebStatement` |
| `acquireLicensePage` | `plus:LicensorURL` |
| `creditText` | `photoshop:Credit`, IIM `2#110` |
| `creator` (rows) | `dc:creator`, IIM `2#080`, EXIF `Artist` |
| `copyrightNotice` | `dc:rights`, IIM `2#116`, EXIF `Copyright` |
| `digitalSourceType` | `Iptc4xmpExt:DigitalSourceType` / `…FileType` |

They live in the media's `customProperties`, so they round-trip through the API and
`media.csv`.

`creator` is a list of `{name, type}`:

```yaml
creator:
  - name: 'Enrico Romanzi'
    type: Person
  - name: 'Altimood'
    type: Organization
```

Each creator emits its own schema.org node: several give an array, a single one a bare
object, as in Google's example. No file format carries a type, so imported creators
default to `Person`, editable per name. Where only one input exists — a config value, a
media-list row — the compact form `Enrico Romanzi (Person), Altimood (Organization)` is
accepted, and a bare list of names is read as people.

`digitalSourceType` is stored for editorial and compliance use but **never emitted**: no
`ImageObject` property carries it.

`licenseState` is a derived, read-only column: `` (none), `seeded`, `overridden` (a human
asserted it) or `thirdParty`. The media list filters and sorts on it.

{id=on-the-page}
## What the page renders

`component/image.html.twig` emits two things from the same properties: the `ImageObject`
script a crawler reads, and a credit a visitor can read, in the `<img title>`.

```html
<img src="…" alt="Refuge du Gioberney" title="© Zde / Wikimedia (CC BY-SA 4.0)">
```

The credit line is `copyrightNotice` verbatim, else `creditText`, else the `creator`
names joined by a comma, prefixed with `©` unless it already opens with one. The licence
is appended only for a Creative Commons deed, which requires it:

| `license` | appended to the line |
| --- | --- |
| `creativecommons.org/licenses/by-sa/4.0/` | `(CC BY-SA 4.0)` |
| `creativecommons.org/publicdomain/zero/1.0/` | `(CC0 1.0)` |
| `creativecommons.org/publicdomain/mark/1.0/` | `(Public Domain Mark 1.0)` |
| anything else — a stock platform's terms page | nothing |

A deed with nobody to attribute still renders alone (`CC0 1.0`).

The credit never goes in the `alt`, which describes what the photo shows. Screen readers
announce a `title` only on request, so an attribution that must be *visible* needs a
caption.

Also:

- a media declaring none of these properties gets neither a title nor an `ImageObject`;
- a caller passing its own title keeps it — `image(media, attr: {title: 'Le refuge au
  petit matin'})` — and the `ImageObject` still carries the credit, because the caller
  overrode the tooltip, not the media's claim;
- an SVG gets the title but no `ImageObject`: the node describes the files
  `Media::isImage()` accepts (jpg, png, gif, webp).

{id=admin}
## In the admin

The **License** block on the media form holds the six fields, collapsed; `creator` is a
row-per-creator collection with its own add and remove buttons. Two buttons sit under the
block: *Apply the site license* fills it from the seed — pressing it **is** the ownership
assertion — and *Clear* empties it so the media stops emitting.

After creating a media, a flash on the media list says what happened — the site's
license was added, or the file carries its own rights and whom it credits — and the row
shows a **License state** badge (*Site license*, *Third-party rights*, *Asserted by hand*;
none while undecided). Multi-upload shows the same disclosure per row instead of a flash.

Browser-side downscaling goes through a canvas, which drops metadata, so the browser
first lifts the XMP packet, APP13 block and C2PA manifest out unparsed and posts them
base64-encoded in an `embeddedMetadata` field (EXIF alone is read client-side, since
`exif_read_data()` needs a file). The server parses them with the same readers as an
intact upload. The stored file still wins property by property: the sidecar only fills
what the bytes leave empty, so a client cannot overrule it.

{id=backfill}
## Backfilling an existing library

Media that predate the feature never went through an upload hook:

```bash
bin/console pw:media:license --dry-run   # preview the decision
bin/console pw:media:license             # seed, import, and report the exceptions
bin/console pw:media:license --force     # also license what the files credit to others
bin/console pw:media:license --all       # re-decide media that already have a state
```

The command lists every file left to its own rights, to decide by hand. `--force` is the
bulk *Apply the site license* button; media whose licence was asserted by hand are never
rewritten.

{id=not-embedding}
## Why the files are not rewritten

Pushword does not write XMP back into images: Imagick would have to re-encode the file,
adding a lossy generation, and Google prefers structured data anyway.

Cache variants carry no rights metadata either (the webp encoder and `cjpeg` drop it), so
the `ImageObject` is the only licensing signal a crawler gets. Its `contentUrl` is
therefore built like the `<img src>` in `component/image.html.twig` — the `default`
filter in the *source* format, not the webp variant — on the site's `base_url`, never on
`base_live_url` (the PHP origin of a static site).
