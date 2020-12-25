<?php

namespace Pushword\Core\Tests\Entity;

use PHPUnit\Framework\TestCase;
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
}
