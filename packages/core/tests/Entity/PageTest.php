<?php

namespace Pushword\Core\Tests\Entity;

use DateTime;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;

final class PageTest extends TestCase
{
    public function testBasics(): void
    {
        $page = new Page();
        self::assertEmpty($page->getTitle());

        $page->setTitle('hello');
        self::assertSame('hello', $page->getTitle());

        $page->setSlug('hello you');
        self::assertSame('hello-you', $page->getSlug());
    }

    public function testHoldPublication(): void
    {
        $page = new Page();
        self::assertFalse($page->isHoldPublication());

        $page->setHoldPublication(true);
        self::assertTrue($page->isHoldPublication());

        $page->setHoldPublication(false);
        self::assertFalse($page->isHoldPublication());
        self::assertNull($page->getHoldPublicationAt());
    }

    public function testHoldPublicationKeepsExplicitTimestamp(): void
    {
        $page = new Page();
        $explicit = new DateTime('2026-01-01 00:00');
        $page->setHoldPublicationAt($explicit);
        self::assertTrue($page->isHoldPublication());

        // Holding again must not overwrite an existing timestamp.
        $page->setHoldPublication(true);
        self::assertSame($explicit, $page->getHoldPublicationAt());
    }

    public function testCloneResetsHoldPublication(): void
    {
        $page = new Page();
        $page->setHoldPublication(true);

        self::assertFalse((clone $page)->isHoldPublication());
    }

    public function testAPublishedPageIsIndexable(): void
    {
        self::assertTrue(new Page()->isIndexable());
    }

    public function testAnUnpublishedPageIsNotIndexable(): void
    {
        $page = new Page();
        $page->publishedAt = new DateTime('tomorrow');

        self::assertFalse($page->isIndexable());
    }

    public function testARedirectionIsNotIndexable(): void
    {
        $page = new Page();
        $page->setMainContent('Location: https://example.tld');

        self::assertFalse($page->isIndexable());
    }

    public function testANoindexPageIsNotIndexable(): void
    {
        $page = new Page();
        $page->setMetaRobots('noindex');
        self::assertFalse($page->isIndexable());

        // The rule matches the SQL twin's `LOWER(...) NOT LIKE '%noindex%'`: noindex
        // never travels alone, it comes paired with a follow directive.
        $page->setMetaRobots('noindex, follow');
        self::assertFalse($page->isIndexable());

        // Written by hand in a flat file or over the API, nothing lowercases it.
        $page->setMetaRobots('NoIndex, NoArchive');
        self::assertFalse($page->isIndexable());

        // `none` is the robots shorthand for `noindex, nofollow`.
        $page->setMetaRobots('none');
        self::assertFalse($page->isIndexable());

        $page->setMetaRobots('index, follow');
        self::assertTrue($page->isIndexable());

        // noimageindex only bans the images: the substring does not line up.
        $page->setMetaRobots('noimageindex');
        self::assertTrue($page->isIndexable());

        // Nor does any other directive carry `none` inside it.
        $page->setMetaRobots('nosnippet, notranslate, noarchive');
        self::assertTrue($page->isIndexable());
    }

    public function testRedirectFromNormalizesMapListAndRows(): void
    {
        $page = new Page();
        self::assertSame([], $page->getRedirectFromMap());

        // Map form, with leading slash and out-of-order keys → normalized + ksorted.
        $page->setRedirectFrom(['/old/two' => 302, 'old-one' => 301]);
        self::assertSame(['old-one' => 301, 'old/two' => 302], $page->getRedirectFromMap());

        // Jekyll-style bare list → implicit 301.
        $page->setRedirectFrom(['a-slug', 'b-slug']);
        self::assertSame(['a-slug' => 301, 'b-slug' => 301], $page->getRedirectFromMap());

        // Row form (admin collection) → map, invalid code falls back to 301.
        $page->setRedirectFrom([['from' => 'foo', 'code' => 307], ['from' => 'bar', 'code' => 999]]);
        self::assertSame(['bar' => 301, 'foo' => 307], $page->getRedirectFromMap());

        // Rows view round-trips.
        self::assertSame(
            [['from' => 'bar', 'code' => 301], ['from' => 'foo', 'code' => 307]],
            $page->getRedirectFromRows(),
        );
    }

    public function testAddRedirectFrom(): void
    {
        $page = new Page();
        $page->addRedirectFrom('first');
        $page->addRedirectFrom('second', 308);

        self::assertSame(['first' => 301, 'second' => 308], $page->getRedirectFromMap());
    }

    public function testMainImageInheritance(): void
    {
        $media = self::createStub(Media::class);

        $parent = new Page(false);
        $parent->mainImage = $media;

        $child = new Page(false);
        $child->extendedPage = $parent;

        self::assertSame($media, $child->getMainImage());
        self::assertNull($child->mainImage, 'Raw property should be null');
    }

    public function testMainImageOwnValueTakesPrecedence(): void
    {
        $parentMedia = self::createStub(Media::class);
        $childMedia = self::createStub(Media::class);

        $parent = new Page(false);
        $parent->mainImage = $parentMedia;

        $child = new Page(false);
        $child->extendedPage = $parent;
        $child->mainImage = $childMedia;

        self::assertSame($childMedia, $child->getMainImage());
    }

    public function testSetMainImageClearsNotFoundMarker(): void
    {
        $media = self::createStub(Media::class);
        $media->method('getWidth')->willReturn(1200);

        $page = new Page(false);
        $page->setCustomProperty('mainImageNotFound', 'heal-me.png');

        $page->setMainImage($media);

        self::assertSame($media, $page->mainImage);
        self::assertFalse($page->hasCustomProperty('mainImageNotFound'), 'Setting a real image clears the broken-reference marker');
    }

    public function testSetMainImageNullKeepsNotFoundMarker(): void
    {
        $page = new Page(false);
        $page->setCustomProperty('mainImageNotFound', 'heal-me.png');

        $page->setMainImage(null);

        self::assertSame('heal-me.png', $page->getCustomProperty('mainImageNotFound'), 'Clearing the image must not erase a pending broken-reference marker');
    }

    public function testTemplateInheritance(): void
    {
        $parent = new Page(false);
        $parent->template = 'parent_template.html.twig';

        $child = new Page(false);
        $child->extendedPage = $parent;

        self::assertSame('parent_template.html.twig', $child->getTemplate());
        self::assertNull($child->template, 'Raw property should be null');
    }

    public function testTemplateOwnValueTakesPrecedence(): void
    {
        $parent = new Page(false);
        $parent->template = 'parent_template.html.twig';

        $child = new Page(false);
        $child->extendedPage = $parent;
        $child->template = 'child_template.html.twig';

        self::assertSame('child_template.html.twig', $child->getTemplate());
    }

    public function testCustomPropertyInheritance(): void
    {
        $parent = new Page(false);
        $parent->setCustomProperty('mainImageFormat', 'wide');

        $child = new Page(false);
        $child->extendedPage = $parent;

        self::assertSame('wide', $child->getCustomProperty('mainImageFormat'));
    }

    public function testCustomPropertyOwnValueTakesPrecedence(): void
    {
        $parent = new Page(false);
        $parent->setCustomProperty('mainImageFormat', 'wide');

        $child = new Page(false);
        $child->extendedPage = $parent;
        $child->setCustomProperty('mainImageFormat', 'square');

        self::assertSame('square', $child->getCustomProperty('mainImageFormat'));
    }

    public function testMultiLevelInheritance(): void
    {
        $grandparent = new Page(false);
        $grandparent->template = 'gp_template.html.twig';
        $grandparent->setCustomProperty('color', 'red');

        $parent = new Page(false);
        $parent->extendedPage = $grandparent;

        $child = new Page(false);
        $child->extendedPage = $parent;

        self::assertSame('gp_template.html.twig', $child->getTemplate());
        self::assertSame('red', $child->getCustomProperty('color'));
    }

    public function testNoInheritanceWithoutExtendedPage(): void
    {
        $page = new Page(false);

        self::assertNull($page->getMainImage());
        self::assertNull($page->getTemplate());
        self::assertNull($page->getCustomProperty('anything'));
    }

    public function testHasCustomPropertyDoesNotInherit(): void
    {
        $parent = new Page(false);
        $parent->setCustomProperty('parentOnly', 'value');

        $child = new Page(false);
        $child->extendedPage = $parent;

        self::assertFalse($child->hasCustomProperty('parentOnly'));
        self::assertSame('value', $child->getCustomProperty('parentOnly'));
    }

    public function testGetCustomPropertiesDoesNotInherit(): void
    {
        $parent = new Page(false);
        $parent->setCustomProperty('inherited', 'value');

        $child = new Page(false);
        $child->extendedPage = $parent;
        $child->setCustomProperty('own', 'mine');

        self::assertArrayNotHasKey('inherited', $child->getCustomProperties());
        self::assertArrayHasKey('own', $child->getCustomProperties());
    }

    public function testIsCacheDefaultsToTrue(): void
    {
        $page = new Page(false);

        self::assertTrue($page->isCache(), 'Cache should be enabled by default (no customProperty set)');
    }

    public function testSetCacheFalseDisablesCache(): void
    {
        $page = new Page(false);
        $page->setCache(false);

        self::assertFalse($page->isCache());
        self::assertFalse($page->getCustomProperty('cache'));
    }

    public function testSetCacheTrueEnablesCache(): void
    {
        $page = new Page(false);
        $page->setCache(false);
        $page->setCache(true);

        self::assertTrue($page->isCache());
        self::assertTrue($page->getCustomProperty('cache'));
    }

    /**
     * `page.uniqueGalleryId` in the gallery template: sequential within a render,
     * restarting for every freshly loaded entity — so re-rendering an unchanged
     * page yields the same ids (static builds and content-hash caches depend on
     * that; the template's `random()` fallback never should be reached when a
     * page is in context).
     */
    public function testUniqueGalleryIdIsSequentialAndRestartsPerEntity(): void
    {
        $page = new Page(false);

        self::assertSame(1, $page->uniqueGalleryId());
        self::assertSame(2, $page->uniqueGalleryId());

        $samePageFreshlyLoaded = new Page(false);
        self::assertSame(1, $samePageFreshlyLoaded->uniqueGalleryId());
    }
}
