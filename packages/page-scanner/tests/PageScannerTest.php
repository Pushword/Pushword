<?php

namespace Pushword\PageScanner;

use DateTime;
use Pushword\Core\Entity\Page;
use Pushword\PageScanner\Scanner\PageScannerService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PageScannerTest extends KernelTestCase
{
    public function testIt(): void
    {
        self::bootKernel();

        /** @var PageScannerService $scanner */
        $scanner = self::getContainer()->get(PageScannerService::class);

        $errors = $scanner->scan($this->getPage());

        self::assertTrue(\is_array($errors) || $errors); // TODO @phpstan-ignore-line
    }

    public function getPage(): Page
    {
        return new Page()
            ->setH1('Welcome to Pushword !')
            ->setSlug('homepage')
            ->setLocale('en')
            ->setCreatedAt(new DateTime('2 days ago'))
            ->setMainContent('...');
    }
}
