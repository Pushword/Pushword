<?php

namespace Pushword\PageScanner;

use DateTime;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\PageScanner\Scanner\LinkedDocsScanner;

use function Safe\file_get_contents;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
class LinkedDocsScannerTest extends KernelTestCase
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

    private function getPage(): Page
    {
        $page = new Page();
        $page->setH1('Welcome to Pushword !');
        $page->setSlug('homepage');
        $page->locale = 'en';
        $page->createdAt = new DateTime('2 days ago');
        $page->setMainContent('...');
        $page->setCustomProperty('pageScanLinksToIgnore', ['https://example2.tld/*']);

        return $page;
    }
}
