<?php

namespace Pushword\Admin\Tests\Menu;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use PHPUnit\Framework\TestCase;
use Pushword\Admin\Menu\AdminMenuItemsEvent;
use Pushword\Core\Entity\Page;

class AdminMenuItemsEventTest extends TestCase
{
    public function testAddMenuItem(): void
    {
        $event = new AdminMenuItemsEvent();
        $menuItem = MenuItem::linkToCrud('Test', 'fa fa-test', Page::class);

        $event->addMenuItem($menuItem, 100);

        $items = $event->getItems();
        self::assertCount(1, $items);
        self::assertSame(100, $items[0]['weight']);
        self::assertSame($menuItem, $items[0]['item']);
    }

    public function testAddMultipleMenuItems(): void
    {
        $event = new AdminMenuItemsEvent();
        $item1 = MenuItem::linkToCrud('Item 1', 'fa fa-1', Page::class);
        $item2 = MenuItem::linkToCrud('Item 2', 'fa fa-2', Page::class);

        $event->addMenuItem($item1, 100);
        $event->addMenuItem($item2, 200);

        $items = $event->getItems();
        self::assertCount(2, $items);
        self::assertSame(100, $items[0]['weight']);
        self::assertSame(200, $items[1]['weight']);
    }

    public function testSetItems(): void
    {
        $event = new AdminMenuItemsEvent();
        $item1 = MenuItem::linkToCrud('Item 1', 'fa fa-1', Page::class);
        $item2 = MenuItem::linkToCrud('Item 2', 'fa fa-2', Page::class);

        $event->addMenuItem($item1, 100);

        $newItems = [
            ['weight' => 300, 'item' => $item2],
        ];

        $event->setItems($newItems);

        $items = $event->getItems();
        self::assertCount(1, $items);
        self::assertSame(300, $items[0]['weight']);
        self::assertSame($item2, $items[0]['item']);
    }

    public function testDefaultWeight(): void
    {
        $event = new AdminMenuItemsEvent();
        $menuItem = MenuItem::linkToCrud('Test', 'fa fa-test', Page::class);

        $event->addMenuItem($menuItem);

        $items = $event->getItems();
        self::assertCount(1, $items);
        self::assertSame(0, $items[0]['weight']);
    }
}
