---
title: 'Standard Admin for Pushword : Admin User Interface'
h1: Admin
publishedAt: '2025-12-21 21:55'
toc: true
---

Create, edit, delete Page, Media, User with an interface built on top of EasyAdmin.

## Install

```shell
composer require pushword/admin
```

The [default installer](/installation) wires the bundle automatically. Custom
installations can use `vendor/pushword/admin/install.php` as a reference.

The admin is available at `https://example.com/admin/`.

Create a user with `ROLE_SUPER_ADMIN` if the project does not have one:

```shell
php bin/console pw:user:create
```

For block-based editing, install the [Admin Block Editor](/extension/admin-block-editor).

## Editing a page

The body is edited in Monaco, with a toolbar above it and a word/line count below.
Formatting is a toggle: pressing a button a second time removes what it added, and with
nothing selected it applies to the word under the cursor.

| Key | What it does |
| --- | --- |
| `Ctrl+B` / `Ctrl+I` | bold, italic |
| `Alt+S` | strikethrough |
| `Ctrl+Shift+]` / `Ctrl+Shift+[` | move the heading level up and down |
| `Alt+C` | tick or untick a task item, making one if the line is not a task yet |
| `Ctrl+K` | wrap the selection in a link |
| `Enter` | carry the list marker over, numbering the next ordered item; on an empty item, end the list |
| `F1` | every command, by name |

Pasting a URL onto selected text links that text instead of replacing it. The toolbar's
last two buttons open the editor fullscreen and the [markdown cheatsheet](/editor).

The same editor, toolbar included, backs the markdown mode of
[admin-block-editor](/extension/admin-block-editor).

Saving is always something you ask for: `Ctrl+S` saves without leaving the form, and the
*Save and continue editing* button shows the result. There is no timed autosave, on
purpose — a page save is a publication (it rewrites the flat markdown, regenerates the
Open Graph image, purges the static cache, and turns a half-typed slug into a redirect
page), so nothing writes to the server until you say so.

What is automatic is the safety net. While you type, the form state is kept in your
browser's `localStorage`, and reopening the page offers it back:

> You left unsaved changes here 7 minutes ago, kept in this browser.
> **Restore them** · **Discard**

It covers the crash, the closed tab and the expired session. *Restore them* puts back the
fields you had changed (the markdown body included) and nothing else, without saving
anything, so you still review before publishing; a field you never touched keeps whatever
the page holds now, even if a colleague saved it meanwhile. Should that colleague have
saved one of *your* fields, the offer says so before you take it:

> You left unsaved changes here 7 minutes ago, kept in this browser. The page has been
> saved since, on fields you changed: restoring puts your version back over it.

The copy is only dropped once a save succeeds, or when you press *Discard*. It never
leaves your browser, so it does not follow you to another machine, and it belongs to the
account that typed it: it is not offered to anyone else signing in on that browser, and
signing out takes it along. It is unrelated to the **Draft** toggle, which is a
publication state stored in the database.

A site overriding `@pwAdmin/page/edit.html.twig` has to carry over the
`unsaved_changes_banner.html.twig` include and the form's `data-pw-unsaved-key`
attribute, or the recovery has nowhere to render.

## Customize the admin

Admin is built on EasyAdmin. Page, redirection, media and user form fields are
configuration-driven; list and search fields are not.

You can also [customize the admin menu](/extension/admin-menu) to add, remove or reorder menu items.

Inspect the effective configuration instead of copying defaults that may change:

```shell
php bin/console debug:config pushword_admin
```

Override `admin_page_form_fields`, `admin_redirection_form_fields`,
`admin_media_form_fields` or `admin_user_form_fields` in `pushword_admin`. Bundles can
alter the resolved lists with the `pushword.admin.load_field` event; the
[Admin Block Editor](/extension/admin-block-editor) is an example.

You can customize fields per site, but when creating a new page, Pushword does not yet know its site and uses the first site's configuration (or the global configuration).

### Writing a page form field

A field is a class extending `Pushword\Admin\FormField\AbstractField`, whose
`getEasyAdminField()` returns the EasyAdmin field to render. The three association
fields — parent page, variant of, translations — are autocompletes: their candidates
come from EasyAdmin's autocomplete endpoint as you type.

If your own field filters its candidates on the page being edited ("not myself", "same
host"), read that page with `Pushword\Admin\FormField\PageFormSubjectTrait`, not with
`$this->admin->getSubject()`. EasyAdmin answers an autocomplete request by rebuilding
the fields against an *empty* subject, so a query builder reading the subject directly
filters on nothing — the list would offer the page itself, and every host's pages.

```php
use Pushword\Admin\FormField\PageFormSubjectTrait;

class MyPageField extends AbstractField
{
    use PageFormSubjectTrait;

    public function getEasyAdminField(): ?FieldInterface
    {
        $page = $this->pageFormSubject();

        return AssociationField::new('myAssociation')
            ->onlyOnForms()
            ->setCrudController(PageCrudController::class)
            ->autocomplete()
            ->setQueryBuilder(fn (QueryBuilder $qb): QueryBuilder => $qb
                ->andWhere('entity.host = :host')
                ->setParameter('host', $page->host));
    }
}
```

`->setCrudController(PageCrudController::class)->autocomplete()` is what makes the
field fetch on demand; without it every candidate page is hydrated whole, `mainContent`
included, to build a `<select>`.
