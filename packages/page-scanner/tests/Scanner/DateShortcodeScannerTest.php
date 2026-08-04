<?php

namespace Pushword\PageScanner\Tests\Scanner;

use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use Pushword\Core\Component\EntityFilter\Filter\Date;
use Pushword\Core\Entity\Page;
use Pushword\PageScanner\Scanner\DateShortcodeScanner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DateShortcodeScannerTest extends KernelTestCase
{
    public function testReportsUnresolvedShortcode(): void
    {
        $errors = $this->scan('<meta name="description" content="Best CMS in date(Y) ?">');

        self::assertCount(1, $errors);
        self::assertStringContainsString('date(Y)', $errors[0]);
        self::assertStringContainsString('content pipeline', $errors[0], 'The message must be translated, not left as its key.');
    }

    /**
     * The scanner exists to report what the pipeline would have resolved, so its
     * pattern has to stay in step with the filter's. Asserting both here means a
     * spelling added to one and not the other fails instead of going unreported.
     */
    #[DataProvider('resolvableShortcodeProvider')]
    public function testReportsEverySpellingTheFilterResolves(string $shortcode): void
    {
        self::bootKernel();

        /** @var Date $dateFilter */
        $dateFilter = self::getContainer()->get(Date::class);

        self::assertNotSame(
            $shortcode,
            $dateFilter->convertDateShortCode($shortcode, 'en'),
            $shortcode.' is not a shortcode the filter resolves: fix the fixture, not the scanner.',
        );
        self::assertCount(1, $this->scan('<p>Copyright '.$shortcode.'</p>'));
    }

    /** @return iterable<string, array{string}> */
    public static function resolvableShortcodeProvider(): iterable
    {
        foreach ([
            'date(Y)', 'date(Y-1)', 'date(Y+1)', 'date(S)', 'date(W)',
            'date(M)', 'date(B)', 'date(A)', 'date(e)',
            "date('%Y')", 'date("Y")', 'DATE(Y)',
        ] as $shortcode) {
            yield $shortcode => [$shortcode];
        }
    }

    #[DataProvider('nonShortcodeProvider')]
    public function testIgnoresWhatIsNotAShortcode(string $text): void
    {
        self::assertSame([], $this->scan('<p>'.$text.'</p>'));
    }

    /** @return iterable<string, array{string}> */
    public static function nonShortcodeProvider(): iterable
    {
        yield 'unsupported code' => ['date(d)'];
        yield 'trailing characters' => ['date(Y7)'];
        yield 'resolved content' => ['Copyright '.date('Y')];
        // The filter has no word boundary and would resolve this one; the scanner
        // stays silent rather than blame the pipeline for every word ending in date.
        yield 'word ending in date' => ['update(Y)'];
    }

    public function testReportsEachDistinctShortcodeOnce(): void
    {
        $errors = $this->scan('<p>date(Y) date(Y) date(M) date(Y+1)</p>');

        self::assertCount(3, $errors);
    }

    public function testIgnoresDocumentedSyntaxAndScripts(): void
    {
        $html = '<pre><code>date(Y)</code></pre>'
            .'<p>Write <code>date(M)</code> to print the month.</p>'
            .'<script>const y = new Date(Y);</script>'
            .'<style>/* date(Y) */</style>';

        self::assertSame([], $this->scan($html));
    }

    public function testIgnoresAPageWithNoHtml(): void
    {
        self::assertSame([], $this->scan(''));
    }

    /** @return string[] */
    private function scan(string $pageHtml): array
    {
        self::bootKernel();

        /** @var DateShortcodeScanner $scanner */
        $scanner = self::getContainer()->get(DateShortcodeScanner::class);

        $page = new Page();
        $page->slug = 'homepage';
        $page->locale = 'en';
        $page->createdAt = new DateTime('2 days ago');

        return array_column($scanner->scan($page, $pageHtml), 'message');
    }
}
