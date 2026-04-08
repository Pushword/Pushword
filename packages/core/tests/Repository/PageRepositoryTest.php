<?php

namespace Pushword\Core\Tests\Controller;

use DateTime;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
class PageRepositoryTest extends KernelTestCase
{
    public function testPageRepo(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $pageRepo = $em->getRepository(Page::class);

        $pages = $pageRepo->getIndexablePagesQuery('', 'en', 2)->getQuery()->getResult();

        self::assertIsIterable($pages);
        self::assertCount(2, $pages); // depend on AppFixtures

        $pages = $pageRepo->getPublishedPages(
            '',
            [['key' => 'slug', 'operator' => '=', 'value' => 'homepage']],
            ['key' => 'publishedAt', 'direction' => 'DESC'],
            1
        );

        self::assertSame($pages[0]->getSlug(), 'homepage');
    }

    public function testNumericSlugDoesNotFallbackToId(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $pageRepo = $em->getRepository(Page::class);

        // Get an existing page to know a valid ID
        $existingPage = $pageRepo->findOneBy(['slug' => 'homepage']);
        self::assertNotNull($existingPage);
        $existingId = (string) $existingPage->id;

        // Requesting a numeric string that matches an existing page's ID but not any slug
        // should return null, not the page with that ID
        $result = $pageRepo->getPage($existingId, $existingPage->host);
        self::assertNull($result, 'getPage() with numeric slug should not fallback to matching by ID');
    }

    public function testFindNewlyPublishedSince(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $pageRepo = $em->getRepository(Page::class);

        // Pages published before 1 hour ago should not appear
        $pages = $pageRepo->findNewlyPublishedSince(new DateTime('+1 hour'));
        self::assertCount(0, $pages);

        // All currently published pages should appear if since is far in the past
        $allPublished = $pageRepo->findNewlyPublishedSince(new DateTime('2000-01-01'));
        self::assertGreaterThan(0, \count($allPublished));
    }
}
