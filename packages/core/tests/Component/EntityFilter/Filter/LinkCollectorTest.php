<?php

namespace Pushword\Core\Tests\Component\EntityFilter\Filter;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Component\EntityFilter\Filter\LinkCollector;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Component\EntityFilter\ManagerPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\LinkCollectorService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
class LinkCollectorTest extends KernelTestCase
{
    public function testCollectMarkdownLinks(): void
    {
        $service = new LinkCollectorService();
        $filter = new LinkCollector($service);
        $page = $this->createPage();
        $manager = $this->getManager($page);

        $content = 'Check [our tours](/alps/hiking) and [more](/beach-trips)';
        $filter->apply($content, $page, $manager);

        self::assertTrue($service->isSlugRegistered('alps/hiking'));
        self::assertTrue($service->isSlugRegistered('beach-trips'));
    }

    public function testCollectHtmlHrefs(): void
    {
        $service = new LinkCollectorService();
        $filter = new LinkCollector($service);
        $page = $this->createPage();
        $manager = $this->getManager($page);

        $content = '<a href="/products/item-1">Item</a> and <a href=\'/services\'>Services</a>';
        $filter->apply($content, $page, $manager);

        self::assertTrue($service->isSlugRegistered('products/item-1'));
        self::assertTrue($service->isSlugRegistered('services'));
    }

    public function testIgnoreExternalLinks(): void
    {
        $service = new LinkCollectorService();
        $filter = new LinkCollector($service);
        $page = $this->createPage();
        $manager = $this->getManager($page);

        $content = '[External](https://example.com) and <a href="https://other.com">Other</a>';
        $filter->apply($content, $page, $manager);

        self::assertEmpty($service->getRegisteredSlugs());
    }

    public function testHandleAnchorsAndQueryStrings(): void
    {
        $service = new LinkCollectorService();
        $filter = new LinkCollector($service);
        $page = $this->createPage();
        $manager = $this->getManager($page);

        $content = '[Link](/page#section) and <a href="/other?param=1">Other</a>';
        $filter->apply($content, $page, $manager);

        self::assertTrue($service->isSlugRegistered('page'));
        self::assertTrue($service->isSlugRegistered('other'));
    }

    public function testContentPassedThrough(): void
    {
        $service = new LinkCollectorService();
        $filter = new LinkCollector($service);
        $page = $this->createPage();
        $manager = $this->getManager($page);

        $content = 'Original content [link](/page)';
        $result = $filter->apply($content, $page, $manager);

        self::assertSame($content, $result);
    }

    public function testCollectMarkdownLinksWithClosingParenthesis(): void
    {
        $service = new LinkCollectorService();
        $filter = new LinkCollector($service);
        $page = $this->createPage();
        $manager = $this->getManager($page);

        $content = '[Simple link](/simple-page)';
        $filter->apply($content, $page, $manager);

        self::assertTrue($service->isSlugRegistered('simple-page'));
    }

    public function testCollectLinksWithUnderscores(): void
    {
        $service = new LinkCollectorService();
        $filter = new LinkCollector($service);
        $page = $this->createPage();
        $manager = $this->getManager($page);

        $content = '[Link](/my_page_slug) and <a href="/another_page">Test</a>';
        $filter->apply($content, $page, $manager);

        self::assertTrue($service->isSlugRegistered('my_page_slug'));
        self::assertTrue($service->isSlugRegistered('another_page'));
    }

    public function testIgnoreRelativeLinks(): void
    {
        $service = new LinkCollectorService();
        $filter = new LinkCollector($service);
        $page = $this->createPage();
        $manager = $this->getManager($page);

        $content = '[Relative](relative-page) and <a href="another-relative">Test</a>';
        $filter->apply($content, $page, $manager);

        self::assertEmpty($service->getRegisteredSlugs());
    }

    public function testTrailingSlashesAreNormalized(): void
    {
        $service = new LinkCollectorService();
        $filter = new LinkCollector($service);
        $page = $this->createPage();
        $manager = $this->getManager($page);

        $content = '[Link](/page-with-slash/)';
        $filter->apply($content, $page, $manager);

        self::assertTrue($service->isSlugRegistered('page-with-slash'));
        self::assertFalse($service->isSlugRegistered('page-with-slash/'));
    }

    private function createPage(): Page
    {
        $page = new Page();
        $page->setSlug('test-page');
        $page->locale = 'en';
        $page->host = 'localhost';

        return $page;
    }

    private function getManager(Page $page): Manager
    {
        self::bootKernel();

        return self::getContainer()->get(ManagerPool::class)->getManager($page);
    }
}
