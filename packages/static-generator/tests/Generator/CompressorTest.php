<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\Generator;

use PHPUnit\Framework\TestCase;
use Pushword\StaticGenerator\Generator\CompressionAlgorithm;
use Pushword\StaticGenerator\Generator\Compressor;
use Symfony\Component\Filesystem\Filesystem;

final class CompressorTest extends TestCase
{
    private string $tempDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir().'/pushword-compressor-test-'.uniqid();
        $this->filesystem->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if ($this->filesystem->exists($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }
    }

    public function testConstructorDetectsAvailableCompressors(): void
    {
        $compressor = new Compressor();

        foreach ($compressor->availableCompressors as $algorithm) {
            self::assertContains($algorithm, CompressionAlgorithm::cases());
        }
    }

    public function testCompressWithUnavailableAlgorithmDoesNothing(): void
    {
        // Create a compressor that only knows about Gzip, then compress with Brotli
        $compressor = new Compressor([CompressionAlgorithm::Gzip]);

        $testFile = $this->tempDir.'/test.txt';
        $this->filesystem->dumpFile($testFile, 'Test content');

        $compressor->compress($testFile, CompressionAlgorithm::Brotli);
        $compressor->waitForCompressionToFinish();

        self::assertFileDoesNotExist($testFile.CompressionAlgorithm::Brotli->getExtension());
        self::assertFileExists($testFile);
    }

    public function testCompressCreatesCompressedFile(): void
    {
        $compressor = $this->compressorWithAvailableAlgorithm();

        // Repeated content makes the compression result deterministic.
        $testFile = $this->tempDir.'/test.html';
        $content = str_repeat('<p>Test content for compression</p>', 100);
        $this->filesystem->dumpFile($testFile, $content);

        foreach ($compressor->availableCompressors as $algorithm) {
            $compressor->compress($testFile, $algorithm);
            $compressor->waitForCompressionToFinish();

            $compressedFile = $testFile.$algorithm->getExtension();
            self::assertFileExists($compressedFile, \sprintf('The file compressed with %s should exist', $algorithm->value));
            self::assertGreaterThan(0, filesize($compressedFile), \sprintf('The file compressed with %s should not be empty', $algorithm->value));

            $this->filesystem->remove($compressedFile);
        }
    }

    public function testCompressedFileIsSmallerThanOriginal(): void
    {
        $compressor = $this->compressorWithAvailableAlgorithm();

        $testFile = $this->dumpRepeatedHtmlPage('large-test.html');
        $originalSize = filesize($testFile);

        foreach ($compressor->availableCompressors as $algorithm) {
            $compressor->compress($testFile, $algorithm);
            $compressor->waitForCompressionToFinish();

            $compressedFile = $testFile.$algorithm->getExtension();
            $compressedSize = filesize($compressedFile);

            self::assertLessThan(
                $originalSize,
                $compressedSize,
                \sprintf('The file compressed with %s should be smaller than the original', $algorithm->value)
            );

            $this->filesystem->remove($compressedFile);
        }
    }

    /**
     * The generated server configs offer brotli before zstd, which only pays off while
     * the brotli sidecar stays the smaller of the two. They are not written at comparable
     * effort: brotli runs at its CLI default quality 11, zstd at its default level 3
     * (`--zstd=wlog=23` only caps the window). Lowering the brotli quality or raising the
     * zstd level inverts that premise, and the configs then serve the larger variant to
     * every client advertising zstd.
     */
    public function testBrotliSidecarIsNotLargerThanTheZstdOne(): void
    {
        $compressor = $this->compressorWithAvailableAlgorithm();
        $page = $this->dumpRepeatedHtmlPage('negotiation-order.html');

        $brotliSize = $this->compressAndMeasureSidecar($compressor, $page, CompressionAlgorithm::Brotli);
        $zstdSize = $this->compressAndMeasureSidecar($compressor, $page, CompressionAlgorithm::Zstd);

        if (null === $brotliSize || null === $zstdSize) {
            // Only one of the two binaries is installed here, so there is nothing to
            // compare; the sidecar that was written is already measured above.
            return;
        }

        self::assertLessThanOrEqual(
            $zstdSize,
            $brotliSize,
            'Brotli is offered first (`encode br zstd gzip`), so its sidecar has to stay the smaller one.',
        );
    }

    public function testMultipleCompressionProcessesRunInParallel(): void
    {
        $compressor = $this->compressorWithAvailableAlgorithm();

        // Create several inputs so the processes overlap.
        $files = [];
        for ($i = 1; $i <= 3; ++$i) {
            $testFile = $this->tempDir.\sprintf('/test%d.html', $i);
            $content = str_repeat(\sprintf('<p>Test content %d</p>', $i), 100);
            $this->filesystem->dumpFile($testFile, $content);
            $files[] = $testFile;
        }

        // Start every compression before waiting for any of them.
        $algorithm = $compressor->availableCompressors[0];
        foreach ($files as $file) {
            $compressor->compress($file, $algorithm);
        }

        $compressor->waitForCompressionToFinish();

        foreach ($files as $file) {
            self::assertFileExists($file.$algorithm->getExtension());
        }
    }

    public function testDestructorWaitsForCompressionToFinish(): void
    {
        $compressor = $this->compressorWithAvailableAlgorithm();

        $testFile = $this->tempDir.'/destructor-test.html';
        $content = str_repeat('<p>Test content</p>', 100);
        $this->filesystem->dumpFile($testFile, $content);

        $algorithm = $compressor->availableCompressors[0];

        $compressor->compress($testFile, $algorithm);

        // Destroy the compressor explicitly to invoke its destructor.
        unset($compressor);

        self::assertFileExists($testFile.$algorithm->getExtension());
    }

    public function testFileSuffixesCoversPrimaryAndEveryAlgorithm(): void
    {
        $suffixes = CompressionAlgorithm::fileSuffixes();

        self::assertSame('', $suffixes[0], 'The primary (uncompressed) file must come first.');
        self::assertCount(\count(CompressionAlgorithm::cases()) + 1, $suffixes);

        foreach (CompressionAlgorithm::cases() as $algorithm) {
            self::assertContains($algorithm->getExtension(), $suffixes);
        }
    }

    public function testZstdNativeCompressionIsDisabled(): void
    {
        // PHP's zstd_compress() doesn't allow controlling window size,
        // producing files with 128MB window that browsers reject.
        // Native zstd must be disabled so the CLI path is used instead.
        self::assertNull(CompressionAlgorithm::Zstd->nativeCompress('test content'));
        self::assertFalse(CompressionAlgorithm::Zstd->hasNativeSupport());
    }

    public function testWaitForCompressionCanBeCalledMultipleTimes(): void
    {
        $compressor = $this->compressorWithAvailableAlgorithm();

        $testFile = $this->tempDir.'/test.html';
        $content = str_repeat('<p>Test</p>', 50);
        $this->filesystem->dumpFile($testFile, $content);

        $algorithm = $compressor->availableCompressors[0];
        $compressor->compress($testFile, $algorithm);

        $compressor->waitForCompressionToFinish();
        $compressor->waitForCompressionToFinish();
        $compressor->waitForCompressionToFinish();

        self::assertFileExists($testFile.$algorithm->getExtension());
    }

    /**
     * Compresses the page and asserts the sidecar is smaller than it.
     *
     * @return int|null the sidecar's size, or null when that compressor is not
     *                  installed on this host
     */
    private function compressAndMeasureSidecar(Compressor $compressor, string $page, CompressionAlgorithm $algorithm): ?int
    {
        if (! \in_array($algorithm, $compressor->availableCompressors, true)) {
            return null;
        }

        $compressor->compress($page, $algorithm);
        $compressor->waitForCompressionToFinish();

        $size = (int) filesize($page.$algorithm->getExtension());
        self::assertLessThan(
            (int) filesize($page),
            $size,
            \sprintf('The %s sidecar should be smaller than the page it compresses.', $algorithm->value),
        );

        return $size;
    }

    /**
     * Dumps a page made of repeated HTML, so it always compresses well and every
     * algorithm gets the same redundancy to work with.
     *
     * @return string the path of the dumped page
     */
    private function dumpRepeatedHtmlPage(string $fileName): string
    {
        $content = '<!DOCTYPE html><html><head><title>Test</title></head><body>';
        $content .= str_repeat('<div class="content"><p>This is a test paragraph with repeated content.</p></div>', 500);
        $content .= '</body></html>';

        $page = $this->tempDir.'/'.$fileName;
        $this->filesystem->dumpFile($page, $content);

        return $page;
    }

    private function compressorWithAvailableAlgorithm(): Compressor
    {
        $compressor = new Compressor();
        self::assertNotEmpty(
            $compressor->availableCompressors,
            'The test environment must provide at least one supported compression algorithm.',
        );

        return $compressor;
    }
}
