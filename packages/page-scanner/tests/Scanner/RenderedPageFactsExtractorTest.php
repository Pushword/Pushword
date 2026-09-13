<?php

declare(strict_types=1);

namespace Pushword\PageScanner\Tests\Scanner;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Pushword\PageScanner\Scanner\RenderedPageFacts;
use Pushword\PageScanner\Scanner\RenderedPageFactsExtractor;
use Symfony\Component\Filesystem\Filesystem;

final class RenderedPageFactsExtractorTest extends TestCase
{
    private string $directory;

    private ?RenderedPageFactsExtractor $extractor = null;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/pushword-page-facts-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->extractor?->reset();
        new Filesystem()->remove($this->directory);
    }

    public function testPhpDefaultDoesNotStartAWorker(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        self::assertNull(new RenderedPageFactsExtractor(logger: $logger)->extract('<a href="/one">one</a>'));
        self::assertNull(new RenderedPageFactsExtractor($this->directory.'/missing', logger: $logger)->extract(''));
    }

    public function testValidFactsAndReusedWorker(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $this->extractor = new RenderedPageFactsExtractor($this->worker('valid'), logger: $logger);
        $expected = new RenderedPageFacts(['/one'], ['/lake.jpg'], ['section']);

        self::assertEquals($expected, $this->extractor->extract('<p>first</p>'));
        self::assertEquals($expected, $this->extractor->extract('<p>second</p>'));
    }

    #[DataProvider('invalidFacts')]
    public function testInvalidFactsFallBackUntilReset(string $mode): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $binary = $this->worker($mode);
        $this->extractor = new RenderedPageFactsExtractor($binary, logger: $logger);

        self::assertNull($this->extractor->extract('<p>first</p>'));
        file_put_contents($binary.'.mode', 'valid');
        self::assertNull($this->extractor->extract('<p>second</p>'));
        $this->extractor->reset();
        self::assertEquals(new RenderedPageFacts(['/one'], ['/lake.jpg'], ['section']), $this->extractor->extract('<p>third</p>'));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidFacts(): iterable
    {
        yield 'missing field' => ['missing-field'];
        yield 'non-list' => ['invalid-list'];
        yield 'non-string' => ['invalid-value'];
    }

    public function testUnavailableWorkerFallsBackOnce(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $this->extractor = new RenderedPageFactsExtractor($this->directory.'/missing', logger: $logger);

        self::assertNull($this->extractor->extract('<p>first</p>'));
        self::assertNull($this->extractor->extract('<p>second</p>'));
    }

    private function worker(string $mode): string
    {
        $binary = $this->directory.'/worker';
        copy(__DIR__.'/fixtures/page-facts-worker.php', $binary);
        chmod($binary, 0700);
        file_put_contents($binary.'.mode', $mode);

        return $binary;
    }
}
