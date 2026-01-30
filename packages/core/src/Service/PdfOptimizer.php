<?php

namespace Pushword\Core\Service;

use Psr\Log\LoggerInterface;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Entity\Media;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class PdfOptimizer
{
    private ?string $ghostscriptPath = null;

    private ?string $qpdfPath = null;

    public function __construct(
        private readonly MediaStorageAdapter $mediaStorage,
        private readonly BackgroundTaskDispatcherInterface $backgroundTaskDispatcher,
        private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem = new Filesystem(),
        private readonly string $pdfPreset = 'ebook',
        private readonly bool $pdfLinearize = true,
    ) {
        $finder = new ExecutableFinder();
        $this->ghostscriptPath = $finder->find('gs');
        $this->qpdfPath = $finder->find('qpdf');
    }

    public function isGhostscriptAvailable(): bool
    {
        return null !== $this->ghostscriptPath;
    }

    public function isQpdfAvailable(): bool
    {
        return null !== $this->qpdfPath;
    }

    public function isAvailable(): bool
    {
        if ($this->isGhostscriptAvailable()) {
            return true;
        }

        return $this->isQpdfAvailable();
    }

    /**
     * Optimize a PDF file (compress with Ghostscript, linearize with qpdf).
     *
     * @return bool True if file was optimized, false if skipped or failed
     */
    public function optimize(Media $media, bool $force = false): bool
    {
        if ('application/pdf' !== $media->getMimeType()) {
            return false;
        }

        if (! $this->isAvailable()) {
            $this->logger->warning('PDF optimization skipped: neither gs nor qpdf available');

            return false;
        }

        $localPath = $this->mediaStorage->getLocalPath($media->getFileName());
        if (! $this->filesystem->exists($localPath)) {
            $this->logger->error('PDF optimization failed: file not found', ['file' => $localPath]);

            return false;
        }

        $originalSize = filesize($localPath);
        if (false === $originalSize) {
            return false;
        }

        $optimizedPath = $this->processFile($localPath);
        if (null === $optimizedPath) {
            return false;
        }

        $newSize = filesize($optimizedPath);
        if (false === $newSize || $newSize >= $originalSize) {
            $this->logger->info('PDF optimization: compressed file not smaller, keeping original', [
                'file' => $media->getFileName(),
                'original' => $this->formatBytes($originalSize),
                'compressed' => $this->formatBytes($newSize ?: 0),
            ]);
            $this->filesystem->remove($optimizedPath);

            return false;
        }

        // Replace original with optimized version
        try {
            $this->filesystem->rename($optimizedPath, $localPath, true);
        } catch (IOException) {
            $this->logger->error('PDF optimization failed: could not replace original file');
            $this->filesystem->remove($optimizedPath);

            return false;
        }

        // Update media size
        $media->setSize($newSize);

        $reduction = round((1 - $newSize / $originalSize) * 100, 1);
        $this->logger->info('PDF optimized successfully', [
            'file' => $media->getFileName(),
            'original' => $this->formatBytes($originalSize),
            'compressed' => $this->formatBytes($newSize),
            'reduction' => $reduction.'%',
        ]);

        return true;
    }

    /**
     * Process file through Ghostscript and/or qpdf.
     *
     * @return string|null Path to optimized file, or null on failure
     */
    private function processFile(string $inputPath): ?string
    {
        $tempDir = sys_get_temp_dir();
        $currentPath = $inputPath;
        $tempFiles = [];

        // Step 1: Ghostscript compression
        if ($this->isGhostscriptAvailable()) {
            $gsOutput = $tempDir.'/'.uniqid('pdf_gs_', true).'.pdf';
            $tempFiles[] = $gsOutput;

            if ($this->compressWithGhostscript($currentPath, $gsOutput)) {
                $currentPath = $gsOutput;
            }
        }

        // Step 2: qpdf linearization (for web streaming)
        if ($this->pdfLinearize && $this->isQpdfAvailable()) {
            $qpdfOutput = $tempDir.'/'.uniqid('pdf_qpdf_', true).'.pdf';
            $tempFiles[] = $qpdfOutput;

            if ($this->linearizeWithQpdf($currentPath, $qpdfOutput)) {
                $currentPath = $qpdfOutput;
            }
        }

        // Clean up intermediate temp files (keep only final result)
        foreach ($tempFiles as $tempFile) {
            if ($tempFile !== $currentPath) {
                $this->filesystem->remove($tempFile);
            }
        }

        // Return null if no processing was done
        if ($currentPath === $inputPath) {
            return null;
        }

        return $currentPath;
    }

    private function compressWithGhostscript(string $inputPath, string $outputPath): bool
    {
        if (null === $this->ghostscriptPath) {
            return false;
        }

        $validPresets = ['screen', 'ebook', 'printer', 'prepress'];
        $preset = \in_array($this->pdfPreset, $validPresets, true) ? $this->pdfPreset : 'ebook';

        $process = new Process([
            $this->ghostscriptPath,
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4',
            '-dPDFSETTINGS=/'.$preset,
            '-dNOPAUSE',
            '-dQUIET',
            '-dBATCH',
            '-sOutputFile='.$outputPath,
            $inputPath,
        ]);
        $process->setTimeout(300); // 5 minutes max
        $process->run();

        if (! $process->isSuccessful() || ! $this->filesystem->exists($outputPath)) {
            $this->logger->warning('Ghostscript compression failed', [
                'error' => $process->getErrorOutput(),
            ]);

            return false;
        }

        return true;
    }

    private function linearizeWithQpdf(string $inputPath, string $outputPath): bool
    {
        if (null === $this->qpdfPath) {
            return false;
        }

        $process = new Process([
            $this->qpdfPath,
            '--linearize',
            $inputPath,
            $outputPath,
        ]);
        $process->setTimeout(120); // 2 minutes max
        $process->run();

        if (! $process->isSuccessful() || ! $this->filesystem->exists($outputPath)) {
            $this->logger->warning('qpdf linearization failed', [
                'error' => $process->getErrorOutput(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Run PDF optimization in background.
     */
    public function runBackgroundOptimization(string $fileName): void
    {
        $this->backgroundTaskDispatcher->dispatch(
            'pdf-optimize-'.md5($fileName),
            ['php', 'bin/console', 'pw:pdf:optimize', $fileName],
            'pw:pdf:optimize',
        );
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
