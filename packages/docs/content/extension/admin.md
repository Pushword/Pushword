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

### Diagnosing slow edit-lock pings

The editor posts to `/admin/page/{pageId}/lock/ping` every three seconds. Edit locks
live in `var/page-locks/`, separately from PHP session locks. A ping can wait for its
session during authentication even when its SQL query is fast. Core releases the
session before the controller runs; later session access can acquire the lock again.

For native file sessions, diagnose contention on a local server with at least two
PHP workers:

1. Record a ping's elapsed time, SQL count and SQL time in the Symfony profiler.
2. Hold that session file's lock for two seconds in another process, then repeat
   the ping alongside a request from a second authenticated session.
3. A firewall wait affecting only the held session indicates session contention.
   If both sessions wait, check worker capacity and shared resources.
4. Repeat alongside the actual slow admin request to find where it retains or
   reopens the session before changing session handling.

### Parent page

In the settings panel, search by page title, slug, path or a URL on a configured
host. Choices show the title and host/path separately; an exact slug comes before
its children and other matches. Only pages on the edited page's host are offered,
and the page cannot be its own parent.

### Saving and recovering changes

**Save** opens the page view after saving. Use `Ctrl+S` or `Cmd+S` to save and
stay in the editor. Installed page tools such as Auto links and Repurpose are
under **More actions**.

While you edit, the browser keeps a local copy for your account. **Restore them**
puts your edited fields back in the form and warns if a newer revision changed
any of them. A successful save, **Discard** or sign-out clears the copy.

The editor checks for newer revisions every three seconds and when you return
to the tab. An untouched, visible editor reloads. If you have started editing,
the banner opens the latest version in another tab and lets you review and
recover your work. The server rejects saves from an older revision.

If you override `@pwAdmin/page/edit.html.twig`, retain the unsaved-changes and
revision banners, `data-pw-unsaved-key` and the shortcut submit button. Render
all remaining fields with `form_rest` or `form_end` to submit the revision.

## Collaborative page editing

On a saved Markdown page, choose **Collaborative mode** in the page action bar.
Editor.js and Monaco merge and save body changes as you type. Other editors
see an invitation to join and can bring local edits from a revision the session
still holds. Once you join, the action bar shows the initials of active
collaborators. The code icon marks sessions using the collaborative API,
including sessions authenticated with your account.

Live saves follow the page's publication status. Changing the publication date
pauses live saves until you save the date and body together.
With the [Version extension](/extension/version),
the next edit adds a restore point after five idle minutes or thirty minutes
of continuous editing.

The PHP web process needs Node.js 24 on its `PATH` and `proc_open` enabled.
If a request reaches the 4 MiB limit, switch to classic mode to save manually.
If a classic or API save ends the session, use the recovery prompt to review
your local changes against the latest revision.

## Uploading media

On a new or existing media form, choose a file or drop it anywhere on the page.
A drop selects the first file; save the form to upload it.
See [Media rules](/media-rules) to restrict accepted files.

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

If your field filters its candidates on the page being edited ("not myself", "same
host"), read that page with `Pushword\Admin\FormField\PageFormSubjectTrait`, not
`$this->admin->getSubject()`: EasyAdmin rebuilds the fields against an *empty* subject
to answer an autocomplete request, so the list would offer the page itself and every
host's pages.

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

### Reference help

A field's help shows under the field. Long reference copy can open from a help button
next to the label instead: set the `context_help` form option, on any admin form.

```php
yield TextField::new('slug', 'mySlugLabel')
    ->setHelp('mySlugHelp')
    ->setFormTypeOption('context_help', 'mySlugSummary');
```

`true` moves the whole help into the popover. A string is the translation key of a short
line that stays under the field, for what the editor must not miss: a consequence, a
constraint. Opt in reference copy only — help holding controls, warnings or history must
stay visible. A field built with `AbstractField::buildEasyAdminField()` passes it among its
options (`'context_help' => true`).
