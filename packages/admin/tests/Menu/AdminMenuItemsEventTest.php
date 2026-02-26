<?php

namespace Pushword\Admin\Tests\Menu;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use PHPUnit\Framework\TestCase;
use Pushword\Admin\Controller\PageCrudController;
use Pushword\Admin\Menu\AdminMenuItemsEvent;

class AdminMenuItemsEventTest extends TestCase
{
    public function testAddMenuItem(): void
    {
        $event = new AdminMenuItemsEvent();
        $menuItem = MenuItem::linkTo(PageCrudController::class, 'Test', 'fa fa-test');

        $event->addMenuItem($menuItem, 100);

        $items = $event->getItems();
        self::assertCount(1, $items);
        self::assertSame(100, $items[0]['weight']);
        self::assertSame($menuItem, $items[0]['item']);
    }

    public function testAddMultipleMenuItems(): void
    {
        $event = new AdminMenuItemsEvent();
        $item1 = MenuItem::linkTo(PageCrudController::class, 'Item 1', 'fa fa-1');
        $item2 = MenuItem::linkTo(PageCrudController::class, 'Item 2', 'fa fa-2');

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
        $item1 = MenuItem::linkTo(PageCrudController::class, 'Item 1', 'fa fa-1');
        $item2 = MenuItem::linkTo(PageCrudController::class, 'Item 2', 'fa fa-2');

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
        $menuItem = MenuItem::linkTo(PageCrudController::class, 'Test', 'fa fa-test');

        $event->addMenuItem($menuItem);

        $items = $event->getItems();
        self::assertCount(1, $items);
        self::assertSame(0, $items[0]['weight']);
    }
}
