<?php

namespace Pushword\Core\Tests\Component\EntityFilter\Filter;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Pushword\Core\Component\EntityFilter\Filter\Twig as TwigFilter;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\EditorNotice\TwigErrorMarker;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class TwigDegradationTest extends TestCase
{
    private function render(string $content): string
    {
        $filter = new class(new Environment(new ArrayLoader()), new NullLogger()) extends TwigFilter {
            public function renderPublic(string $string, Page $page): string
            {
                return $this->render($string, $page);
            }
        };

        $page = new Page();
        $page->host = 'localhost';

        return $filter->renderPublic($content, $page);
    }

    public function testRendersValidTwigUnchanged(): void
    {
        self::assertSame('2', $this->render('{{ 1 + 1 }}'));
    }

    public function testLeavesContentWithoutBracesUntouched(): void
    {
        self::assertSame('plain text', $this->render('plain text'));
    }

    public function testDegradesBrokenTwigToAnInvisibleMarkerInsteadOfThrowing(): void
    {
        $output = $this->render('before {{ undefined_function() }} after');

        self::assertStringContainsString('pushword:twig-error', $output);
        self::assertNotSame([], TwigErrorMarker::extractMessages($output));
    }
}
