---
title: "Standard Admin for Pushword : Admin User Interface"
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
php bin/console pushword:user:create
```
