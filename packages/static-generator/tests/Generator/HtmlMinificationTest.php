<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\Generator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Pushword\StaticGenerator\Generator\HtmlMinification;
use Pushword\StaticGenerator\Generator\HtmlMinifier;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
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
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects(self::never())->method('getItem');
        self::assertSame([], new HtmlMinification($this->directory.'/missing', logger: $logger, cache: $cache)->compressMany([]));
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
        self::assertSame(HtmlMinifier::compress($this->html()), new HtmlMinification($this->directory.'/missing', logger: $logger, cache: new ArrayAdapter())->compress($this->html()));
        $this->minifier = new HtmlMinification($this->worker('normal'), logger: $logger, cache: new ArrayAdapter());
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

    public function testCachedResultsSurviveResetAndPreserveBatchOrder(): void
    {
        $cache = new ArrayAdapter();
        $binary = $this->worker('normal');
        $this->minifier = new HtmlMinification($binary, cache: $cache);
        $first = $this->minifier->compress('first');
        $this->minifier->reset();
        $this->minifier = new HtmlMinification($binary, cache: $cache);

        self::assertSame($first, $this->minifier->compress('first'), 'a cached document must not start another worker');
        $mixed = $this->minifier->compressMany(['first', 'second', 'first']);
        self::assertSame($first, $mixed[0]);
        self::assertStringEndsWith(':second', $mixed[1]);
        self::assertSame($first, $mixed[2]);
        self::assertSame($mixed, $this->minifier->compressMany(['first', 'second', 'first']));
        self::assertSame(['first', 'second'], new HtmlMinification(cache: $cache)->compressMany(['first', 'second']));
    }

    public function testPhpCacheReusesRawInputsAndKeepsChangedDocumentsFresh(): void
    {
        $storage = new ArrayAdapter();
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturnCallback($storage->getItem(...));
        $cache->expects(self::exactly(3))->method('save')->willReturnCallback($storage->save(...));
        $this->minifier = new HtmlMinification(cache: $cache);
        $documents = ['', $this->html()];
        $expected = array_map(HtmlMinifier::compress(...), $documents);
        self::assertSame($expected, $this->minifier->compressMany($documents));
        $this->minifier->reset();
        self::assertSame($expected, $this->minifier->compressMany($documents));

        $changed = str_replace('à   b', 'updated content', $this->html());
        self::assertSame(HtmlMinifier::compress($changed), $this->minifier->compress($changed));
        self::assertSame($expected, new HtmlMinification(cache: $cache)->compressMany($documents));
    }

    public function testInvalidCachedValueIsRecomputed(): void
    {
        $storage = new ArrayAdapter();
        $storage->save($storage->getItem('invalid')->set(['unexpected format']));

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($storage->getItem('invalid'));
        $cache->expects(self::once())->method('save')->willReturnCallback($storage->save(...));

        self::assertSame(HtmlMinifier::compress($this->html()), new HtmlMinification(cache: $cache)->compress($this->html()));
    }

    public function testLossyMinificationIsNotCachedAndRemainsReported(): void
    {
        $storage = new ArrayAdapter();
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturnCallback($storage->getItem(...));
        $cache->expects(self::never())->method('save');
        $this->minifier = new HtmlMinification(cache: $cache);
        $skipped = HtmlMinifier::$skippedOnBrokenLibxml;
        $html = "<p>\xff</p>";

        try {
            self::assertSame($html, $this->minifier->compress($html.'<!-- removed -->'));
            $this->minifier->reset();
            self::assertSame($html, $this->minifier->compress($html.'<!-- removed -->'));
            self::assertSame($skipped + 2, HtmlMinifier::$skippedOnBrokenLibxml);
        } finally {
            HtmlMinifier::$skippedOnBrokenLibxml = $skipped;
        }
    }

    public function testReplacingTheNativeBinaryInvalidatesCachedOutput(): void
    {
        $cache = new ArrayAdapter();
        $binary = $this->worker('normal');
        $this->minifier = new HtmlMinification($binary, cache: $cache);
        $first = $this->minifier->compress('same document');
        $this->minifier->reset();
        file_put_contents($binary, "\n// A new native release.\n", \FILE_APPEND);

        self::assertNotSame($first, $this->minifier->compress('same document'));
    }

    public function testFailedNativeBatchDoesNotMixCachedNativeAndPhpResults(): void
    {
        $cache = new ArrayAdapter();
        $binary = $this->worker('normal');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $this->minifier = new HtmlMinification($binary, logger: $logger, cache: $cache);
        self::assertStringStartsWith('native:', $this->minifier->compress('cached'));
        $this->minifier->reset();
        file_put_contents($binary.'.mode', 'crash');

        self::assertSame(['cached', 'missing'], $this->minifier->compressMany(['cached', 'missing']));
        self::assertSame(['cached', 'missing'], $this->minifier->compressMany(['cached', 'missing']));
    }

    #[DataProvider('cacheFailures')]
    public function testCacheFailureDoesNotDiscardMinifiedHtml(bool $failOnRead): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        if ($failOnRead) {
            $cache->expects(self::once())->method('getItem')->willThrowException(new RuntimeException('unavailable cache'));
        } else {
            $cache->expects(self::once())->method('getItem')->willReturn(new ArrayAdapter()->getItem('missing'));
            $cache->expects(self::once())->method('save')->willThrowException(new RuntimeException('unavailable cache'));
        }

        self::assertSame(HtmlMinifier::compress($this->html()), new HtmlMinification(cache: $cache)->compress($this->html()));
    }

    /** @return iterable<string, array{bool}> */
    public static function cacheFailures(): iterable
    {
        yield 'read' => [true];
        yield 'write' => [false];
    }

    #[DataProvider('controlCharacterCacheModes')]
    public function testControlCharactersUsePhpWithoutChangingOtherDocuments(bool $cached): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $this->minifier = new HtmlMinification($this->worker('normal'), logger: $logger, cache: $cached ? new ArrayAdapter() : null);
        $first = $this->minifier->compress('first');
        $pid = explode(':', $first)[1];
        $documents = [];
        foreach (range(0, 31) as $code) {
            $controls = ['&#'.$code.';', '&#x'.dechex($code).';', '&#000'.$code.';', '&#X000'.strtoupper(dechex($code)).';', '&#'.$code.'Z', '&#x'.dechex($code).'Z'];
            if (! \in_array($code, [9, 10, 13], true)) {
                $controls[] = \chr($code);
            }

            foreach ($controls as $control) {
                $text = 'a'.$control.'é';
                $documents[] = '<!DOCTYPE html><html><body><p title="'.$text.'">'.$text.'</p><script>'.$text.'</script></body></html>';
            }
        }

        $normal = ['first'];
        foreach (["\t", "\n", "\r"] as $whitespace) {
            $normal[] = '<!DOCTYPE html><html><body><p>a'.$whitespace.'b</p></body></html>';
        }

        foreach ([32, 39, 65, 128, 256, 0x10FF, 0x1FFF] as $code) {
            foreach (['&#'.$code.';', '&#000'.$code.';', '&#x'.dechex($code).';', '&#X000'.strtoupper(dechex($code)).';'] as $reference) {
                $normal[] = '<!DOCTYPE html><html><body><p>'.$reference.'</p></body></html>';
            }
        }

        $expected = [...array_map(HtmlMinifier::compress(...), $documents), ...array_map(static fn (string $html): string => 'native:'.$pid.':'.$html, $normal)];
        self::assertSame($expected, $this->minifier->compressMany([...$documents, ...$normal]));
        self::assertSame($expected, $this->minifier->compressMany([...$documents, ...$normal]));
    }

    /** @return iterable<string, array{bool}> */
    public static function controlCharacterCacheModes(): iterable
    {
        yield 'uncached' => [false];
        yield 'cached' => [true];
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
