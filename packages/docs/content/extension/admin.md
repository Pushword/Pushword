---
title: 'Standard Admin for Pushword : Admin User Interface'
h1: Admin
toc: true
parent: extensions
---

Create, edit, delete Page, Media, User with an interface built on top of Sonata Admin.

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

## Customize the admin

Admin is built on top of Sonata Admin with one more feature : the ability to manage displayed form fields from the configuration ([not yet for list fields and search fields](/roadmap)).

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
                    - Pushword\Admin\FormField\PriorityField
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
