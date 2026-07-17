<?php

namespace Pushword\PageScanner\Tests\Service;

use Override;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Repository\PageRepository;
use Pushword\PageScanner\Service\LinkGraphStorage;
use Symfony\Component\Filesystem\Filesystem;

final class LinkGraphStorageTest extends TestCase
{
    /** @var array{pages: int, lastEditAt: int|null} */
    private const array CORPUS = ['pages' => 2, 'lastEditAt' => 1750000000];

    private string $varDir;

    #[Override]
    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/pushword-link-graph-test-'.uniqid();
        new Filesystem()->mkdir($this->varDir);
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    /**
     * @param array{pages: int, lastEditAt: int|null} $currentCorpus what the database looks like right now
     */
    private function storage(array $currentCorpus = self::CORPUS): LinkGraphStorage
    {
        $pageRepo = $this->createMock(PageRepository::class);
        $pageRepo->method('getPublishedCorpusState')->willReturn($currentCorpus);

        return new LinkGraphStorage(new Filesystem(), $pageRepo, $this->varDir);
    }

    public function testReadReturnsNullWhenTheHostWasNeverScanned(): void
    {
        self::assertNull($this->storage()->read('a.tld'));
    }

    public function testWriteThenReadRoundTrip(): void
    {
        $nodes = ['a.tld/homepage', 'a.tld/one'];
        $edges = ['a.tld/homepage' => ['a.tld/one']];

        $this->storage()->write('a.tld', $nodes, $edges, self::CORPUS);
        $snapshot = $this->storage()->read('a.tld');

        self::assertNotNull($snapshot);
        self::assertSame($nodes, $snapshot['nodes']);
        self::assertSame($edges, $snapshot['edges']);
    }

    public function testGeneratedAtIsTheSnapshotMtime(): void
    {
        $this->storage()->write('a.tld', [], [], self::CORPUS);
        $snapshot = $this->storage()->read('a.tld');

        self::assertNotNull($snapshot);
        self::assertSame(filemtime($this->varDir.'/page-scan-graph--a.tld'), $snapshot['generatedAt']);
    }

    public function testASnapshotOfTheCurrentCorpusIsFresh(): void
    {
        $this->storage()->write('a.tld', ['a.tld/homepage'], [], self::CORPUS);

        $snapshot = $this->storage()->read('a.tld');

        self::assertNotNull($snapshot);
        self::assertFalse($snapshot['stale']);
    }

    public function testAnEditedPageMakesTheSnapshotStale(): void
    {
        $this->storage()->write('a.tld', ['a.tld/homepage'], [], self::CORPUS);

        // Same pages, one of them edited since: every inbound count is now suspect,
        // because a page's links change when OTHER pages are edited.
        $snapshot = $this->storage(['pages' => 2, 'lastEditAt' => 1750000001])->read('a.tld');

        self::assertNotNull($snapshot);
        self::assertTrue($snapshot['stale']);
    }

    public function testADeletedPageMakesTheSnapshotStale(): void
    {
        $this->storage()->write('a.tld', ['a.tld/homepage'], [], self::CORPUS);

        // Nothing was edited — a removal moves no timestamp — which is exactly why
        // the count is half of the state.
        $snapshot = $this->storage(['pages' => 1, 'lastEditAt' => 1750000000])->read('a.tld');

        self::assertNotNull($snapshot);
        self::assertTrue($snapshot['stale']);
    }

    public function testASnapshotFromBeforeTheCorpusWasRecordedIsStale(): void
    {
        // Written by an older Pushword: it describes a corpus it cannot name, and
        // rescanning once is the only honest reading.
        new Filesystem()->dumpFile(
            $this->varDir.'/page-scan-graph--a.tld',
            (string) json_encode(['nodes' => ['a.tld/homepage'], 'edges' => []]),
        );

        $snapshot = $this->storage()->read('a.tld');

        self::assertNotNull($snapshot);
        self::assertTrue($snapshot['stale']);
    }

    public function testWriteOverwritesThePreviousSnapshot(): void
    {
        $this->storage()->write('a.tld', ['a.tld/old'], [], self::CORPUS);
        $this->storage()->write('a.tld', ['a.tld/new'], [], self::CORPUS);

        $snapshot = $this->storage()->read('a.tld');

        self::assertNotNull($snapshot);
        self::assertSame(['a.tld/new'], $snapshot['nodes']);
    }

    public function testWriteAllSplitsAnAllHostsScanIntoOneSnapshotPerHost(): void
    {
        // An all-hosts `pw:page-scan` must leave every host readable on its own,
        // otherwise `pw:link:graph <host>` would rescan what was just rendered.
        $this->storage()->writeAll(
            ['a.tld/homepage', 'a.tld/one', 'b.tld/homepage'],
            [
                'a.tld/homepage' => ['a.tld/one'],
                'b.tld/homepage' => ['b.tld/two'],
            ],
            ['a.tld' => self::CORPUS, 'b.tld' => self::CORPUS],
        );

        $a = $this->storage()->read('a.tld');
        $b = $this->storage()->read('b.tld');

        self::assertNotNull($a);
        self::assertNotNull($b);
        self::assertSame(['a.tld/homepage', 'a.tld/one'], $a['nodes']);
        self::assertSame(['b.tld/homepage'], $b['nodes']);
        self::assertSame(['a.tld/homepage' => ['a.tld/one']], $a['edges'], "a host's snapshot carries only the edges leaving it");
        self::assertSame(['b.tld/homepage' => ['b.tld/two']], $b['edges']);
    }

    public function testWriteAllGivesEachHostItsOwnCorpusState(): void
    {
        // A host is scanned against its own corpus: b.tld going stale must not drag
        // a.tld's graph down with it.
        $this->storage()->writeAll(
            ['a.tld/homepage', 'b.tld/homepage'],
            [],
            ['a.tld' => self::CORPUS, 'b.tld' => ['pages' => 9, 'lastEditAt' => 1750000009]],
        );

        $a = $this->storage()->read('a.tld');
        $b = $this->storage()->read('b.tld');

        self::assertNotNull($a);
        self::assertNotNull($b);
        self::assertFalse($a['stale']);
        self::assertTrue($b['stale'], 'b.tld was written against a corpus that is not the one the mock reports');
    }

    public function testWriteAllOfNothingWritesNothing(): void
    {
        $this->storage()->writeAll([], [], []);

        self::assertSame([], glob($this->varDir.'/page-scan-graph*'));
    }

    public function testTheSnapshotIsInspectableJson(): void
    {
        $this->storage()->write('a.tld', ['a.tld/homepage'], ['a.tld/homepage' => ['a.tld/one']], self::CORPUS);

        $raw = file_get_contents($this->varDir.'/page-scan-graph--a.tld');

        self::assertIsString($raw);
        self::assertJson($raw);
        self::assertStringContainsString('a.tld/homepage', $raw, 'slashes stay unescaped so the file reads by hand');
    }
}
