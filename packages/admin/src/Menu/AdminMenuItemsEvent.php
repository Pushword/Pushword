<?php

namespace Pushword\Admin\Menu;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched to allow bundles and end users to customize admin menu items.
 */
class AdminMenuItemsEvent extends Event
{
    public const NAME = 'pushword.admin.menu_items';

    /**
     * @var array<int, array{weight: int, item: MenuItemInterface}>
     */
    private array $items = [];

    /**
     * Add a menu item with its weight.
     * Higher weight items appear first in the menu.
     *
     * @param MenuItemInterface $item   The menu item to add
     * @param int               $weight Weight of the item (higher = appears first)
     */
    public function addMenuItem(MenuItemInterface $item, int $weight = 0): void
    {
        $this->items[] = [
            'weight' => $weight,
            'item' => $item,
        ];
    }

    /**
     * @return array<int, array{weight: int, item: MenuItemInterface}>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param array<int, array{weight: int, item: MenuItemInterface}> $items
     */
    public function setItems(array $items): void
    {
        $this->items = $items;
    }
}
