<?php

namespace Pushword\PageScanner\Tests\Scanner;

use DateTime;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\EditorNotice\TwigErrorMarker;
use Pushword\PageScanner\Scanner\TwigErrorScanner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TwigErrorScannerTest extends KernelTestCase
{
    public function testReportsTwigErrorMarker(): void
    {
        self::bootKernel();

        /** @var TwigErrorScanner $scanner */
        $scanner = self::getContainer()->get(TwigErrorScanner::class);

        $pageHtml = '<p>'.TwigErrorMarker::for('Unknown "undefined_function_xyz" function.').'</p>';

        $errors = $scanner->scan($this->getPage(), $pageHtml);

        self::assertNotEmpty(
            array_filter($errors, static fn (string $message): bool => str_contains($message, 'undefined_function_xyz')),
            'A failed Twig block must surface as a scan error.',
        );
    }

    public function testReportsNothingWhenNoMarkerPresent(): void
    {
        self::bootKernel();

        /** @var TwigErrorScanner $scanner */
        $scanner = self::getContainer()->get(TwigErrorScanner::class);

        self::assertSame([], $scanner->scan($this->getPage(), '<p>all good</p>'));
    }

    private function getPage(): Page
    {
        $page = new Page();
        $page->setSlug('homepage');
        $page->locale = 'en';
        $page->createdAt = new DateTime('2 days ago');

        return $page;
    }
}
