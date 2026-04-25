<?php

namespace Pushword\Admin\Tests\Menu;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Menu\AdminMenuItemsEvent;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class AbstractRouteMenuWithHostsSubscriberTest extends KernelTestCase
{
    public function testMultipleHostsYieldSubMenuWithAllAndPerHost(): void
    {
        self::bootKernel();

        /** @var SiteRegistry $siteRegistry */
        $siteRegistry = self::getContainer()->get(SiteRegistry::class);
        $hosts = $siteRegistry->getHosts();

        $subscriber = new TestMenuSubscriber($siteRegistry);
        $event = new AdminMenuItemsEvent();
        $subscriber->onMenuItems($event);

        $items = $event->getItems();
        self::assertCount(1, $items);
        self::assertSame(300, $items[0]['weight']);

        $dto = $items[0]['item']->getAsDto();
        self::assertSame('Test Menu', $dto->getLabel());

        if (\count($hosts) <= 1) {
            self::assertEmpty($dto->getSubItems());

            return;
        }

        $subItems = $dto->getSubItems();
        self::assertCount(\count($hosts) + 1, $subItems); // "All" + each host
        self::assertSame('All', $subItems[0]->getLabel());

        foreach ($hosts as $i => $host) {
            self::assertSame($host, $subItems[$i + 1]->getLabel());
        }
    }

    public function testGetSubscribedEventsReturnsAdminMenuEvent(): void
    {
        $events = TestMenuSubscriber::getSubscribedEvents();
        self::assertArrayHasKey(AdminMenuItemsEvent::NAME, $events);
        self::assertSame('onMenuItems', $events[AdminMenuItemsEvent::NAME]);
    }
}
