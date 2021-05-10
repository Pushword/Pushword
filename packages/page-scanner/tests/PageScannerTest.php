<?php

namespace Pushword\PageScanner;

use App\Entity\Page;
use Pushword\Core\Entity\PageInterface;
use Pushword\PageScanner\Scanner\LinkedDocsScanner;
use Pushword\PageScanner\Scanner\PageScannerService;
use Pushword\PageScanner\Scanner\ParentPageScanner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PageScannerTest extends KernelTestCase
{
    public function testIt()
    {
        self::bootKernel();

        $scanner = new PageScannerService(
            self::$kernel->getContainer()->get('pushword.router'),
            self::$kernel
        );
        $scanner->linkedDocsScanner = new LinkedDocsScanner(
            self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager'),
            __DIR__.'/../../skeleton/public',
        );
        $scanner->linkedDocsScanner->translator = self::$kernel->getContainer()->get('translator');

        $scanner->parentPageScanner = new ParentPageScanner();
        $scanner->parentPageScanner->translator = self::$kernel->getContainer()->get('translator');

        $errors = $scanner->scan($this->getPage());

        $this->assertTrue( // TODO
            \is_array($errors) || true === $errors
        );
    }

    public function getPage(): PageInterface
    {
        $page = (new Page())
            ->setH1('Welcome : this is your first page')
            ->setSlug('homepage')
            ->setLocale('en')
            ->setCreatedAt(new \DateTime('2 days ago'))
            ->setMainContent('...');

        return $page;
    }
}
