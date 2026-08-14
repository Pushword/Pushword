<?php

namespace Pushword\Core\Tests\Image;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Image\ImageScratchFile;
use Pushword\Core\Image\ImageScratchSweeper;
use Symfony\Component\Filesystem\Filesystem;

final class ImageScratchSweeperTest extends TestCase
{
    private string $mediaDir;

    private string $mediaCacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir().'/pw-scratch-sweeper-'.getmypid().'-'.uniqid();
        $this->mediaDir = $base.'/media';
        $this->mediaCacheDir = $base.'/media-cache';

        new Filesystem()->mkdir([$this->mediaDir, $this->mediaCacheDir.'/md']);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname($this->mediaDir));

        parent::tearDown();
    }

    /**
     * Both trees, and the cache's per-filter subdirectories: a scratch sits beside
     * whatever its writer produces, so md/ holds them as surely as the root does.
     */
    public function testSweepsStaleScratchFilesFromBothTrees(): void
    {
        $stale = [
            $this->mediaDir.'/photo.jpg.opt-4242.abcdef123456.tmp',
            $this->mediaCacheDir.'/photo.webp.enc-4242.abcdef123456.tmp',
            $this->mediaCacheDir.'/md/photo.webp.4242.abcdef123456.tmp',
        ];

        foreach ($stale as $path) {
            $this->writeAged($path, 'half-written');
        }

        $result = $this->sweeper()->sweep();

        self::assertSame(3, $result['swept']);
        foreach ($stale as $path) {
            self::assertFileDoesNotExist($path);
        }
    }

    /**
     * The age bound is what makes the sweep safe to run at any moment: a writer
     * encoding right now owns a scratch seconds old, and losing it under them would
     * turn a housekeeping pass into the corruption it exists to prevent.
     */
    public function testLeavesAFreshScratchToItsWriter(): void
    {
        $fresh = ImageScratchFile::pathFor($this->mediaCacheDir.'/md/photo.webp', 'enc');
        file_put_contents($fresh, 'being written');

        $result = $this->sweeper()->sweep();

        self::assertSame(0, $result['swept']);
        self::assertFileExists($fresh);
    }

    public function testLeavesRealMediaAlone(): void
    {
        $real = [
            $this->mediaDir.'/photo.jpg',
            $this->mediaCacheDir.'/md/photo.webp',
            $this->mediaCacheDir.'/notes.tmp',
        ];

        foreach ($real as $path) {
            $this->writeAged($path, 'real bytes');
        }

        $result = $this->sweeper()->sweep();

        self::assertSame(0, $result['swept']);
        foreach ($real as $path) {
            self::assertFileExists($path);
        }
    }

    /**
     * The empty count is the point of reporting at all: it is the only surviving
     * trace that the encoder still emits blank payloads and that the promotion
     * guard intercepts them. A sweep that erased it without counting would destroy
     * the evidence.
     */
    public function testCountsTheEmptyOnesSeparately(): void
    {
        $this->writeAged($this->mediaCacheDir.'/md/a.webp.enc-4242.abcdef123456.tmp', '');
        $this->writeAged($this->mediaCacheDir.'/md/b.webp.enc-4242.abcdef123457.tmp', '');
        $this->writeAged($this->mediaCacheDir.'/md/c.webp.enc-4242.abcdef123458.tmp', 'half-written');

        $result = $this->sweeper()->sweep();

        self::assertSame(3, $result['swept']);
        self::assertSame(2, $result['empty']);
    }

    /**
     * A site is free to point both settings at one directory; walking it twice
     * would be pure waste, and counting it twice would be a lie.
     */
    public function testWalksASharedDirectoryOnce(): void
    {
        $this->writeAged($this->mediaDir.'/photo.webp.enc-4242.abcdef123456.tmp', 'half-written');

        $result = new ImageScratchSweeper($this->mediaDir, $this->mediaDir)->sweep();

        self::assertSame(1, $result['swept']);
    }

    public function testToleratesAMissingDirectory(): void
    {
        $result = new ImageScratchSweeper($this->mediaDir.'/nope', $this->mediaCacheDir)->sweep();

        self::assertSame(0, $result['swept']);
    }

    private function sweeper(): ImageScratchSweeper
    {
        return new ImageScratchSweeper($this->mediaDir, $this->mediaCacheDir);
    }

    /** Old enough that no live writer could own it. */
    private function writeAged(string $path, string $content): void
    {
        file_put_contents($path, $content);
        touch($path, time() - ImageScratchFile::MAX_AGE - 60);
    }
}
