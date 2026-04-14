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

    public function testResolvePageUriTargetWarmsLightCache(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $pageRepo = $em->getRepository(Page::class);

        $homepage = $pageRepo->findOneBy(['slug' => 'homepage']);
        self::assertNotNull($homepage);
        $host = $homepage->host;

        $em->clear();
        self::assertFalse($pageRepo->isHostLightWarmed($host));
        self::assertFalse($pageRepo->isHostWarmed($host), 'full-entity cache must stay cold');

        $target = $pageRepo->resolvePageUriTarget('homepage', $host);
        self::assertNotNull($target);
        self::assertSame('homepage', $target['slug']);
        self::assertSame($host, $target['host']);
        self::assertNull($target['redirectUrl']);
        self::assertTrue($pageRepo->isHostLightWarmed($host));
        self::assertFalse($pageRepo->isHostWarmed($host), 'light path must not touch full-entity cache');

        // Unknown slug on a warmed host returns null without extra queries.
        self::assertNull($pageRepo->resolvePageUriTarget('this-slug-does-not-exist', $host));

        // EM clear resets the light cache; next call re-warms.
        $em->clear();
        self::assertFalse($pageRepo->isHostLightWarmed($host));
        self::assertNotNull($pageRepo->resolvePageUriTarget('homepage', $host));
        self::assertTrue($pageRepo->isHostLightWarmed($host));

        // Empty host sentinel: returns null without warming.
        $em->clear();
        self::assertNull($pageRepo->resolvePageUriTarget('homepage', ''));
        self::assertFalse($pageRepo->isHostLightWarmed(''));
    }

    public function testResolvePageUriTargetReturnsRedirectUrl(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $pageRepo = $em->getRepository(Page::class);

        $redirectPage = $pageRepo->findOneBy(['slug' => 'pushword']);
        self::assertNotNull($redirectPage, 'redirection fixture (slug=pushword) missing');

        $em->clear();
        $target = $pageRepo->resolvePageUriTarget('pushword', $redirectPage->host);

        self::assertNotNull($target);
        self::assertSame('https://pushword.piedweb.com', $target['redirectUrl']);
        self::assertSame(301, $target['redirectCode']);
    }

    public function testGetPageBySlugDoesNotAutoWarmFullCache(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $pageRepo = $em->getRepository(Page::class);

        $homepage = $pageRepo->findOneBy(['slug' => 'homepage']);
        self::assertNotNull($homepage);
        $host = $homepage->host;

        $em->clear();
        self::assertFalse($pageRepo->isHostWarmed($host));

        $page = $pageRepo->getPageBySlug('homepage', $host);
        self::assertNotNull($page);
        self::assertFalse($pageRepo->isHostWarmed($host), 'getPageBySlug must not auto-warm the full-entity cache');

        // Second call on the same slug is served from per-slug cache.
        self::assertSame($page, $pageRepo->getPageBySlug('homepage', $host));
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
