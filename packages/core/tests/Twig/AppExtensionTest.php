<?php

namespace Pushword\Core\Tests\Twig;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\AppExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class AppExtensionTest extends KernelTestCase
{
    private function makePage(string $slug, string $name, ?Page $parent = null): Page
    {
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->setSlug($slug);
        $page->setName($name);
        $page->setParentPage($parent);

        return $page;
    }

    /** @return array<array-key, mixed> */
    private function generate(Page $page): array
    {
        self::bootKernel();
        self::getContainer()->get(SiteRegistry::class)->switchSite('localhost.dev');
        $ext = self::getContainer()->get(AppExtension::class);

        $jsonLd = json_decode($ext->generateBreadcrumbJsonLd($page), true);
        self::assertIsArray($jsonLd);
        self::assertIsArray($jsonLd['itemListElement']);

        return $jsonLd['itemListElement'];
    }

    public function testBreadcrumbPositionsAscendFromRootToCurrentPage(): void
    {
        $root = $this->makePage('root', 'Root');
        $child = $this->makePage('child', 'Child', $root);
        $leaf = $this->makePage('leaf', 'Leaf', $child);

        $items = $this->generate($leaf);

        self::assertCount(3, $items);
        self::assertSame(['Root', 'Child', 'Leaf'], array_column($items, 'name'));
        self::assertSame([1, 2, 3], array_column($items, 'position'));
    }

    public function testBreadcrumbNameStripsHtml(): void
    {
        $page = $this->makePage('page', '<strong>Bold</strong> Title');

        $items = $this->generate($page);

        self::assertSame(['Bold Title'], array_column($items, 'name'));
    }
}
