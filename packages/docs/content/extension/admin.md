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

That's it ! If you have a custom installation (not used the [default installer](/installation)),
you may have a look inside `vendor/pushword/admin/install.php`.

Admin is now accessible via <small>https://mydomain.tld</small>`/admin/`.

Don't forget to create an user with **ROLE_SUPER_ADMIN** to access to the just installed admin :

```shell
php bin/console pw:user:create
```

You may be intersted by the [block editor](/extension/admin-block-editor).

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

It covers the crash, the closed tab and the expired session. *Restore them* refills the
form (including the markdown body) without saving anything, so you still review before
publishing; the copy is only dropped once a save succeeds, or when you press *Discard*.
It never leaves your browser, so it does not follow you to another machine — and it is
unrelated to the **Draft** toggle, which is a publication state stored in the database.

A site overriding `@pwAdmin/page/edit.html.twig` has to carry over the
`unsaved_changes_banner.html.twig` include and the form's `data-pw-unsaved-key`
attribute, or the recovery has nowhere to render.

## Customize the admin

Admin is built on top of EasyAdmin with one more feature : the ability to manage displayed form fields from the configuration ([not yet for list fields and search fields](/roadmap)).

You can also [customize the admin menu](/extension/admin-menu) to add, remove or reorder menu items.

So, in your configuration, your default configuration is :

```
pushword_admin:
    app_fallback_properties:
        - admin_page_form_fields
        - admin_user_form_fields
    admin_page_form_fields:
        -
            - Pushword\Admin\FormField\PageH1Field
            - Pushword\Admin\FormField\PageMainContentField
        -
            admin.page.state.label:
                - Pushword\Admin\FormField\PagePublishedAtField
                - Pushword\Admin\FormField\PageMetaRobotsField
            admin.page.permanlien.label:
                - Pushword\Admin\FormField\HostField
                - Pushword\Admin\FormField\PageSlugField
            admin.page.mainImage.label:
                - Pushword\Admin\FormField\PageMainImageField
            admin.page.parentPage.label:
                - Pushword\Admin\FormField\PageParentPageField
            admin.page.search.label:
                expand:              1
                fields:
                    - Pushword\Admin\FormField\PageTitleField
                    - Pushword\Admin\FormField\PageNameField
                    - Pushword\Admin\FormField\PageSearchExcreptField
                    - Pushword\Admin\FormField\WeightField
            admin.page.translations.label:
                - Pushword\Admin\FormField\PageLocaleField
                - Pushword\Admin\FormField\PageTranslationsField
            admin.page.customProperties.label:
                expand:              1
                fields:
                    - Pushword\Admin\FormField\CustomPropertiesField
            admin.page.og.label:
                expand:              1
                fields:
                    - Pushword\Admin\FormField\OgTitleField
                    - Pushword\Admin\FormField\OgDescriptionField
                    - Pushword\Admin\FormField\OgImageField
                    - Pushword\Admin\FormField\OgTwitterCardField
                    - Pushword\Admin\FormField\OgTwitterSiteField
                    - Pushword\Admin\FormField\OgTwitterCreatorField
    admin_user_form_fields:
        -
            - Pushword\Admin\FormField\UserEmailField
            - Pushword\Admin\FormField\UserUsernameField
            - Pushword\Admin\FormField\UserPasswordField
            - Pushword\Admin\FormField\CreatedAtField
        -
            admin.user.label.security:
                - Pushword\Admin\FormField\UserRolesField
```

You can directly edit this default list or customize them by editing this list on the fly with the `pushword.admin.load_field` event (see [admin-block-editor extension](/extension/admin-block-editor) for an example).

You can customize fields on [app](/configuration#configuration-types) level, but when we create a new page, we don't know yet in wich app we are, we will use first app configuration (or global).