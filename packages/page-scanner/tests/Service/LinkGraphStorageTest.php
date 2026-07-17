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

    public function testReadReturnsNullWhenNeverScanned(): void
    {
        self::assertNull($this->storage()->read(null));
        self::assertNull($this->storage()->read('a.tld'));
    }

    public function testWriteThenReadRoundTrip(): void
    {
        $nodes = ['a.tld/homepage', 'a.tld/one'];
        $edges = ['a.tld/homepage' => ['a.tld/one']];

        $this->storage()->write(null, $nodes, $edges);
        $snapshot = $this->storage()->read(null);

        self::assertNotNull($snapshot);
        self::assertSame($nodes, $snapshot['nodes']);
        self::assertSame($edges, $snapshot['edges']);
    }

    public function testGeneratedAtIsTheSnapshotMtime(): void
    {
        $this->storage()->write(null, [], []);
        $snapshot = $this->storage()->read(null);

        self::assertNotNull($snapshot);
        self::assertSame(filemtime($this->varDir.'/page-scan-graph'), $snapshot['generatedAt']);
    }

    public function testEachHostKeepsItsOwnSnapshot(): void
    {
        $this->storage()->write(null, ['a.tld/homepage', 'b.tld/homepage'], []);
        $this->storage()->write('a.tld', ['a.tld/homepage'], []);

        $all = $this->storage()->read(null);
        $scoped = $this->storage()->read('a.tld');

        self::assertNotNull($all);
        self::assertNotNull($scoped);
        self::assertSame(['a.tld/homepage', 'b.tld/homepage'], $all['nodes'], 'a per-host scan must not overwrite the all-hosts snapshot');
        self::assertSame(['a.tld/homepage'], $scoped['nodes']);
        self::assertNull($this->storage()->read('b.tld'), 'a host never scanned on its own has no snapshot');
    }

    public function testAnEmptyHostIsTheAllHostsScope(): void
    {
        $this->storage()->write(null, ['a.tld/homepage'], []);

        $snapshot = $this->storage()->read('');

        self::assertNotNull($snapshot);
        self::assertSame(['a.tld/homepage'], $snapshot['nodes']);
    }

    public function testWriteOverwritesThePreviousSnapshot(): void
    {
        $this->storage()->write('a.tld', ['a.tld/old'], []);
        $this->storage()->write('a.tld', ['a.tld/new'], []);

        $snapshot = $this->storage()->read('a.tld');

        self::assertNotNull($snapshot);
        self::assertSame(['a.tld/new'], $snapshot['nodes']);
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
