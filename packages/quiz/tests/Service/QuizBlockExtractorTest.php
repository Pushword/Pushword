<?php

namespace Pushword\Quiz\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\Quiz\Service\QuizBlockExtractor;

final class QuizBlockExtractorTest extends TestCase
{
    private QuizBlockExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new QuizBlockExtractor();
    }

    public function testExtractsTagFormBodyVerbatim(): void
    {
        $blocks = $this->extractor->extract("Intro\n\n{% quiz %}{\"questions\":[]}{% endquiz %}\n\nOutro");

        self::assertCount(1, $blocks);
        self::assertSame('{"questions":[]}', $blocks[0]['json']);
        self::assertSame('{% quiz %}', $blocks[0]['form']);
        self::assertSame(3, $blocks[0]['line']);
    }

    public function testExtractsFunctionFormAndUnescapesApostrophes(): void
    {
        // Authored as {{ quiz('{"q":"L\'eau"}') }} — the \' must be unescaped back to '.
        $blocks = $this->extractor->extract("{{ quiz('{\"q\":\"L\\'eau\"}') }}");

        self::assertCount(1, $blocks);
        self::assertSame('{"q":"L\'eau"}', $blocks[0]['json']);
        self::assertSame("{{ quiz('…') }}", $blocks[0]['form']);
    }

    public function testExtractsBothFormsOrderedByPosition(): void
    {
        $blocks = $this->extractor->extract("{{ quiz('{\"a\":1}') }}\n\n{% quiz %}{\"b\":2}{% endquiz %}");

        self::assertCount(2, $blocks);
        self::assertSame(1, $blocks[0]['line']);
        self::assertSame("{{ quiz('…') }}", $blocks[0]['form']);
        self::assertSame(3, $blocks[1]['line']);
        self::assertSame('{% quiz %}', $blocks[1]['form']);
    }

    public function testReturnsEmptyWhenNoBlock(): void
    {
        self::assertSame([], $this->extractor->extract('Just prose, no quiz here.'));
    }
}
