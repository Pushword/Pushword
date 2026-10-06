---
title: 'Configure the Admin Menu'
h1: 'Configure the Admin Menu'
publishedAt: '2025-12-21 21:55'
name: Pushword
toc: true
---

`Pushword\Admin\Controller\AdminMenu` dispatches `AdminMenuItemsEvent`
(`pushword.admin.menu_items`) while building the menu. Subscribe to it to add, change or
replace items. Each item carries a **weight**; items are sorted by weight, highest first.

## Adding an item

```php
<?php

namespace App\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Pushword\Admin\Menu\AdminMenuItemsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class AdminMenuSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [AdminMenuItemsEvent::NAME => 'onMenuItems'];
    }

    public function onMenuItems(AdminMenuItemsEvent $event): void
    {
        $event->addMenuItem(
            MenuItem::linkToRoute('My Custom Page', 'fa fa-star', 'my_custom_route'),
            450,
        );
    }
}
```

Any EasyAdmin `MenuItem` works: `linkTo(MyCrudController::class, 'Label', 'fa fa-icon')`,
`linkToRoute()`, `linkToUrl()`, `subMenu('Label', 'fa fa-icon')->setSubItems([...])`,
`section('Title')`.

For a single route item, extend `Pushword\Admin\Menu\AbstractRouteMenuItemSubscriber`
(the template editor does):

```php
final readonly class AdminMenuItemSubscriber extends AbstractRouteMenuItemSubscriber
{
    public function __construct()
    {
        parent::__construct('Template Editor', 'fa fa-code', 'admin_template_editor_list', 200);
    }
}
```

`AbstractRouteMenuWithHostsSubscriber` takes the `SiteRegistry` first and turns the item
into a per-host submenu when several sites are configured (used by the page scanner).

### Default weights

| Weight | Item |
| --- | --- |
| 1000 | Pages |
| 900 | Redirections |
| 800 | Media |
| 750 | Cheat sheet |
| 700 | Users (`ROLE_SUPER_ADMIN` only) |
| 670 | Social posts (repurpose) |
| 660 | Quiz results |
| 650 | Snippets |
| 620 | Newsletter |
| 600 | Conversation |
| 580 | Reviews (conversation, when enabled) |
| 500 | *Tools* section |
| 400 | Page scanner |
| 350 | Git status (flat) |
| 300 | Static generator, Activity log (version) |
| 200 | Template editor |

## Replacing the menu

`getItems()` returns `array<int, array{weight: int, item: MenuItemInterface}>`; `setItems()`
replaces it:

```php
public function onMenuItems(AdminMenuItemsEvent $event): void
{
    $items = array_filter($event->getItems(), static fn (array $item): bool => $item['weight'] >= 500);
    $items[] = ['weight' => 100, 'item' => MenuItem::linkToRoute('Custom', 'fa fa-cog', 'custom_route')];

    $event->setItems($items);
}
```
