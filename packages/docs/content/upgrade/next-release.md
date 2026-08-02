---
title: 'six unused editorjs twig helpers are gone'
publishedAt: '2099-01-01 00:00'
parentPage: upgrade
---

**Concerns:** `pushword/admin-block-editor`, `pushword/core`

## `blockWrapperAttr` and five other block helpers were removed

Six Twig callables left over from an earlier EditorJS rendering scheme have been
dropped. None was called by any shipped template, none was documented, and two of
them could not have worked:

| Removed | From |
| --- | --- |
| `blockWrapperAttr()` | `Pushword\Core\Twig\BlockExtension` |
| `blockWrapperAlignment()` | `Pushword\Core\Twig\BlockExtension` |
| `legacyImageArray()` | `Pushword\Core\Twig\BlockExtension` |
| `legacyImageName()` | `Pushword\AdminBlockEditor\Twig\AppExtension` |
| `legacyImageArray()` | `Pushword\AdminBlockEditor\Twig\AppExtension` |
| `fixHref` (filter) | `Pushword\AdminBlockEditor\Twig\AppExtension` |

`blockWrapperAttr()` emitted `class=" "` on every block carrying no class tune, and
raised two `Undefined array key "class"` warnings doing it — its normalizer never set
the default its own docblock promised. And `legacyImageArray` was registered twice
under that one Twig name with two different return shapes, so which one you got
depended on extension registration order.

`blockWrapperId()` stays — that one is used by the `attaches`, `video` and
`pages_list` components.

If a template of yours calls one of these, copy the body out of the git history into
your own Twig extension. Nothing else in Pushword reads them.
