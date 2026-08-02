<?php

namespace Pushword\Core\Tests\Entity;

use DateTime;
use LogicException;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;

final class PageTest extends TestCase
{
    public function testBasics(): void
    {
        $page = new Page();
        self::assertEmpty($page->title);

        $page->title = 'hello';
        self::assertSame('hello', $page->title);

        $page->slug = 'hello you';
        self::assertSame('hello-you', $page->slug);
    }

    public function testHoldPublication(): void
    {
        $page = new Page();
        self::assertFalse($page->isHoldPublication());

        $page->setHoldPublication(true);
        self::assertTrue($page->isHoldPublication());

        $page->setHoldPublication(false);
        self::assertFalse($page->isHoldPublication());
        self::assertNull($page->holdPublicationAt);
    }

    public function testHoldPublicationKeepsExplicitTimestamp(): void
    {
        $page = new Page();
        $explicit = new DateTime('2026-01-01 00:00');
        $page->holdPublicationAt = $explicit;
        self::assertTrue($page->isHoldPublication());

        // Holding again must not overwrite an existing timestamp.
        $page->setHoldPublication(true);
        self::assertSame($explicit, $page->holdPublicationAt);
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
        $page->mainContent = 'Location: https://example.tld';

        self::assertFalse($page->isIndexable());
    }

    public function testWritingTheContentTrimsItAndDropsTheEditorsEmptyAnchors(): void
    {
        $page = new Page();
        $page->mainContent = "\n  Before <a href=\"#x\" class=\"anchor\"></a>after.  \n";

        self::assertSame('Before after.', $page->mainContent);
    }

    public function testTheContentNormalizationIsReusableOnStoredContent(): void
    {
        // What pw:page:clean re-runs over pages written before a normalization
        // changed — assigning the property to itself would read as a no-op.
        self::assertSame(
            'Before after.',
            Page::normalizeMainContent('  Before <a href="#x" class="anchor"></a>after.  '),
        );
        self::assertSame('', Page::normalizeMainContent(null));
    }

    public function testRewritingTheContentReparsesTheRedirection(): void
    {
        $page = new Page();
        $page->mainContent = 'Location: https://example.tld';
        self::assertTrue($page->hasRedirection());

        // The parse is cached; the write has to invalidate it or the page keeps
        // redirecting to a target its content no longer names.
        $page->mainContent = 'Plain content now.';

        self::assertFalse($page->hasRedirection());
    }

    public function testANoindexPageIsNotIndexable(): void
    {
        $page = new Page();
        $page->metaRobots = 'noindex';
        self::assertFalse($page->isIndexable());

        // The rule matches the SQL twin's `LOWER(...) NOT LIKE '%noindex%'`: noindex
        // never travels alone, it comes paired with a follow directive.
        $page->metaRobots = 'noindex, follow';
        self::assertFalse($page->isIndexable());

        // Written by hand in a flat file or over the API, nothing lowercases it.
        $page->metaRobots = 'NoIndex, NoArchive';
        self::assertFalse($page->isIndexable());

        // `none` is the robots shorthand for `noindex, nofollow`.
        $page->metaRobots = 'none';
        self::assertFalse($page->isIndexable());

        $page->metaRobots = 'index, follow';
        self::assertTrue($page->isIndexable());

        // noimageindex only bans the images: the substring does not line up.
        $page->metaRobots = 'noimageindex';
        self::assertTrue($page->isIndexable());

        // Nor does any other directive carry `none` inside it.
        $page->metaRobots = 'nosnippet, notranslate, noarchive';
        self::assertTrue($page->isIndexable());
    }

    public function testAPageCannotBeItsOwnParent(): void
    {
        $page = new Page();
        $this->expectException(LogicException::class);
        $page->parentPage = $page;
    }

    public function testParentAssignmentRejectsCycles(): void
    {
        $parent = new Page();
        $child = new Page();
        $child->parentPage = $parent;

        $this->expectException(LogicException::class);
        $parent->parentPage = $child;
    }

    public function testRedirectFromNormalizesMapListAndRows(): void
    {
        $page = new Page();
        self::assertSame([], $page->redirectFrom);

        // Map form, with leading slash and out-of-order keys → normalized + ksorted.
        $page->redirectFrom = ['/old/two' => 302, 'old-one' => 301];
        self::assertSame(['old-one' => 301, 'old/two' => 302], $page->redirectFrom);

        // Jekyll-style bare list → implicit 301.
        $page->redirectFrom = ['a-slug', 'b-slug'];
        self::assertSame(['a-slug' => 301, 'b-slug' => 301], $page->redirectFrom);

        // Row form (admin collection) → map, invalid code falls back to 301.
        $page->redirectFrom = [['from' => 'foo', 'code' => 307], ['from' => 'bar', 'code' => 999]];
        self::assertSame(['bar' => 301, 'foo' => 307], $page->redirectFrom);

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

        self::assertSame(['first' => 301, 'second' => 308], $page->redirectFrom);
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

        self::assertArrayNotHasKey('inherited', $child->customProperties);
        self::assertArrayHasKey('own', $child->customProperties);
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
