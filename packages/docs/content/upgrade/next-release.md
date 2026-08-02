---
title: 'a fresh install ships real starter content; six unused editorjs twig helpers are gone'
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

## A fresh install ships starter content, not the test fixtures

`install.php` used to copy `dev-app`'s `AppFixtures.php` — 378 lines of the monorepo's own
test rig — into your `src/DataFixtures`, and load it. New installs got `kitchen-sink`,
`quiz-montagnes` (French), `fr/homepage`, `fr-ca/homepage` and two variant-demo pages,
several of which render empty on a site that has none of the pages or bundles they refer
to.

It now copies `vendor/pushword/core/starter`: four pages — `homepage`,
`getting-started`, `examples` and `contact` — in the app's default locale, all tagged
`demo`. Edit `src/DataFixtures/AppFixtures.php` to seed your own content instead.

Three files no longer land in `media/`: `piedweb-logo.png`, `logo.svg` and `test.pdf`.
They are the test suite's fixtures, not yours. `dev-app` keeps all of them, so nothing
changes when developing Pushword itself.

Only fresh installs are affected — an existing `var/app.db` is never reloaded.

## `pw:page:delete` removes pages by tag

```bash
php bin/console pw:page:delete --tag=demo
```

It lists what it matched and asks before deleting. Without a terminal it refuses unless
you pass `--force`, so a hook or an agent cannot wipe pages by accident. It supports
`--format=agent` and reports the slugs it removed.

This is how you clear the starter content once you have read it.
