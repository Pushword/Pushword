---
title: 'Your own media rules: validation and removal veto'
h1: 'Media rules'
publishedAt: '2026-10-15 18:00'
toc: true
---

An app can add its own rules to Pushword's media without touching Pushword. Examples
are a file-name convention, a mandatory credit, or a tag grammar. The rules use two
extension points: Symfony validation constraints on the `Media` entity, and an event
that can veto a removal.

## Validation on every write

Each interactive path validates the whole `Media` (group `Default`) before it saves.
The validation covers Pushword's own constraints and every constraint the app maps
onto the entity:

| Path | A refusal shows as |
| ---- | ------------------ |
| Admin form, new and edit, including the media picker's *Upload* modal | A form error. A class constraint without `atPath` shows above the form |
| Multi-upload, after the duplicate check | `422 {"error": message}`, on the file's row |
| Inline editing (multi-upload rows, media table) | `422 {"error": message}`, under the edited field. Nothing is saved |
| Block editor upload, from a file or a URL, after the duplicate check | `422 {"success": 0, "error": message}`, in the image tool's notice |
| `POST`/`PATCH /api/media/{filename}`, upload and metadata | `422 {"error": "validation", "violations": [{path, message}]}`, see [Media API](/media-api#errors) |

The form and API return all violations; the other channels show the first one.
Messages are translated through the `validators` domain. An upload that matches an
existing media's SHA-1 skips entity validation because it writes nothing.

Maintenance paths do not validate. These are the flat import, console commands,
fixtures and `ExternalImageImporter`.

A custom write path uses the same check:
`Pushword\Core\Service\MediaUploadValidator::validateMedia(Media $media): ConstraintViolationListInterface`.

### Adding a constraint

Map it onto `Pushword\Core\Entity\Media`, as you would onto your own entity. For
example, in `config/validator/media.yaml`:

```yaml
Pushword\Core\Entity\Media:
    constraints:
        - Callback:
            callback: [App\Media\MediaRules, validate]
```

```php
public static function validate(Media $media, ExecutionContextInterface $context): void
{
    if (null === $media->id && ! str_contains($media->getFileName(), '-')) {
        $context->buildViolation('Name the file after its subject.')->addViolation();
    }
}
```

### What a constraint can read on a new upload

A new upload has `id === null` and a non-null `getMediaFile()`.

- `getFileName()`: the name the file is about to be stored under. It comes from the
  admin form's name field or the API URL; otherwise it is derived from the alt, then
  the client file name. It is slugified, and its extension comes from the content.
  This is the name **before** the anti-collision suffix (`-2`) that may follow.
- `getMediaFile()`: the uploaded file. Use its `getClientOriginalName()`,
  `getMimeType()` (guessed from the content), `getSize()` and `getPathname()`.
- `getMimeType()` is still `null`, `size` is `0`, and dimensions and hash are unset:
  they are filled at persist. Read them from `getMediaFile()`.
- `getAlt(true)`: the alt that was submitted. It is empty for the multi-upload and
  the block editor, which derive it at persist.
- `getTags()` and the custom properties: what the request supplied. That includes the
  admin form's license block, written back before validation. In the admin form,
  read unmanaged keys through `getEffectiveCustomProperties()`: the free-form YAML
  textarea merges during validation.
- The rights embedded in the file are not imported yet. Read what the upload will
  import with
  `Pushword\Core\Image\License\UploadRightsReader::read(File $file): EmbeddedRights`.
  It reads the file, backed by the metadata the admin posts beside a canvas-resized
  image.
- `licenseState` is not decided yet.

A file replaced on an existing media has a non-null `id` and a non-null
`getMediaFile()`. Without a new file, `getMediaFile()` is `null` — including later in
the request that uploaded it: once the bytes are stored, the media lets go of the file.

### Judge the change, not the state

Validation runs on whole entities, so a rule fails every edit of a media that broke
it before the rule existed. For example, inline editing changes one field at a time.
A media missing a credit and carrying a malformed tag can then be fixed by neither
edit alone.

A rule that must tolerate existing data should judge what the write changes, and
read the stored values through `UnitOfWork::getOriginalEntityData($media)`. The
entity is managed whenever validation runs on an existing media.

## Vetoing a removal

`Pushword\Core\Event\MediaPreRemoveEvent` (`PushwordEvents::MEDIA_PRE_REMOVE`) asks
whether a media may be removed. A listener calls `refuse(string $reason)`. Every
listener is asked, and `getReasons()` returns all their reasons.

```php
#[AsEventListener(event: MediaPreRemoveEvent::NAME)]
public function keepSlideshowPhotos(MediaPreRemoveEvent $event): void
{
    if (\in_array('slideshow', $event->media->getTagList(), true)) {
        $event->refuse('Shown by a slideshow.');
    }
}
```

| Path | A refused media is… |
| ---- | ------------------- |
| Admin delete and batch delete | Kept, with an error flash naming it. The rest of a batch is deleted |
| Multi-upload and media table delete | Kept: `409 {"error", "reasons"}`, shown in a notice |
| `DELETE /api/media/{filename}` | Kept: `409 {"error", "reasons"}` |
| `pw:media:clean-unused` | Skipped, counted (`refused` in JSON) and listed with its reasons |

The dry run of `pw:media:clean-unused` asks too, so its list is what `--force`
removes. A listener must therefore decide without side effects.
