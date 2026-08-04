<?php

namespace Pushword\PageScanner\Tests\Scanner;

use PHPUnit\Framework\Attributes\DataProvider;
use Pushword\Core\Entity\Page;
use Pushword\PageScanner\Scanner\MissingAltScanner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MissingAltScannerTest extends KernelTestCase
{
    public function testReportsAnImageWithNoAltAttribute(): void
    {
        $errors = $this->scan('<p><img src="/media/default/lake.jpg" width="8"></p>');

        self::assertCount(1, $errors);
        self::assertStringContainsString('/media/default/lake.jpg', $errors[0]);
        self::assertStringContainsString('alternative text', $errors[0], 'The message must be translated, not left as its key.');
    }

    #[DataProvider('altlessProvider')]
    public function testReportsEveryShapeOfAMissingAlt(string $img): void
    {
        self::assertCount(1, $this->scan($img));
    }

    /** @return iterable<string, array{string}> */
    public static function altlessProvider(): iterable
    {
        yield 'no attribute' => ['<img src="/a.jpg">'];
        yield 'empty double quotes' => ['<img src="/a.jpg" alt="">'];
        yield 'empty single quotes' => ["<img src='/a.jpg' alt=''>"];
        yield 'whitespace only' => ['<img src="/a.jpg" alt="   ">'];
        yield 'uppercase tag' => ['<IMG SRC="/a.jpg">'];
        yield 'self closing' => ['<img src="/a.jpg" />'];
    }

    #[DataProvider('describedProvider')]
    public function testStaysSilentWhenTheImageIsDescribedOrDecorative(string $img): void
    {
        self::assertSame([], $this->scan($img));
    }

    /** @return iterable<string, array{string}> */
    public static function describedProvider(): iterable
    {
        yield 'plain alt' => ['<img src="/a.jpg" alt="Un lac au petit matin">'];
        yield 'unquoted alt' => ['<img src=/a.jpg alt=lake>'];
        // An empty alt is the correct markup for a decorative image, but only the
        // paired role/aria-hidden says that is what was meant.
        yield 'decorative by role' => ['<img src="/a.jpg" alt="" role="presentation">'];
        yield 'decorative by aria-hidden' => ['<img src="/a.jpg" alt="" aria-hidden="true">'];
    }

    public function testReportsEachSourceOnce(): void
    {
        $html = '<img src="/a.jpg"><img src="/a.jpg"><img src="/b.jpg">';

        self::assertCount(2, $this->scan($html));
    }

    /**
     * With no src there is nothing to name the image by, so the tag itself is quoted —
     * escaped, since it lands in an HTML message.
     */
    public function testNamesTheTagWhenTheImageHasNoSource(): void
    {
        $errors = $this->scan('<img width="8" height="8">');

        self::assertCount(1, $errors);
        self::assertStringContainsString('&lt;img width=&quot;8&quot; height=&quot;8&quot;&gt;', $errors[0]);
    }

    /**
     * Deduplication keys on the src, so several src-less images collapse into one
     * report rather than one per broken tag.
     */
    public function testSourcelessImagesAreReportedOnce(): void
    {
        self::assertCount(1, $this->scan('<img width="8"><img width="16">'));
    }

    public function testIgnoresAPageWithNoHtml(): void
    {
        self::assertSame([], $this->scan(''));
    }

    /** @return string[] */
    private function scan(string $pageHtml): array
    {
        self::bootKernel();

        /** @var MissingAltScanner $scanner */
        $scanner = self::getContainer()->get(MissingAltScanner::class);

        $page = new Page();
        $page->slug = 'homepage';
        $page->locale = 'en';

        return array_values($scanner->scan($page, $pageHtml));
    }
}
