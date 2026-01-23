<?php

namespace Pushword\Core\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\LinkCollectorService;

class LinkCollectorServiceTest extends TestCase
{
    public function testRegisterSlug(): void
    {
        $service = new LinkCollectorService();
        $service->registerSlug('my-page');

        self::assertTrue($service->isSlugRegistered('my-page'));
        self::assertFalse($service->isSlugRegistered('other-page'));
    }

    public function testRegisterPage(): void
    {
        $service = new LinkCollectorService();
        $page = new Page();
        $page->setSlug('test-slug');

        $service->register($page);

        self::assertTrue($service->isSlugRegistered('test-slug'));
    }

    public function testExcludeRegistered(): void
    {
        $service = new LinkCollectorService();
        $service->registerSlug('page-1');
        $service->registerSlug('page-3');

        $page1 = new Page()->setSlug('page-1');
        $page2 = new Page()->setSlug('page-2');
        $page3 = new Page()->setSlug('page-3');

        $result = $service->excludeRegistered([$page1, $page2, $page3]);

        self::assertCount(1, $result);
        self::assertSame('page-2', $result[0]->getSlug());
    }

    public function testReset(): void
    {
        $service = new LinkCollectorService();
        $service->registerSlug('my-page');

        self::assertTrue($service->isSlugRegistered('my-page'));

        $service->reset();

        self::assertFalse($service->isSlugRegistered('my-page'));
    }

    public function testGetRegisteredSlugs(): void
    {
        $service = new LinkCollectorService();
        $service->registerSlug('page-a');
        $service->registerSlug('page-b');

        $slugs = $service->getRegisteredSlugs();

        self::assertArrayHasKey('page-a', $slugs);
        self::assertArrayHasKey('page-b', $slugs);
    }

    public function testDuplicateRegistration(): void
    {
        $service = new LinkCollectorService();
        $service->registerSlug('my-page');
        $service->registerSlug('my-page');

        $slugs = $service->getRegisteredSlugs();

        self::assertCount(1, $slugs);
        self::assertTrue($service->isSlugRegistered('my-page'));
    }
}
