---
title: 'a newsletter contact can be keyed on a phone number, and subscribed no longer means mailable'
publishedAt: '2099-01-01 00:00'
parentPage: upgrade
run: 'doctrine:schema:update --force'
---

**Concerns:** `pushword/newsletter`

## A contact the site can only phone

`newsletter_contact.email` becomes nullable and gains a `phone` next to it,
unique per audience like the address, with at least one of the two required. A
person known only by a number — a booking taken over the phone, a client met on
site — now has a row, where before they had none.

Run `bin/console doctrine:schema:update --force` after updating; the two new
indexes and the relaxed column come from it.

Nothing changes for a base that only holds addresses. What changed under an
unchanged call, for everyone:

- **`subscribed` and mailable are two questions now.** Everything that sends —
  campaign arming, automation enrollment, the counts shown before Send,
  `estimatedRecipients` — asks the second, `Contact::isMailable()`: subscribed
  *and* holding an address. The API's audience payload gained a `mailable`
  count next to the per-status ones, and each contact a `mailable` flag.
- **`ContactManager::subscribe()` takes a nullable `$email`** and a trailing
  `$phone`. Callers passing an address positionally are unaffected.
- **`ContactRepository::findAllByEmail()` accepts null** and answers nothing for
  it, as `findSubscribedSiblings()` now does for a contact with no address.
  There is no sibling relation between two rows that have no address — the
  address is what makes them the same person.
- **`Contact::$email` is `?string`.** Code reading it straight into a string —
  a custom template, a listener — needs a null guard. `Contact::identifier()`
  gives the address, else the number, else `#<id>`.

The public subscribe form stays email-only. A number reaches the base over the
API or through the admin's *Opt in a contact*, and a contact keyed on one alone
is subscribed without a confirmation mail — there is none to send. `source`
records who entered it, which is the evidence that opt-in owes.

Segments gained `email` and `phone`, both taking `isSet` / `isNotSet`, so
"everybody I can only phone" is a rule you can write.

Both identifiers are now declared to the validator as well as to the schema, so
writing one that another contact of the same audience already holds comes back
as a `409` from the contact upsert and a validation error from `PATCH` and the
admin — where it previously reached the driver as an integrity violation. The
two rows are not joined: merging two consent records is a deliberate operation,
and the bundle does not have one.

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
