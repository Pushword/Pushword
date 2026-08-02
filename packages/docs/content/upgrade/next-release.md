---
title: 'four more entities expose their columns as properties'
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

**Concerns:** pushword/conversation, pushword/core, pushword/page-scanner,
pushword/repurpose, pushword/snippet

## Message, Snippet, SocialPost and the last of Page lost their accessors

The property-hook migration that emptied `Page` in rc825 now covers the entities that
still wrapped a column in a get/set pair. Read and write the property instead:

```diff
-$snippet->setSlug('cta')->setContent($markdown);
-$message->setAuthorEmail($email);
-$post->setSpec($spec);
+$snippet->slug = 'cta';
+$snippet->content = $markdown;
+$message->authorEmail = $email;
+$post->spec = $spec;
```

Full list — `Message`: `authorName`, `authorEmail`, `authorIp`, `referring`,
`publishedAt`. `Snippet`: `slug`, `name`, `content`. `SocialPost`: `page`, `network`,
`format`, `status`, `plannedAt`, `spec`. `Page`: `mainContent`, and `setTemplate()`
(`getTemplate()` stays — it falls back to the extended page). `User`: `getId()`, whose
contract has been the `$id` property on `IdInterface` for a while.

Everything those setters did survives in a `set` hook, so writing the property still
normalizes: `Snippet::$slug` lower-cases and trims, `Page::$mainContent` strips the
editor's empty anchors and invalidates the parsed redirection, and `SocialPost::$spec`
mirrors its queryable fields into the columns beside it. Doctrine writes the backing
store directly, so hydrating a row runs none of it — same as before.

Two things did **not** move. `Message::getContent()` stays a method: the column is
nullable and the getter collapses that to a string, which a hook cannot do. `Media` keeps
its accessors — only 6 of its 43 were passthroughs.

Twig keeps working either way: `ExtensiblePropertiesTrait::__call()` answers a removed
getter from the property that replaced it. The one case it changes is a custom property
deliberately named after a public column — read that with `getCustomProperty('name')`.

`Page::normalizeMainContent()` is new and public: it is what the `mainContent` hook
applies, exposed so `pw:page:clean` can re-run it over stored content without writing a
self-assignment.

## Image alt falls back to the h1 when a page has no title

`page/_content.html.twig` and advanced-main-image's `_content_hero.html.twig` built the
main image's `alt` from `page.title`, the raw column. They now read `pw(page).title`,
which runs the `title` filter chain — `ElseH1` first. A page with an h1 and an empty
title used to render `alt=""`; it now gets the h1. Override those blocks if you relied on
the empty value.

## `pw:page-scan` reports date shortcodes left unresolved

A `date(Y)` still readable in the rendered HTML is now an error, because it means the
text was printed without crossing the markdown parser or the `Date` entity filter — a
raw `page.title` in a template, a meta tag, a custom property. Visitors see the literal
shortcode.

A scan that gates CI can therefore turn red on content that never changed. Fix the
template (`pw(page).title` rather than `page.title`), or silence the case with
`errors_to_ignore: ['*date shortcode left unresolved*']`. Code samples and `<script>`
blocks are already exempt.
