<?php

namespace Pushword\Core\Tests\Controller;

use DateTime;
use Doctrine\ORM\PersistentCollection;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class PageRepositoryTest extends KernelTestCase
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

        self::assertSame('homepage', $pages[0]->getSlug());
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

    public function testHasSlugWarmsLightCache(): void
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

        self::assertTrue($pageRepo->hasSlug('homepage', $host));
        self::assertNull($pageRepo->getRedirectFor('homepage', $host));
        self::assertTrue($pageRepo->isHostLightWarmed($host));
        self::assertFalse($pageRepo->isHostWarmed($host), 'light path must not touch full-entity cache');

        // Unknown slug on a warmed host returns false without extra queries.
        self::assertFalse($pageRepo->hasSlug('this-slug-does-not-exist', $host));

        // EM clear resets the light cache; next call re-warms.
        $em->clear();
        self::assertFalse($pageRepo->isHostLightWarmed($host));
        self::assertTrue($pageRepo->hasSlug('homepage', $host));
        self::assertTrue($pageRepo->isHostLightWarmed($host));

        // Empty host sentinel: returns false without warming.
        $em->clear();
        self::assertFalse($pageRepo->hasSlug('homepage', ''));
        self::assertNull($pageRepo->getRedirectFor('homepage', ''));
        self::assertFalse($pageRepo->isHostLightWarmed(''));
    }

    public function testGetRedirectForReturnsRedirectTarget(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $pageRepo = $em->getRepository(Page::class);

        $redirectPage = $pageRepo->findOneBy(['slug' => 'pushword']);
        self::assertNotNull($redirectPage, 'redirection fixture (slug=pushword) missing');

        $em->clear();
        $redirect = $pageRepo->getRedirectFor('pushword', $redirectPage->host);

        self::assertNotNull($redirect);
        self::assertSame('https://pushword.piedweb.com', $redirect['url']);
        self::assertSame(301, $redirect['code']);
    }

    public function testGetRedirectForResolvesRedirectFromAndRespectsShadowing(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $pageRepo = $em->getRepository(Page::class);

        $homepage = $pageRepo->findOneBy(['slug' => 'homepage']);
        self::assertNotNull($homepage);
        $host = $homepage->host;

        $destination = new Page();
        $destination->setH1('RFM Repo Dest');
        $destination->setSlug('rfm-repo-dest');
        $destination->host = $host;
        $destination->locale = 'en';
        $destination->createdAt = new DateTime();
        $destination->updatedAt = new DateTime();
        $destination->setMainContent('content');
        // 'homepage' collides with a real page → must be shadowed by it.
        $destination->setRedirectFrom(['rfm-repo-old' => 308, 'homepage' => 301]);

        $em->persist($destination);
        $em->flush();
        $em->clear();

        try {
            $redirect = $pageRepo->getRedirectFor('rfm-repo-old', $host);
            self::assertNotNull($redirect);
            self::assertSame('/rfm-repo-dest', $redirect['url']);
            self::assertSame(308, $redirect['code']);
            self::assertTrue($pageRepo->hasSlug('rfm-repo-old', $host));

            // resolveRedirectFromSlug maps the old path to the destination slug
            // (used to rewrite internal links at render).
            self::assertSame('rfm-repo-dest', $pageRepo->resolveRedirectFromSlug('rfm-repo-old', $host));
            // A live page slug is not a redirectFrom entry.
            self::assertNull($pageRepo->resolveRedirectFromSlug('rfm-repo-dest', $host));

            // The real homepage page wins: its redirectFrom claim is ignored.
            self::assertNull($pageRepo->getRedirectFor('homepage', $host));
            self::assertNull($pageRepo->resolveRedirectFromSlug('homepage', $host));
        } finally {
            $em->clear();
            $toRemove = $pageRepo->findOneBy(['slug' => 'rfm-repo-dest', 'host' => $host]);
            if (null !== $toRemove) {
                $em->remove($toRemove);
                $em->flush();
            }
        }
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

    public function testWarmupSlugCacheForResolvesHitsAndMisses(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $pageRepo = $em->getRepository(Page::class);

        $homepage = $pageRepo->findOneBy(['slug' => 'homepage']);
        self::assertNotNull($homepage);
        $host = $homepage->host;

        $em->clear();

        // Empty input is a no-op (no query, no error).
        $pageRepo->warmupSlugCacheFor([], $host);

        $pageRepo->warmupSlugCacheFor(['homepage', 'no-such-page'], $host);

        // Batch warmup must not flip the full-host warmed flag — it only seeds
        // the per-slug cache for the requested slugs.
        self::assertFalse($pageRepo->isHostWarmed($host), 'batch warmup must not mark the whole host warmed');

        // A found slug resolves to the real page from the warm cache.
        $page = $pageRepo->getPageBySlug('homepage', $host);
        self::assertNotNull($page);
        self::assertSame('homepage', $page->getSlug());

        // A missing slug resolves to null and stays null (negative cache).
        self::assertNull($pageRepo->getPageBySlug('no-such-page', $host));
        self::assertNull($pageRepo->getPageBySlug('no-such-page', $host));
    }

    public function testFindWithMainImageByIdsPreservesOrderAndDropsUnknownIds(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $pageRepo = $em->getRepository(Page::class);

        $pages = $pageRepo->findByHost('');
        self::assertGreaterThanOrEqual(2, \count($pages), 'fixtures must provide at least two pages');

        $first = $pages[0];
        $second = $pages[1];
        self::assertNotNull($first->id);
        self::assertNotNull($second->id);

        // Reversed input order + an id that does not exist.
        $result = $pageRepo->findWithMainImageByIds([$second->id, 999999, $first->id]);

        self::assertCount(2, $result, 'unknown ids are dropped');
        self::assertSame($second->id, $result[0]->id, 'order follows the supplied ids, not SQL IN');
        self::assertSame($first->id, $result[1]->id);

        // Empty input short-circuits without a query.
        self::assertSame([], $pageRepo->findWithMainImageByIds([]));
    }

    public function testFindWithMainImageByIdsEagerLoadsMainImage(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $pageRepo = $em->getRepository(Page::class);

        // The homepage fixture carries a mainImage (AppFixtures).
        $homepage = $pageRepo->findOneBy(['slug' => 'homepage']);
        self::assertNotNull($homepage);
        self::assertNotNull($homepage->mainImage, 'homepage fixture must carry a mainImage');
        $homepageId = $homepage->id;
        self::assertNotNull($homepageId);

        $em->clear();

        $result = $pageRepo->findWithMainImageByIds([$homepageId]);
        self::assertCount(1, $result);

        $mainImage = $result[0]->mainImage;
        self::assertNotNull($mainImage);
        // Eager-joined: the media is fully hydrated, not an uninitialized proxy, so
        // reading it issues no follow-up SELECT (the N+1 the helper exists to prevent).
        self::assertFalse(
            $em->getUnitOfWork()->isUninitializedObject($mainImage),
            'mainImage must be eager-loaded, not a lazy proxy',
        );
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

    public function testWhereFilterProducesDeterministicDql(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $pageRepo = $em->getRepository(Page::class);

        $where = [['key' => 'slug', 'operator' => 'LIKE', 'value' => 'home%'], 'OR', ['key' => 'title', 'operator' => '=', 'value' => 'x']];

        // The parameter names are part of the DQL string: if they are not
        // deterministic, no generated query can ever hit Doctrine's query cache
        // across processes, and the cache pool grows on every run.
        self::assertSame(
            $pageRepo->getPublishedPageQueryBuilder('', $where)->getDQL(),
            $pageRepo->getPublishedPageQueryBuilder('', $where)->getDQL(),
        );
    }

    /**
     * Several conditions — including a param-less IS NOT NULL between two valued
     * ones — must each bind their own value: a parameter-name collision would
     * silently overwrite the first value with the second.
     */
    public function testWhereFilterBindsEachConditionSeparately(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $pageRepo = $em->getRepository(Page::class);

        $pages = $pageRepo->getPublishedPages('', [
            ['key' => 'slug', 'operator' => 'LIKE', 'value' => 'home%'],
            ['key' => 'parentPage', 'operator' => 'IS', 'value' => null],
            ['key' => 'slug', 'operator' => '!=', 'value' => 'homepage-draft'],
        ]);

        self::assertNotSame([], $pages);
        foreach ($pages as $page) {
            self::assertStringStartsWith('home', $page->getSlug());
            self::assertNotSame('homepage-draft', $page->getSlug());
        }
    }

    public function testPreloadTranslationsInitializesEveryCollectionAtOnce(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $pageRepo = $em->getRepository(Page::class);
        $em->clear();

        $pages = $pageRepo->getPublishedPages('');
        $homepage = null;
        foreach ($pages as $page) {
            if ('homepage' === $page->getSlug()) {
                $homepage = $page;
            }
        }

        self::assertInstanceOf(Page::class, $homepage);
        $fresh = $homepage->getTranslations();
        self::assertInstanceOf(PersistentCollection::class, $fresh);
        self::assertFalse($fresh->isInitialized(), 'A fresh load must not have initialized the collection yet.');

        $pageRepo->preloadTranslations($pages);

        foreach ($pages as $page) {
            $collection = $page->getTranslations();
            self::assertInstanceOf(PersistentCollection::class, $collection);
            self::assertTrue($collection->isInitialized(), 'Not preloaded: '.$page->getSlug());
        }

        // The preloaded rows are the real data (AppFixtures links homepage → fr)…
        $locales = array_map(static fn (Page $page): string => $page->locale, $homepage->getTranslations()->toArray());
        self::assertContains('fr', $locales);

        // …and stay initialized across the EM clear the build loop does every few pages.
        $em->clear();
        $afterClear = $homepage->getTranslations();
        self::assertInstanceOf(PersistentCollection::class, $afterClear);
        self::assertTrue($afterClear->isInitialized());
    }
}
