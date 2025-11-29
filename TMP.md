# Bug Report

| Subject        | Details                                       |
| :------------- | :-------------------------------------------- |
| Rector version | v2.2.9 (invoke `vendor/bin/rector --version`) |

<!-- Please describe your problem here. -->

`ControllerMethodInjectionToConstructorRector` breaks EasyAdmin CrudControllers when applied to methods like `configureCrud()`.

## Minimal PHP Code Causing Issue

```php
<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class MediaCrudController extends AbstractCrudController
{
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Media')
            ->setEntityLabelInPlural('Medias');
    }
}
```

After Rector refactoring, the `$crud` parameter is moved to the constructor, resulting in:

```php
public function configureCrud(): Crud
{
    // ...
}
```

This causes a compile error:

```
Compile Error: Declaration of App\Controller\Admin\MediaCrudController::configureCrud(): EasyCorp\Bundle\EasyAdminBundle\Config\Crud must be compatible with EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController::configureCrud(EasyCorp\Bundle\EasyAdminBundle\Config\Crud $crud): EasyCorp\Bundle\EasyAdminBundle\Config\Crud
```

## Expected Behaviour

Rector should skip `ControllerMethodInjectionToConstructorRector` for methods that override parent class methods with a specific signature, especially for EasyAdmin's `AbstractCrudController` methods like `configureCrud()`, `configureFields()`, `configureActions()`, etc.

The rule should detect that removing the method parameter would break the parent class contract and skip the transformation.
