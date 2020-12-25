# Pushword Bootstrap 5 Theme

http://tools.bitfertig.de/bootstrap2tailwind/index.php

## Installation

Get it with composer

```
composer require pushword/bootstrap5-theme
```

Edit your `config/packages/twig.yaml` and add :

```
twig:
    ...
    paths:
        ...
        "%kernel.root_dir%/../vendor/piedweb/theme-component-bundle/src/Resources/views": PushwordCore
        "%kernel.projet_dir%/vendor/piedweb/theme-component-bundle/src/Resources/views": PiedWebThemeComponent
```

<p align="center"><a href="https://piedweb.com" rel="dofollow">
<img src="https://raw.githubusercontent.com/PiedWeb/piedweb-devoluix-theme/master/src/img/logo_title.png" width="200" height="200" alt="PiedWeb.com" />
</a></p>
