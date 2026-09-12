<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;
use Pushword\Core\Component\EntityFilter\ValueObject\SplitContent;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\ContentSplitter;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Filesystem\Filesystem;

final class ContentSplitterTest extends TestCase
{
    private string $directory;

    private ?ContentSplitter $splitter = null;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/pushword-split-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->splitter?->reset();
        new Filesystem()->remove($this->directory);
    }

    public function testDefaultAndMissingBinaryPreservePhp(): void
    {
        $page = new Page();
        $page->setCustomProperty('toc', true);

        $html = '<p>Intro.</p><h2>Title</h2><p>Text.</p>';
        $expected = new SplitContent($html, $page);
        self::assertSame((string) $expected, (string) new ContentSplitter()->split($html, $page));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $this->splitter = new ContentSplitter($this->directory.'/missing', logger: $logger);
        self::assertSame([], $this->splitter->splitMany([]));
        for ($i = 0; $i < 2; ++$i) {
            $actual = $this->splitter->split($html, $page);
            self::assertSame((string) $expected, (string) $actual);
            self::assertSame($expected->getToc(), $actual->getToc());
        }
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidAnalysisFallsBackUntilReset(mixed $payload): void
    {
        $binary = $this->worker($payload);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $this->splitter = new ContentSplitter($binary, logger: $logger);
        $page = new Page();
        self::assertSame(['Text.'], $this->splitter->split('<p>Text.</p>', $page)->getParagraphs());
        $this->worker($this->payload());
        self::assertSame(['Text.'], $this->splitter->split('<p>Text.</p>', $page)->getParagraphs());
        self::assertSame("call\n", file_get_contents($binary.'.calls'));
        $this->splitter->reset();
        self::assertSame(['Prepared.'], $this->splitter->split('<p>Text.</p>', $page)->getParagraphs());
        self::assertSame("call\ncall\n", file_get_contents($binary.'.calls'));
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidPayloads(): iterable
    {
        yield 'scalar' => ['wrong'];
        yield 'list' => [[]];
        yield 'missing field' => [(object) ['chapeau' => '']];
        $base = ['chapeau' => '', 'segments' => [''], 'headings' => [], 'paragraphs' => [], 'paragraphs_with_chapeau' => []];
        yield 'bad segments' => [(object) array_replace($base, ['segments' => [false]])];
        yield 'missing slot' => [(object) array_replace($base, ['segments' => []])];
        yield 'bad paragraph' => [(object) array_replace($base, ['paragraphs' => [null]])];
        yield 'object paragraph list' => [(object) array_replace($base, ['paragraphs' => (object) ['0' => 'x']])];
        yield 'bad heading' => [(object) array_replace($base, ['segments' => ['', ''], 'headings' => [['seed' => 'x', 'label' => 'x', 'level' => 7, 'listed' => true]]])];
    }

    public function testOneRequestForBatchAndCacheIncludesTocFlag(): void
    {
        $binary = $this->worker($this->payload());
        $pool = new ArrayAdapter();
        $this->splitter = new ContentSplitter($binary, cache: $pool);
        $page = new Page();
        $documents = [['html' => '<p>A.</p>', 'page' => $page], ['html' => '<p>B.</p>', 'page' => $page]];
        self::assertCount(2, $this->splitter->splitMany($documents));
        self::assertSame("call\n", file_get_contents($binary.'.calls'));
        $this->splitter->reset();
        self::assertSame(['Prepared.'], $this->splitter->split('<p>A.</p>', $page)->getParagraphs());
        self::assertSame("call\n", file_get_contents($binary.'.calls'));
        $page->setCustomProperty('toc', false); // Presence, even false, enables the legacy TOC pass.
        $this->splitter->split('<p>A.</p>', $page);
        self::assertSame("call\ncall\n", file_get_contents($binary.'.calls'));
    }

    public function testDeclinedInputIsCachedAndDoesNotDisableWorker(): void
    {
        $binary = $this->worker(null);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $this->splitter = new ContentSplitter($binary, cache: new ArrayAdapter(), logger: $logger);
        $page = new Page();
        self::assertSame(['Text.'], $this->splitter->split('<p>Text.</p>', $page)->getParagraphs());
        $this->worker($this->payload());
        self::assertSame(['Text.'], $this->splitter->split('<p>Text.</p>', $page)->getParagraphs());
        self::assertSame(['Prepared.'], $this->splitter->split('<p>Other.</p>', $page)->getParagraphs());
        self::assertSame("call\ncall\n", file_get_contents($binary.'.calls'));
    }

    public function testBrokenCacheStillUsesComputedAnalysis(): void
    {
        $pool = new class extends ArrayAdapter {
            public function getItem(mixed $key): CacheItem
            {
                throw new RuntimeException('cache unavailable');
            }
        };
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $this->splitter = new ContentSplitter($this->worker($this->payload()), cache: $pool, logger: $logger);
        self::assertSame(['Prepared.'], $this->splitter->split('<p>Text.</p>', new Page())->getParagraphs());
    }

    public function testFailedCacheWriteDoesNotDisableWorker(): void
    {
        $pool = new class extends ArrayAdapter {
            public function save(CacheItemInterface $item): bool
            {
                throw new RuntimeException('cache write unavailable');
            }
        };
        $binary = $this->worker($this->payload());
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $this->splitter = new ContentSplitter($binary, cache: $pool, logger: $logger);
        for ($i = 0; $i < 2; ++$i) {
            self::assertSame(['Prepared.'], $this->splitter->split('<p>Text.</p>', new Page())->getParagraphs());
        }

        self::assertSame("call\ncall\n", file_get_contents($binary.'.calls'));
    }

    public function testInvalidBatchDoesNotReturnOrCachePartialResults(): void
    {
        $binary = $this->worker(['batch' => [$this->payload(), false]]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $this->splitter = new ContentSplitter($binary, cache: new ArrayAdapter(), logger: $logger);
        $page = new Page();
        $documents = [['html' => '<p>A.</p>', 'page' => $page], ['html' => '<p>B.</p>', 'page' => $page]];
        $actual = $this->splitter->splitMany($documents);
        self::assertSame(['A.'], $actual[0]->getParagraphs());
        self::assertSame(['B.'], $actual[1]->getParagraphs());
        $this->worker($this->payload());
        $this->splitter->reset();
        self::assertSame(['Prepared.'], $this->splitter->split('<p>A.</p>', $page)->getParagraphs());
        self::assertSame("call\ncall\n", file_get_contents($binary.'.calls'));
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['chapeau' => '', 'segments' => ['<p>Text.</p>'], 'headings' => [], 'paragraphs' => ['Prepared.'], 'paragraphs_with_chapeau' => ['Prepared.']];
    }

    private function worker(mixed $payload): string
    {
        $binary = $this->directory."/worker ' $ &";
        if (! is_file($binary)) {
            copy(__DIR__.'/fixtures/split-worker.php', $binary);
            chmod($binary, 0700);
        }

        file_put_contents($binary.'.response', json_encode($payload, \JSON_THROW_ON_ERROR));

        return $binary;
    }
}
