<?php

namespace Pushword\PageScanner;

use DateTime;
use Pushword\Core\Entity\Page;
use Pushword\PageScanner\Scanner\LinkedDocsScanner;

use function Safe\file_get_contents;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LinkedDocsScannerTest extends KernelTestCase
{
    public function testLinkedDocsScanner(): void
    {
        self::bootKernel();
        $linkedDocsScanner = new LinkedDocsScanner(
            self::getContainer()->get('doctrine.orm.default_entity_manager'),
            [],
            __DIR__.'/../../skeleton/public',
            self::getContainer()->get('translator')
        );

        $errors = $linkedDocsScanner->scan($this->getPage(), file_get_contents(__DIR__.'/data/page.html'));

        $knowedErrors = [
            '<code>https://localhost.dev/feed.xml</code> unreacheable',
            '<code>https://localhost.dev/</code> unreacheable',
            '<code>#install</code> target not found',
        ];

        foreach ($knowedErrors as $error) {
            self::assertContains($error, $errors);
        }

        self::assertNotContains('<code>#fun</code> target not found', $errors);
    }

    public function getPage(): Page
    {
        $page = new Page();
        $page->setH1('Welcome to Pushword !');
        $page->setSlug('homepage');
        $page->locale = 'en';
        $page->createdAt = new DateTime('2 days ago');
        $page->setMainContent('...'); // \Safe\file_get_contents( __DIR__.'/../../skeleton/src/DataFixtures/WelcomePage.md')
        $page->setCustomProperty('pageScanLinksToIgnore', ['https://example2.tld/*']);

        return $page;
    }
}
