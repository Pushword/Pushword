---
h1: Installation
title: Install Pushword in a few seconds (automatic installer)
parent: homepage
toc: true
---

## Requirements

-   **PHP** 7.4
-   **PHP extensions** : dom, curl, libxml, mbstring, zip, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv, fileinfo
-   **Composer** (#[how to install composer](https://getcomposer.org/download/))

## Automatic installer via CLI (unix)

```

curl https://raw.githubusercontent.com/Pushword/Pushword/main/packages/installer/src/installer >> installer && chmod +x installer && echo 'y' | ./installer ./my-folder

```

## Manual installation

Look in the shell script [installer](https://github.com/Pushword/Pushword/blob/main/packages/installer/src/installer) where each step is describe.

<!-- for postcss... -->
<pre style="display:none"><code>...</code></pre>

## Update

Stay up to date with only one command :

```
composer update
```
