<?php

namespace Pushword\AdminBlockEditor\Tests;

use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\AdminBlockEditor\EditorJsHelper;

class EditorJsHelperTest extends TestCase
{
    public function testDecodeValidJson(): void
    {
        $json = '{"blocks":[{"type":"paragraph","data":{"text":"Hello"}}]}';

        $result = EditorJsHelper::decode($json);

        self::assertCount(1, $result->blocks);
        self::assertSame('paragraph', $result->blocks[0]->type);
    }

    public function testDecodeWithTextProperty(): void
    {
        $json = '{"blocks":[{"type":"header","text":"Title"}]}';

        $result = EditorJsHelper::decode($json);

        /** @var object{type: string, text: string} $block */
        $block = $result->blocks[0];
        self::assertSame('Title', $block->text);
    }

    public function testDecodeWithTunesProperty(): void
    {
        $json = '{"blocks":[{"type":"paragraph","tunes":{"align":"center"}}]}';

        $result = EditorJsHelper::decode($json);

        /** @var object{type: string, tunes: object{align: string}} $block */
        $block = $result->blocks[0];
        self::assertSame('center', $block->tunes->align);
    }

    #[DataProvider('provideInvalidJson')]
    public function testDecodeThrowsOnInvalidInput(string $input): void
    {
        $this->expectException(Exception::class);
        EditorJsHelper::decode($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidJson(): iterable
    {
        yield 'empty string' => [''];
        yield 'no blocks keyword' => ['{"data":"value"}'];
        yield 'blocks not array' => ['{"blocks":"notarray"}'];
    }

    public function testDecodeThrowsOnBlockWithoutType(): void
    {
        $this->expectException(Exception::class);
        EditorJsHelper::decode('{"blocks":[{"data":"value"}]}');
    }

    public function testDecodeThrowsOnInvalidTunes(): void
    {
        $this->expectException(Exception::class);
        EditorJsHelper::decode('{"blocks":[{"type":"p","tunes":"invalid"}]}');
    }

    public function testDecodeThrowsOnInvalidText(): void
    {
        $this->expectException(Exception::class);
        EditorJsHelper::decode('{"blocks":[{"type":"p","text":123}]}');
    }

    public function testTryToDecodeReturnsObjectOnValidJson(): void
    {
        $json = '{"blocks":[{"type":"paragraph"}]}';

        $result = EditorJsHelper::tryToDecode($json);

        self::assertIsObject($result);
    }

    public function testTryToDecodeReturnsFalseOnInvalidJson(): void
    {
        self::assertFalse(EditorJsHelper::tryToDecode(''));
        self::assertFalse(EditorJsHelper::tryToDecode('not json'));
    }
}
