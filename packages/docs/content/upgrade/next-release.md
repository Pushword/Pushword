---
title: 'page exposes its columns as properties, with no getter/setter left'
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

**Concerns:** `pushword/core`

## Page drops the last of its getter/setter pairs

[rc372](/upgrade/rc372) turned `Page`'s simple columns into PHP 8.4 property hooks and
kept the accessors "for caller compatibility"; [rc799](/upgrade/rc799) removed the
equivalent pairs everywhere else. The ones on `Page` are gone now — read or assign the
property of the same name:

```diff
-$page->getH1();          $page->setH1('Bonjour');
-$page->getSlug();        $page->setSlug('bonjour');
-$page->getTitle();       $page->setTitle('Bonjour | Demo');
-$page->getName();        $page->setName('Bonjour');
-$page->getMetaRobots();  $page->setMetaRobots('noindex');
-$page->getPublishedAt(); $page->setPublishedAt(new DateTime());
+$page->h1;               $page->h1 = 'Bonjour';
+$page->slug;             $page->slug = 'bonjour';
+$page->title;            $page->title = 'Bonjour | Demo';
+$page->name;             $page->name = 'Bonjour';
+$page->metaRobots;       $page->metaRobots = 'noindex';
+$page->publishedAt;      $page->publishedAt = new DateTime();
```

Also gone: `getCustomCanonical()`/`setCustomCanonical()`,
`getHoldPublicationAt()`/`setHoldPublicationAt()`, `getLocale()` and `getEditMessage()`.

The hooks still normalize on assignment, so `$page->slug = 'Ma Page'` stores `ma-page`
exactly as `setSlug()` did, and `null` still lands as `''`. Everything that does more
than return a value stays a method: `isHoldPublication()`, `setHoldPublication()`,
`getRealSlug()`, `getMainContent()`/`setMainContent()`, `getTemplate()`,
`isPublished()`, `isIndexable()`.

Fluent construction goes with the setters; assign instead:

```diff
-$page = new Page()->setSlug('bonjour')->setH1('Bonjour');
+$page = new Page();
+$page->slug = 'bonjour';
+$page->h1 = 'Bonjour';
```

**In Twig nothing changes**: `page.h1`, `page.slug` and `pw(page).title` resolve the same
through property access, and a template still calling `page.getTitle()` is answered by
the property that replaced it rather than by an unset custom property. Update those calls
anyway — any argument they carry (`page.getTitle(true)`, from a signature that has not
existed for a long time) is silently ignored.

That bridge lives in `ExtensiblePropertiesTrait::__call()`, so it covers every entity
using it — `Page`, `Media`, `User`, `Message`, `Snippet`, `Contact`. The one case it
changes is a custom property deliberately named after a public column and read as
`$entity->getThatName()`: the column now answers instead of the bag. Read it explicitly
with `getCustomProperty('thatName')`.

## The filter pipeline reads properties, and Manager is a facade

`ContentPipeline` used to resolve a filtered property by building a getter name and
calling it. It now prefers a real method and falls back to the public property of the
same name, so a hooked column with no accessor still goes through the filters. Two
consequences for custom code:

- `Pushword\Core\Component\EntityFilter\Manager` no longer implements the filtering — it
  delegates to the page's `ContentPipeline`. Filters and `pushword.filter_before`/`_after`
  listeners still receive it, unchanged, and `$manager->page`, `getPage()` and the magic
  `$manager->title()` all still answer. Its constructor now takes that single pipeline,
  and `getManagerPool()` is gone: to resolve a property against another page, use
  `$manager->getPipeline()->for($otherPage)->getFilteredProperty('Title')`.
- `ManagerPool` is now a thin delegate over `ContentPipelineFactory`: it takes only that
  factory, and `ContentPipelineFactory` no longer takes a `ManagerPool`. Only code
  constructing either by hand, rather than autowiring it, has to change. `ManagerPool`
  also stops implementing `ResetInterface` — it holds no cache of its own now, and the
  managers are discarded with the pipelines when `ContentPipelineFactory` is reset, so
  worker mode is unaffected. Call `reset()` on the factory instead.
- New on `ContentPipeline`: `for(Page)` returns the pipeline of another page, and
  `getLegacyManager()` the Manager facade of this one.
