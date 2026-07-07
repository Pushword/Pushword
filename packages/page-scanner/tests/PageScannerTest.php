<?php

namespace Pushword\PageScanner;

use DateTime;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\Markdown\BrokenImageComment;
use Pushword\PageScanner\Scanner\BrokenImageScanner;
use Pushword\PageScanner\Scanner\PageScannerService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class PageScannerTest extends KernelTestCase
{
    public function testIt(): void
    {
        self::bootKernel();

        /** @var PageScannerService $scanner */
        $scanner = self::getContainer()->get(PageScannerService::class);

        $errors = $scanner->scan($this->getPage());

        self::assertTrue(\is_array($errors) || $errors); // TODO @phpstan-ignore-line
    }

    public function testBrokenImageIsReported(): void
    {
        self::bootKernel();

        /** @var BrokenImageScanner $scanner */
        $scanner = self::getContainer()->get(BrokenImageScanner::class);

        // The renderer degrades an unresolvable body image to this marker (see
        // MarkdownExtensionTest::testBrokenBodyImageDegradesToComment); the scanner
        // surfaces it from the rendered page HTML.
        $pageHtml = '<p>'.BrokenImageComment::for('does-not-exist-broken.jpg').'</p>';

        $errors = $scanner->scan($this->getPage(), $pageHtml);

        self::assertNotEmpty(
            array_filter($errors, static fn (string $message): bool => str_contains($message, 'does-not-exist-broken.jpg')),
            'A broken body image must surface as a scan error.',
        );
    }

    public function getPage(): Page
    {
        $page = new Page();
        $page->setH1('Welcome to Pushword !');
        $page->setSlug('homepage');
        $page->locale = 'en';
        $page->createdAt = new DateTime('2 days ago');
        $page->setMainContent('...');

        return $page;
    }
}
