<?php

namespace Pushword\Core\Tests\Component\EntityFilter\ValueObject;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Component\EntityFilter\ValueObject\SplitContent;
use Pushword\Core\Entity\Page;

/**
 * The heading fix re-serializes the whole content through masterminds/html5,
 * which lowercases SVG elements missing from its case map (feDropShadow).
 */
final class SplitContentSvgCaseTest extends TestCase
{
    public function testSvgElementCaseSurvivesHeadingFix(): void
    {
        $html = '<h2>Title</h2><p>text</p>'
            .'<svg viewBox="0 0 4 4"><filter id="s"><feDropShadow dx="0" dy="1" stdDeviation="1.5"/></filter></svg>';

        $page = new Page();
        $page->host = 'localhost';
        $page->setCustomProperty('toc', true);

        $body = new SplitContent($html, $page)->getBody();

        self::assertStringContainsString('<feDropShadow', $body);
        self::assertStringContainsString('viewBox=', $body);
        self::assertStringContainsString('id="title"', $body);
    }
}
