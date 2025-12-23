---
title: 'Configure the Admin Menu'
h1: 'Configure the Admin Menu'
id: 30
publishedAt: '2025-12-21 21:55'
parentPage: extension/admin
toc: true
---

The admin menu in Pushword is highly customizable through Symfony events. You can add new items, modify existing ones, or completely replace the menu structure.

## How it works

The `AdminMenu` class dispatches the `AdminItemsEvent` event (`pushword.admin.menu_items`) during menu configuration. This event allows any bundle or custom code to interact with the menu items before they are displayed.

Each menu item has a **weight** that determines its position in the menu. Items with higher weights appear first in the menu.

## Adding an item

To add a new item to the admin menu, create an `EventSubscriber` that listens to the `AdminItemsEvent`:

```php
<?php

namespace App\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Pushword\Admin\Menu\AdminItemsEvent;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[AutoconfigureTag('kernel.event_subscriber')]
final readonly class AdminMenuSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AdminItemsEvent::NAME => 'onMenuItems',
        ];
    }

    public function onMenuItems(AdminItemsEvent $event): void
    {
        $event->addMenuItem(
            MenuItem::linkToRoute('My Custom Page', 'fa fa-star', 'my_custom_route'),
            500 // weight (higher = appears first)
        );
    }
}
```

### Available MenuItem methods

You can create different types of menu items:

**Link to a route:**

```php
MenuItem::linkToRoute('Label', 'fa fa-icon', 'route_name')
```

**Link to a CRUD controller:**

```php
MenuItem::linkToCrud('Label', 'fa fa-icon', EntityClass::class)
    ->setController(MyCrudController::class)
```

**Submenu:**

```php
MenuItem::subMenu('Label', 'fa fa-icon')
    ->setSubItems([
        MenuItem::linkToRoute('Sub Item', 'fa fa-icon', 'route_name'),
    ])
```

**Section (separator):**

```php
MenuItem::section('Section Title')
```

### Weight values

Default weights used by Pushword core:

- **1000**: Content (Pages)
- **900**: Redirections
- **800**: Media
- **700**: Users
- **600**: Conversation (if enabled)
- **500**: Tools section
- **400**: Page Scanner (if enabled)
- **300**: Static Generator (if enabled)
- **200**: Template Editor (if enabled)

Use values between these to position your items correctly.

## Editing the full menu

If you need to completely customize the menu structure, you can use `setItems()` to replace all items:

```php
<?php

namespace App\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Pushword\Admin\Menu\AdminItemsEvent;
use Pushword\Core\Entity\Page;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[AutoconfigureTag('kernel.event_subscriber')]
final readonly class AdminMenuSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AdminItemsEvent::NAME => 'onMenuItems',
        ];
    }

    public function onMenuItems(AdminItemsEvent $event): void
    {
        // Get existing items
        $items = $event->getItems();

        // Filter out items you don't want
        $items = array_filter($items, function (array $item) {
            // Remove items with weight less than 500
            return $item['weight'] >= 500;
        });

        // Add your custom items
        $items[] = [
            'weight' => 100,
            'item' => MenuItem::linkToRoute('Custom', 'fa fa-cog', 'custom_route'),
        ];

        // Replace all items
        $event->setItems($items);
    }
}
```

## Examples from Pushword bundles

### Conversation bundle

The conversation bundle adds its menu item when the `Message` entity exists:

```php
<?php

namespace Pushword\Conversation\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Pushword\Admin\Menu\AdminItemsEvent;
use Pushword\Conversation\Entity\Message;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[AutoconfigureTag('kernel.event_subscriber')]
final readonly class MenuItemsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AdminItemsEvent::NAME => 'onMenuItems',
        ];
    }

    public function onMenuItems(AdminItemsEvent $event): void
    {
        if (class_exists(Message::class)) {
            $event->addMenuItem(
                MenuItem::linkToCrud('admin.label.conversation', 'fa fa-comments', Message::class),
                600
            );
        }
    }
}
```

### Page Scanner bundle

```php
<?php

namespace Pushword\PageScanner\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Pushword\Admin\Menu\AdminItemsEvent;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[AutoconfigureTag('kernel.event_subscriber')]
final readonly class MenuItemsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AdminItemsEvent::NAME => 'onMenuItems',
        ];
    }

    public function onMenuItems(AdminItemsEvent $event): void
    {
        $event->addMenuItem(
            MenuItem::linkToRoute('admin.label.check_content', 'fa fa-check-circle', 'admin_page_scanner'),
            400
        );
    }
}
```

## Tips

- Use the `#[AutoconfigureTag('kernel.event_subscriber')]` attribute to automatically register your subscriber
- Higher weight values appear first in the menu
- You can add multiple items in the same subscriber
- Items are automatically sorted by weight (descending) before being displayed
- Use sections (weight 500) to group related tools together
