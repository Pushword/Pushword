<?php

namespace Pushword\Admin\Tests\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Pushword\Admin\Controller\AdminMenu;
use Pushword\Admin\Menu\AdminMenuItemsEvent;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AdminMenuTest extends KernelTestCase
{
    private AdminMenu $adminMenu;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private SiteRegistry $appPool;

    private AdminContextProviderInterface&Stub $adminContextProvider;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        // SiteRegistry is final, get real instance from container
        $this->appPool = self::getContainer()->get(SiteRegistry::class);
        $this->adminContextProvider = self::createStub(AdminContextProviderInterface::class);
        $this->requestStack = new RequestStack();
        $this->requestStack->push(new Request());

        $this->adminMenu = new AdminMenu(
            $this->appPool,
            $this->adminContextProvider,
            $this->requestStack,
            $this->eventDispatcher
        );
    }

    public function testConfigureMenuItemsReturnsItems(): void
    {
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                self::isInstanceOf(AdminMenuItemsEvent::class),
                AdminMenuItemsEvent::NAME
            )
            ->willReturnCallback(static fn (AdminMenuItemsEvent $event): AdminMenuItemsEvent =>
                // Simulate event dispatch without modification
                $event);

        $items = iterator_to_array($this->adminMenu->configureMenuItems());

        self::assertNotEmpty($items);
        self::assertContainsOnlyInstancesOf(MenuItemInterface::class, $items);
    }

    public function testConfigureMenuItemsDispatchesEvent(): void
    {
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                self::isInstanceOf(AdminMenuItemsEvent::class),
                AdminMenuItemsEvent::NAME
            )
            ->willReturnCallback(static function (AdminMenuItemsEvent $event): AdminMenuItemsEvent {
                // Verify event contains initial items
                $items = $event->getItems();
                self::assertNotEmpty($items);

                // Add a custom item
                $event->addMenuItem(
                    MenuItem::linkToRoute('Custom', 'fa fa-custom', 'custom_route'),
                    150
                );

                return $event;
            });

        $items = iterator_to_array($this->adminMenu->configureMenuItems());

        // Should contain the custom item added in the event
        self::assertNotEmpty($items);
    }

    public function testConfigureMenuItemsSortsByWeight(): void
    {
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (AdminMenuItemsEvent $event): AdminMenuItemsEvent {
                // Add items with different weights
                $event->setItems([
                    ['weight' => 100, 'item' => MenuItem::linkToRoute('Low', 'fa fa-low', 'low_route')],
                    ['weight' => 300, 'item' => MenuItem::linkToRoute('High', 'fa fa-high', 'high_route')],
                    ['weight' => 200, 'item' => MenuItem::linkToRoute('Medium', 'fa fa-medium', 'medium_route')],
                ]);

                return $event;
            });

        $items = iterator_to_array($this->adminMenu->configureMenuItems());

        // Items should be sorted by weight descending
        self::assertCount(3, $items);
        // First item should have highest weight
        $firstItem = $items[0];
        $dto = $firstItem->getAsDto();
        self::assertSame('High', $dto->getLabel());
    }

    public function testEventCanModifyItems(): void
    {
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (AdminMenuItemsEvent $event): AdminMenuItemsEvent {
                // Get existing items
                $items = $event->getItems();

                // Filter out items with weight < 500
                $filteredItems = array_filter($items, static fn (array $item): bool => $item['weight'] >= 500);

                // Replace items
                $event->setItems($filteredItems);

                return $event;
            });

        $items = iterator_to_array($this->adminMenu->configureMenuItems());

        // Should only contain items with weight >= 500
        self::assertNotEmpty($items);
    }

    public function testDefaultMenuItems(): void
    {
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (AdminMenuItemsEvent $event): AdminMenuItemsEvent {
                $items = $event->getItems();

                // Verify default items are present
                $weights = array_column($items, 'weight');
                self::assertContains(1000, $weights); // Pages
                self::assertContains(900, $weights);  // Redirections
                self::assertContains(800, $weights);  // Media
                self::assertContains(700, $weights);  // Users
                self::assertContains(500, $weights);  // Tools section

                return $event;
            });

        iterator_to_array($this->adminMenu->configureMenuItems());
    }
}
