---
title: 'the block editor uploads inline, links carry any rel, and page-scan tells an unreachable link from a bad status'
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

**Concerns:** `pushword/admin-block-editor`, `pushword/page-scanner`

## The Upload button opens your file dialog instead of the media form

On an image or attachment block, **Upload** used to open the media form in a modal
iframe: pick a file, fill the form, submit, come back. It now opens the device's
file dialog and uploads what you pick straight away — the same request a file
dropped on the block already made. **Select** still opens the media library.

Nothing to do, unless you ship your own copy of `editorjs_widget.html.twig`: the
`onUploadFile` callback of the `image` and `attaches` tools must become
`window.editorJsHelper.onUploadInline`, and `editorJsHelper.onUploadFile` is gone
(`onUploadImage`, which the gallery and embed tools still use, is untouched).

Both blocks also gained a button to drop the media they hold, so changing a picture
no longer means deleting the block and building a new one.

## Links take any rel, not just "obfuscate"

The link tool's **Obfusquer** switch is now a `rel` select: `obfuscate`, `nofollow`,
`nofollow sponsored`, `nofollow ugc`. Existing links keep the rel they carry — one
the list does not offer is kept as an entry of its own rather than dropped.

The list is `availableRels` next to `availableDesigns` in the tool's config, so a
site that overrides `editorjs_widget.html.twig` can declare its own:

```js
link: {
    name: "link",
    className: "Hyperlink",
    config: {
        availableDesigns: { /* … */ },
        availableRels: { Obfusquer: 'obfuscate', me: 'me' },
    }
},
```

`obfuscate` stays exclusive: `HtmlObfuscateLink` matches `rel="obfuscate"` exactly,
so it cannot be combined with a real rel.

## `link-external` splits into `link-status` and `link-unreachable`

An external link can fail two ways, and they are not the same problem: a URL that
answers something unexpected (`link-status`) versus one that does not answer at all —
DNS, timeout, TLS (`link-unreachable`). Both shipped under one `link-external` code in
rc835, so silencing a flaky host also silenced every 404.

**If your `errors_to_ignore` names `link-external`, it no longer matches anything.**
Replace it with whichever half you meant, or with `link-*` to keep both:

```yaml
pushword_page_scanner:
  errors_to_ignore:
    - 'link-unreachable'   # the host is down, not your link
```

Same for a `<!-- page-scanner-ignore: link-external -->` comment in a page.

This is the one code change there will be: `ScanErrorCodeTest` now pins the whole set,
so a rename fails the build rather than silently un-ignoring findings on every site
relying on it.

The external-URL cache is keyed by result shape, so the first scan after upgrading
rechecks every external link once, then goes back to being cached.
