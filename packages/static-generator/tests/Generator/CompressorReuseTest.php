<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\Generator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\StaticGenerator\Generator\CompressionAlgorithm;
use Pushword\StaticGenerator\Generator\Compressor;
use Symfony\Component\Filesystem\Filesystem;

final class CompressorReuseTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/pushword-compression-reuse-'.bin2hex(random_bytes(8));
        new Filesystem()->mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
        parent::tearDown();
    }

    #[DataProvider('unchangedContents')]
    public function testIdenticalRenderedBytesReuseThePreviousCompressedFile(string $content): void
    {
        $previous = $this->directory.'/previous.html';
        $current = $this->directory.'/current.html';
        file_put_contents($previous, $content);
        file_put_contents($current, $content);
        // Level zero is valid gzip with bytes distinct from the normal level nine.
        $compressed = gzencode($content, 0);
        self::assertNotFalse($compressed);
        self::assertNotSame(CompressionAlgorithm::Gzip->nativeCompress($content), $compressed);
        file_put_contents($previous.'.gz', $compressed);

        new Compressor([CompressionAlgorithm::Gzip])->compressFile($current, $previous);

        self::assertSame($compressed, file_get_contents($current.'.gz'));
        self::assertSame($content, gzdecode($compressed));
        self::assertSame($compressed, file_get_contents($previous.'.gz'));
    }

    /** @return iterable<string, array{string}> */
    public static function unchangedContents(): iterable
    {
        yield 'HTML' => [str_repeat('<p>Identical rendered content.</p>', 100)];
        yield 'empty file' => [''];
    }

    #[DataProvider('regenerationCases')]
    public function testMissingOrChangedOutputIsCompressedNormally(string $scenario): void
    {
        $content = str_repeat('<p>Freshly rendered content.</p>', 100);
        $previous = $this->directory.'/previous.html';
        $current = $this->directory.'/current.html';
        file_put_contents($current, $content);
        if ('missing previous file' !== $scenario && 'first build' !== $scenario) {
            file_put_contents($previous, 'changed HTML' === $scenario ? '<p>Old content.</p>' : $content);
        }

        if ('missing sidecar' !== $scenario) {
            file_put_contents($previous.'.gz', 'empty sidecar' === $scenario ? '' : (string) gzencode('old sidecar', 0));
        }

        if ('same path' === $scenario) {
            file_put_contents($current.'.gz', (string) gzencode($content, 0));
        }

        new Compressor([CompressionAlgorithm::Gzip])->compressFile(
            $current,
            'first build' === $scenario ? null : ('same path' === $scenario ? $current : $previous),
        );

        self::assertSame(CompressionAlgorithm::Gzip->nativeCompress($content), file_get_contents($current.'.gz'));
    }

    /** @return iterable<string, array{string}> */
    public static function regenerationCases(): iterable
    {
        foreach (['first build', 'missing previous file', 'changed HTML', 'missing sidecar', 'empty sidecar', 'same path'] as $scenario) {
            yield $scenario => [$scenario];
        }
    }

    public function testUnavailableAlgorithmDoesNotCopyAPreviousSidecar(): void
    {
        $previous = $this->directory.'/previous.html';
        $current = $this->directory.'/current.html';
        file_put_contents($previous, 'same content');
        file_put_contents($current, 'same content');
        file_put_contents($previous.'.br', 'previous Brotli output');

        new Compressor([CompressionAlgorithm::Gzip])->compressFile($current, $previous);

        self::assertFileDoesNotExist($current.'.br');
    }

    public function testMissingFormatIsCompressedWhileAnotherFormatIsReused(): void
    {
        $previous = $this->directory.'/previous.html';
        $current = $this->directory.'/current.html';
        file_put_contents($previous, 'same content');
        file_put_contents($current, 'same content');
        $gzip = gzencode('same content', 0);
        self::assertNotFalse($gzip);
        file_put_contents($previous.'.gz', $gzip);

        $compressor = new class([CompressionAlgorithm::Brotli, CompressionAlgorithm::Gzip]) extends Compressor {
            /** @var list<CompressionAlgorithm> */
            public array $compressed = [];

            public function compress(string $filePath, CompressionAlgorithm $algorithm): void
            {
                $this->compressed[] = $algorithm;
                file_put_contents($filePath.$algorithm->getExtension(), 'fresh-'.$algorithm->value);
            }
        };

        $compressor->compressFile($current, $previous);

        self::assertSame([CompressionAlgorithm::Brotli], $compressor->compressed);
        self::assertSame('fresh-brotli', file_get_contents($current.'.br'));
        self::assertSame($gzip, file_get_contents($current.'.gz'));
    }

    public function testNoAvailableAlgorithmDoesNotRequireAnInputFile(): void
    {
        $current = $this->directory.'/uncompressed.html';
        $previous = $this->directory.'/previous.html';
        file_put_contents($previous, 'unused previous content');

        new Compressor([])->compressFile($current, $previous);

        self::assertFileDoesNotExist($current);
    }
}
