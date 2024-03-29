<?php

namespace Pushword\PageScanner;

use App\Entity\Page;
use Pushword\Core\Entity\PageInterface;
use Pushword\PageScanner\Scanner\LinkedDocsScanner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LinkedDocsScannerTest extends KernelTestCase
{
    public function testLinkedDocsScanner()
    {
        self::bootKernel();
        $linkedDocsScanner = new LinkedDocsScanner(
            self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager'),
            [],
            __DIR__.'/../../skeleton/public',
        );
        $linkedDocsScanner->translator = self::$kernel->getContainer()->get('translator');

        $errors = $linkedDocsScanner->scan($this->getPage(), file_get_contents(__DIR__.'/data/page.html'));

        $knowedErrors = [
            '<code>https://localhost.dev/feed.xml</code> unreacheable',
            '<code>https://localhost.dev/</code> unreacheable',
            '<code>#install</code> target not found',
        ];

        foreach ($knowedErrors as $error) {
            $this->assertContains($error, $errors);
        }
    }

    public function getPage(): PageInterface
    {
        $page = (new Page())
            ->setH1('Welcome : this is your first page')
            ->setSlug('homepage')
            ->setLocale('en')
            ->setCreatedAt(new \DateTime('2 days ago'))
            ->setCustomProperty('pageScanLinksToIgnore', ['https://example2.tld/*'])
            ->setMainContent('...'); // \Safe\file_get_contents( __DIR__.'/../../skeleton/src/DataFixtures/WelcomePage.md')

        return $page;
    }
}
