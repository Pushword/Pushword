<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\Generator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Pushword\StaticGenerator\Generator\HtmlMinification;
use Pushword\StaticGenerator\Generator\HtmlMinifier;
use Symfony\Component\Filesystem\Filesystem;

final class HtmlMinificationTest extends TestCase
{
    private string $directory;

    private ?HtmlMinification $minifier = null;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/pushword-native-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->minifier?->reset();
        new Filesystem()->remove($this->directory);
    }

    public function testPhpNeedsNoExecutable(): void
    {
        $minifier = new HtmlMinification();
        self::assertSame([], $minifier->compressMany([]));
        self::assertSame(HtmlMinifier::compress($this->html()), $minifier->compress($this->html()));
        self::assertSame('<p>invalid: '."\xff".'</p>', $minifier->compress('<p>invalid: '."\xff".'</p><!-- removed -->'));
    }

    public function testEmptyBatchDoesNotStartAnUnavailableExecutable(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        self::assertSame([], new HtmlMinification($this->directory.'/missing', logger: $logger)->compressMany([]));
    }

    public function testWorkerIsReusedWithAnIndependentDeadlineAndReset(): void
    {
        $binary = $this->worker('normal');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $this->minifier = new HtmlMinification($binary, 2.0, $logger);
        $first = $this->minifier->compress('first');
        $pid = explode(':', $first)[1];
        self::assertSame('native:'.$pid.':first', $first);
        usleep(2100000);
        self::assertSame(['native:'.$pid.':second', 'native:'.$pid.':third'], $this->minifier->compressMany(['second', 'third']));
        $this->minifier->reset();
        self::assertNotSame('native:'.$pid.':first', $this->minifier->compress('first'));
    }

    #[DataProvider('failures')]
    public function testFailureFallsBackForTheWholeBatchAndStopsRetrying(string $mode): void
    {
        $binary = $this->worker($mode);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $this->minifier = new HtmlMinification($binary, 'timeout' === $mode ? 0.5 : 5.0, $logger);
        $documents = [$this->html(), '<p> fragment </p><!-- comment -->'];
        $expected = array_map(HtmlMinifier::compress(...), $documents);
        self::assertSame($expected, $this->minifier->compressMany($documents));
        // Repairing the executable only takes effect after reset, never mid-request.
        file_put_contents($binary.'.mode', 'normal');
        self::assertSame($expected, $this->minifier->compressMany($documents));
        $this->minifier->reset();
        self::assertStringStartsWith('native:', $this->minifier->compress('recovered'));
    }

    /** @return iterable<string, array{string}> */
    public static function failures(): iterable
    {
        foreach (['crash', 'json', 'version', 'id', 'count', 'type', 'object', 'incomplete', 'timeout', 'overflow', 'stderr', 'mismatch'] as $mode) {
            yield $mode => [$mode];
        }
    }

    public function testMissingBinaryAndInvalidUtf8FallBack(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('warning');
        self::assertSame(HtmlMinifier::compress($this->html()), new HtmlMinification($this->directory.'/missing', logger: $logger)->compress($this->html()));
        $this->minifier = new HtmlMinification($this->worker('normal'), logger: $logger);
        self::assertSame("<p>\xff</p>", $this->minifier->compress("<p>\xff</p><!-- remove -->"));
    }

    public function testOversizedInputFallsBackWithoutStartingTheWorker(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $this->minifier = new HtmlMinification($this->worker('normal'), logger: $logger);
        $html = str_repeat('x', 16 * 1024 * 1024);
        self::assertSame($html, $this->minifier->compress($html));
    }

    private function html(): string
    {
        return '<!DOCTYPE html><html><body><p>à   b</p><!-- c --></body></html>';
    }

    private function worker(string $mode): string
    {
        // Spaces, quotes and shell metacharacters must remain part of the executable path.
        $binary = $this->directory."/worker ' $ &";
        copy(__DIR__.'/fixtures/html-worker.php', $binary);
        chmod($binary, 0700);
        file_put_contents($binary.'.mode', $mode);

        return $binary;
    }
}
