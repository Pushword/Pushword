<?php

namespace Pushword\Core\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;

class PageTest extends TestCase
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

    public function testMainImageInheritance(): void
    {
        $media = static::createStub(Media::class);

        $parent = new Page(false);
        $parent->mainImage = $media;

        $child = new Page(false);
        $child->extendedPage = $parent;

        self::assertSame($media, $child->getMainImage());
        self::assertNull($child->mainImage, 'Raw property should be null');
    }

    public function testMainImageOwnValueTakesPrecedence(): void
    {
        $parentMedia = static::createStub(Media::class);
        $childMedia = static::createStub(Media::class);

        $parent = new Page(false);
        $parent->mainImage = $parentMedia;

        $child = new Page(false);
        $child->extendedPage = $parent;
        $child->mainImage = $childMedia;

        self::assertSame($childMedia, $child->getMainImage());
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
}
