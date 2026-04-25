<?php

declare(strict_types=1);

namespace Pushword\PageScanner;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\PageScanner\Scanner\LinkedDocsScanner;

use function Safe\file_get_contents;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class LinkedDocsScannerTest extends KernelTestCase
{
    private function createScanner(): LinkedDocsScanner
    {
        return new LinkedDocsScanner(
            self::getContainer()->get('doctrine.orm.default_entity_manager'),
            self::getContainer()->get(SiteRegistry::class),
            [],
            __DIR__.'/../../skeleton/public',
            self::getContainer()->get('translator'),
        );
    }

    public function testLinkedDocsScanner(): void
    {
        self::bootKernel();
        $errors = $this->createScanner()->scan($this->getPage(), file_get_contents(__DIR__.'/data/page.html'));

        self::assertContains('<code>#install</code> target not found', $errors);
        self::assertNotContains('<code>#fun</code> target not found', $errors);
    }

    public function testCrossHostInternalLinkToExistingPage(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        // localhost.dev/homepage exists in fixtures → no error
        $html = '<a href="https://localhost.dev/homepage">link</a>';
        $errors = $scanner->scan($this->getPage(), $html);

        self::assertSame([], $errors);
    }

    #[DataProvider('homepageUrlProvider')]
    public function testCrossHostInternalLinkToHomepage(string $url): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        $errors = $scanner->scan($this->getPage('other-page'), '<a href="'.$url.'">home</a>');

        self::assertSame([], $errors, $url.' should resolve internally without error');
    }

    /**
     * @return Iterator<string, array{string}>
     */
    public static function homepageUrlProvider(): Iterator
    {
        yield 'with trailing slash' => ['https://localhost.dev/'];
        yield 'without trailing slash' => ['https://localhost.dev'];
    }

    public function testCrossHostInternalLinkToMissingPage(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        // localhost.dev/nonexistent does not exist → "not found" error
        $html = '<a href="https://localhost.dev/nonexistent">link</a>';
        $errors = $scanner->scan($this->getPage(), $html);

        self::assertCount(1, $errors);
        self::assertStringContainsString('https://localhost.dev/nonexistent', $errors[0]);
    }

    public function testCrossHostInternalLinkToUnpublishedPage(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $unpublished = new Page();
        $unpublished->setH1('Future page');
        $unpublished->setSlug('future-page');
        $unpublished->host = 'localhost.dev';
        $unpublished->locale = 'en';
        $unpublished->setMainContent('...');
        $unpublished->setPublishedAt(new DateTime('+1 year'));

        $em->persist($unpublished);
        $em->flush();

        try {
            $scanner = $this->createScanner();
            $scanner->preloadPageCache();

            $html = '<a href="https://localhost.dev/future-page">link</a>';
            $errors = $scanner->scan($this->getPage('other-page'), $html);

            self::assertCount(1, $errors);
            self::assertStringContainsString('https://localhost.dev/future-page', $errors[0]);
        } finally {
            $em->remove($unpublished);
            $em->flush();
        }
    }

    public function testCrossHostInternalLinkToRedirectionPage(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();

        // "pushword" page in fixtures has mainContent "Location: ..." → is a redirection
        $html = '<a href="https://localhost.dev/pushword">link</a>';
        $errors = $scanner->scan($this->getPage('other-page'), $html);

        self::assertCount(1, $errors);
        self::assertStringContainsString('https://localhost.dev/pushword', $errors[0]);
    }

    public function testExternalLinkStillTreatedAsExternal(): void
    {
        self::bootKernel();
        $scanner = $this->createScanner();
        $scanner->preloadPageCache();
        $scanner->enableCollectMode();

        // unknown-host.com is not a known Pushword host → collected as external
        $html = '<a href="https://unknown-host.com/page">link</a>';
        $scanner->scan($this->getPage(), $html);

        self::assertContains('https://unknown-host.com/page', $scanner->getCollectedExternalUrls());
    }

    private function getPage(string $slug = 'homepage', string $host = ''): Page
    {
        $page = new Page();
        $page->setH1('Welcome to Pushword !');
        $page->setSlug($slug);
        $page->host = $host;
        $page->locale = 'en';
        $page->createdAt = new DateTime('2 days ago');
        $page->setMainContent('...');
        $page->setCustomProperty('pageScanLinksToIgnore', ['https://example2.tld/*']);

        return $page;
    }
}
