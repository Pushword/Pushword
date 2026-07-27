---
title: 'Image license metadata (Google Licensable badge)'
h1: Image license metadata
publishedAt: '2026-07-26 12:00'
toc: true
---

Google shows a "Licensable" badge next to an image in Google Images when the page
carrying it declares who owns it and where a licence can be bought. Pushword emits that
declaration as schema.org `ImageObject` structured data, straight from properties stored
on each media.

Reference: <https://developers.google.com/search/docs/appearance/structured-data/image-license-metadata>

{id=what-google-needs}
## What Google needs

An `ImageObject` needs `contentUrl` **plus at least one** of `creator`, `creditText`,
`copyrightNotice` or `license`. Note what is missing from that list: an
`acquireLicensePage` on its own does **not** make an image eligible.

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

It is a **seed written at upload**, not a fallback consulted when a page renders. Each
media ends up owning concrete values, and rendering reads the media and nothing else.

The trade-off is deliberate: changing the config does not propagate to media already in
the library. `pw:media:license` is what propagates it.

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

**A file that claims somebody's rights never receives the site's licensing.** Nothing in
the bytes distinguishes "commissioned, we hold the rights" from "someone else's photo",
so seeding an `acquireLicensePage` into the empty fields of a photo credited to a
photographer would advertise that the site licenses their work. A human asserts it
instead, with one button.

The image still emits a valid `ImageObject` from its imported attribution — it is not
excluded from Google, only from the site's licence.

### Where the metadata is read from

Four sources, in that order of precedence, walked out of the container without decoding
any pixels:

| Source | Carried in | Holds |
| --- | --- | --- |
| XMP | JPEG `APP1`, PNG `iTXt`/`zTXt`/`tEXt`, WebP `XMP ` chunk | every property |
| IPTC-IIM | JPEG `APP13` | creator, credit, copyright |
| EXIF | JPEG `APP1` | `Artist`, `Copyright` |
| C2PA | JPEG `APP11`, PNG `caBX`, WebP `C2PA` chunk | `digitalSourceType` only |

PNG carries XMP under four different shapes — the specified `XML:com.adobe.xmp` keyword
raw or deflated, the same keyword in a plain `tEXt`, and ImageMagick's own hex-wrapped
`Raw profile type xmp` — and all four are read.

### AI-generated images

ChatGPT and Gemini stamp their output with `Iptc4xmpExt:DigitalSourceType` and a credit
line of exactly `AI Generated` or `Made with Google AI`. That is a provenance note, not
somebody claiming the image, so it is moved to `digitalSourceType`, removed from the
credit line, and the image is seeded like any other image the site owns.

The credit marker is matched exactly, so a real agency named "AI Generated Studio Ltd"
keeps its credit and still gates as third-party.

{id=c2pa}
### C2PA (Content Credentials)

A `gpt-image` PNG carries **no XMP, no IPTC and no EXIF at all** — its only metadata is a
C2PA manifest, which is what OpenAI, Google, Adobe and the camera makers now write. The
`c2pa.actions` assertion inside it holds the same IPTC NewsCode vocabulary:

```
claim_generator_info.name = "OpenAI Media Service API"
softwareAgent             = gpt-image 2.0
digitalSourceType         = …/digitalsourcetype/trainedAlgorithmicMedia
```

Only that `digitalSourceType` is read. The manifest also names the signer, and treating a
signing certificate as the `creator` would publish an ownership claim nobody made.

**The signature is not verified.** This answers "what does the file say about itself" —
the same question asked of XMP, which anybody can equally write. It is not evidence of
authenticity, and the resulting value stays editable in the admin. A camera writes the
same assertion to say the opposite (`digitalCapture`), so the value is read rather than
the mere presence of a manifest.

### Replacing the file on an existing media

A replacement **discards the previous values and re-runs the decision** — never a merge.
Filling only the empty fields would be wrong in both directions: a stale photographer
surviving onto a photo the site now owns, or the site's `acquireLicensePage` staying
attached to somebody else's photo.

If the licence had been asserted by hand, the admin says so with a flash message.
Rotating an image does not reset anything, and re-uploading byte-identical content is a
no-op.

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
through `media.csv` for free.

`creator` is a list of `{name, type}`:

```yaml
creator:
  - name: 'Enrico Romanzi'
    type: Person
  - name: 'Altimood'
    type: Organization
```

The type belongs to the name rather than to the media, because schema.org emits one node
per creator and a photographer credited next to the agency that commissioned the shot are
not the same kind of entity. Several creators emit an array of nodes, a single one a bare
object — the shape Google's own example uses.

No file format carries a type: `dc:creator` is an `rdf:Seq` of bare strings, IPTC By-line
and EXIF `Artist` are plain text. Imported creators therefore fall back to `Person`, which
is editable per name. Anywhere only one input is available — a config value, a media-list
row — the compact form `Enrico Romanzi (Person), Altimood (Organization)` is accepted, and
a bare list of names is read as people.

`digitalSourceType` is stored but **never emitted** — no schema.org property on
`ImageObject` carries it, and Google reads provenance from the file itself. It is kept
for editorial and compliance use.

`licenseState` is a derived, read-only column: `` (none), `seeded`, `overridden` (a human
asserted it) or `thirdParty`. The media list filters and sorts on it.

{id=admin}
## In the admin

The **License** block on the media form holds the six fields, collapsed; `creator` is a
row-per-creator collection with its own add and remove buttons. Two buttons sit under the
block: *Apply the site license* fills it from the seed — pressing it **is** the ownership
assertion — and *Clear* empties it so the media stops emitting.

Creating a media redirects to the media list, so the decision is announced twice on the
way there. A flash says what happened — the site's license was added, or the file carries
rights of its own and names whom it credits — and the row itself carries a **License
state** badge (*Site license*, *Third-party rights*, *Asserted by hand*; an undecided
media shows none). Nothing about the licensing of an image is applied without saying so.

Multi-upload stays silent instead: it discloses per row, as each file lands, so a flash
per file would only pile up. A row whose file claims third-party rights shows what was
imported and from where, so a photographer's name is never stored in a field nobody can
see.

Because scaling an image down in the browser re-encodes it through a canvas and destroys
every metadata segment, files that carry rights metadata are uploaded uncompressed. On a
typical library that is about 1 % of uploads.

{id=backfill}
## Backfilling an existing library

Media that predate the feature never went through an upload hook:

```bash
bin/console pw:media:license --dry-run   # preview the decision
bin/console pw:media:license             # seed, import, and report the exceptions
bin/console pw:media:license --force     # also license what the files credit to others
bin/console pw:media:license --all       # re-decide media that already have a state
```

The command prints every file it left to its own rights, so each one can be decided by
hand. `--force` is the bulk form of the *Apply the site license* button; media whose
licence was asserted by hand are never rewritten.

{id=not-embedding}
## Why the files are not rewritten

Pushword does not write XMP back into images. Imagick cannot splice a profile into a
compressed file without decoding and re-encoding it, which throws away exactly the bytes
`cjpeg`/`cwebp` earned and adds a second generation of lossy artefacts. Google needs only
one of the two methods and prefers structured data on conflict, so embedding would buy
nothing for search while adding an invalidation path and a dozen writes per media.

The cache variants carry no rights metadata either, and never will: the webp encoder
drops every profile, and `cjpeg` re-encodes a JPEG from scratch, so whatever the source
file said about its rights does not reach the served derivative. The `ImageObject` is the
only licensing signal on the page — which is why its `contentUrl` is built exactly like
the `<img src>` in `component/image.html.twig`: the `default` filter in the *source*
format, not the webp variant, so Google associates the node with the image it crawled.
