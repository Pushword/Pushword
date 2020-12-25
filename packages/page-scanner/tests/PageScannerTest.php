<?php

namespace Pushword\PageScanner;

use DateTime;
use Pushword\Core\Entity\Page;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\PageScanner\Scanner\LinkedDocsScanner;
use Pushword\PageScanner\Scanner\PageScannerService;
use Pushword\PageScanner\Scanner\ParentPageScanner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PageScannerTest extends KernelTestCase
{
    public function testIt(): void
    {
        $scanner = new PageScannerService(
            self::getContainer()->get(PushwordRouteGenerator::class),
            self::bootKernel(),
        );
        $scanner->linkedDocsScanner = new LinkedDocsScanner(
            self::getContainer()->get('doctrine.orm.default_entity_manager'),
            [],
            __DIR__.'/../../skeleton/public',
            self::getContainer()->get('translator')
        );

        $scanner->parentPageScanner = new ParentPageScanner(self::getContainer()->get('translator'));

        $errors = $scanner->scan($this->getPage());

        self::assertTrue(\is_array($errors) || $errors); // TODO @phpstan-ignore-line
    }

    public function getPage(): Page
    {
        return (new Page())
            ->setH1('Welcome to Pushword !')
            ->setSlug('homepage')
            ->setLocale('en')
            ->setCreatedAt(new DateTime('2 days ago'))
            ->setMainContent('...');
    }
}
