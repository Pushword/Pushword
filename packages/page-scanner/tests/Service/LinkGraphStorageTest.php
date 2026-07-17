<?php

namespace Pushword\PageScanner\Tests\Service;

use Override;
use PHPUnit\Framework\TestCase;
use Pushword\PageScanner\Service\LinkGraphStorage;
use Symfony\Component\Filesystem\Filesystem;

final class LinkGraphStorageTest extends TestCase
{
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

    private function storage(): LinkGraphStorage
    {
        return new LinkGraphStorage(new Filesystem(), $this->varDir);
    }

    public function testReadReturnsNullWhenTheHostWasNeverScanned(): void
    {
        self::assertNull($this->storage()->read('a.tld'));
    }

    public function testWriteThenReadRoundTrip(): void
    {
        $nodes = ['a.tld/homepage', 'a.tld/one'];
        $edges = ['a.tld/homepage' => ['a.tld/one']];

        $this->storage()->write('a.tld', $nodes, $edges);
        $snapshot = $this->storage()->read('a.tld');

        self::assertNotNull($snapshot);
        self::assertSame($nodes, $snapshot['nodes']);
        self::assertSame($edges, $snapshot['edges']);
    }

    public function testGeneratedAtIsTheSnapshotMtime(): void
    {
        $this->storage()->write('a.tld', [], []);
        $snapshot = $this->storage()->read('a.tld');

        self::assertNotNull($snapshot);
        self::assertSame(filemtime($this->varDir.'/page-scan-graph--a.tld'), $snapshot['generatedAt']);
    }

    public function testWriteOverwritesThePreviousSnapshot(): void
    {
        $this->storage()->write('a.tld', ['a.tld/old'], []);
        $this->storage()->write('a.tld', ['a.tld/new'], []);

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

    public function testWriteAllOfNothingWritesNothing(): void
    {
        $this->storage()->writeAll([], []);

        self::assertSame([], glob($this->varDir.'/page-scan-graph*'));
    }

    public function testTheSnapshotIsInspectableJson(): void
    {
        $this->storage()->write('a.tld', ['a.tld/homepage'], ['a.tld/homepage' => ['a.tld/one']]);

        $raw = file_get_contents($this->varDir.'/page-scan-graph--a.tld');

        self::assertIsString($raw);
        self::assertJson($raw);
        self::assertStringContainsString('a.tld/homepage', $raw, 'slashes stay unescaped so the file reads by hand');
    }
}
