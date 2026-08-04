---
title: 'the page scanner reports missing image alts and colliding translation locales'
publishedAt: '2099-01-01 00:00'
parentPage: upgrade
---

<!--
The upgrade note for the next release. `.scripts/release` renames this file to
`upgrade/rc<N>.md`, adds its row to the table in `upgrade.md` and empties it back
to this scaffold, at the tag.

Write here, in the same commit as the change, whenever a release asks something of
a site that upgrades: a command to run, a config key to set, a template to copy, a
behaviour that changed under an unchanged call. A change `composer update` fully
absorbs needs no note.

- `title:` — the "What changed" cell of the index table. One line, lower case,
  written from the site's side ("the newsletter form is fetched, and CSRF-protected")
  rather than the diff's ("refactor NewsletterFormController"). Required as soon as
  the note has a section; the release stops if it is still empty.
- `run:` — the command(s) the release expects, without `php bin/console`. Omit the
  key when there is none. A list runs in the order given.
- `**Concerns:**` — first line of the body, listing every package a site has to
  install to be affected. Alphabetical, full composer names, `@pushword/js-helper`
  last. Add the packages your change touches to the line, keep the others.
- One `##` section per change, saying what breaks and what to do about it.

Several changes land here between two tags: append to the file, do not replace it.
-->

**Concerns:** pushword/newsletter, pushword/page-scanner

## Two new checks report on content that already exists

`pw:page-scan` gained two checks, so a site that was green can go red on the first
run after the upgrade without its content having changed.

**Image alt.** Every rendered `<img>` with no `alt`, or an `alt` that is empty or
only whitespace, is reported once per `src`:

```
`/media/default/lake.jpg` image without alternative text
```

Fill the alt in the admin, in `media.csv`, or as the `![alt](…)` caption. An image
that really is decorative keeps its empty alt and says so — the scanner skips
`role="presentation"` and `aria-hidden="true"`.

**Translation locales.** A page whose `translations` hold its own language, or two
of them sharing one language, is now reported:

```
translation `/home` has the same language as this page (en)
two translations share the language fr: `/bienvenue` and `/accueil`
```

Detach the extra page from the group: two pages in the same language are variants,
not translations.

Either check can be silenced site-wide or per route through the existing
`errors_to_ignore`, which accepts `fnmatch` patterns:

```yaml
pushword_page_scanner:
  errors_to_ignore:
    - '*image without alternative text*'
```
