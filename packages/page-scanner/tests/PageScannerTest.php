<?php

namespace Pushword\PageScanner;

use DateTime;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\Markdown\BrokenImageComment;
use Pushword\PageScanner\Scanner\BrokenImageScanner;
use Pushword\PageScanner\Scanner\PageScannerService;
use Pushword\PageScanner\Scanner\ScanErrorCode;
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
            array_filter($errors, static fn (array $error): bool => str_contains($error['message'], 'does-not-exist-broken.jpg')),
            'A broken body image must surface as a scan error.',
        );
        self::assertSame(ScanErrorCode::ImageNotFound->value, $errors[0]['code']);
    }

    /**
     * A page that throws on render is the one finding this service raises itself,
     * rather than collecting from a scanner.
     */
    public function testAPageThatCannotRenderIsReportedAsARenderError(): void
    {
        self::bootKernel();

        /** @var PageScannerService $scanner */
        $scanner = self::getContainer()->get(PageScannerService::class);

        $page = $this->getPage();
        $page->template = 'page/no-such-template.html.twig';

        $errors = $scanner->scan($page);

        self::assertIsArray($errors);
        self::assertSame(ScanErrorCode::RenderError->value, $errors[0]['code']);
    }

    /**
     * Read from the content, not the rendered HTML — a page that renders nothing
     * has no HTML to carry the directive that silences exactly that failure.
     */
    public function testAPageSilencesItsOwnRenderError(): void
    {
        self::bootKernel();

        /** @var PageScannerService $scanner */
        $scanner = self::getContainer()->get(PageScannerService::class);

        $page = $this->getPage();
        $page->template = 'page/no-such-template.html.twig';
        $page->mainContent = '<!-- page-scanner-ignore: render-error -->';

        self::assertTrue($scanner->scan($page));
    }

    public function getPage(): Page
    {
        $page = new Page();
        $page->h1 = 'Welcome to Pushword !';
        $page->slug = 'homepage';
        $page->locale = 'en';
        $page->createdAt = new DateTime('2 days ago');
        $page->mainContent = '...';

        return $page;
    }
}
