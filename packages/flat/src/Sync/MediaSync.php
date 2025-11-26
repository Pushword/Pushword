<?php

namespace Pushword\Flat\Sync;

use DateTime;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Flat\Exporter\MediaExporter;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Importer\MediaImporter;

use function Safe\filemtime;
use function Safe\scandir;

use Symfony\Component\Stopwatch\Stopwatch;

final readonly class MediaSync
{
    public function __construct(
        private AppPool $apps,
        private FlatFileContentDirFinder $contentDirFinder,
        private MediaImporter $mediaImporter,
        private MediaExporter $mediaExporter,
        private MediaRepository $mediaRepository,
        private string $mediaDir,
        private ?Stopwatch $stopwatch = null,
    ) {
    }

    public function sync(?string $host = null, bool $forceExport = false, ?string $exportDir = null): void
    {
        if (! $forceExport && $this->mustImport($host)) {
            $this->import($host);

            return;
        }

        $this->export($host, $forceExport, $exportDir);
    }

    public function import(?string $host = null): void
    {
        $app = $this->resolveApp($host);
        $contentDir = $this->contentDirFinder->get($app->getMainHost());

        $this->measure('media_import', function () use ($contentDir): void {
            $this->importDirectory($this->mediaDir);
            $this->mediaImporter->finishImport();

            $localMediaDir = $contentDir.'/media';
            $this->importDirectory($localMediaDir);
            $this->mediaImporter->finishImport();
        });
    }

    public function export(?string $host = null, bool $force = false, ?string $exportDir = null): void
    {
        $app = $this->resolveApp($host);
        $targetDir = $exportDir ?? $this->contentDirFinder->get($app->getMainHost());

        $this->measure('media_export', function () use ($targetDir): void {
            $this->mediaExporter->exportDir = $targetDir;
            $this->mediaExporter->exportMedias();
        });
    }

    public function mustImport(?string $host = null): bool
    {
        $app = $this->resolveApp($host);
        $contentDir = $this->contentDirFinder->get($app->getMainHost());

        foreach ($this->getDirectoriesToScan($contentDir) as $directory) {
            if ($this->hasNewerFiles($directory)) {
                return true;
            }
        }

        return false;
    }

    private function importDirectory(string $dir): void
    {
        if (! file_exists($dir)) {
            return;
        }

        /** @var string[] $files */
        $files = scandir($dir);
        foreach ($files as $file) {
            if (\in_array($file, ['.', '..'], true)) {
                continue;
            }

            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->importDirectory($path);

                continue;
            }

            $lastEditDateTime = (new DateTime())->setTimestamp(filemtime($path));
            $this->mediaImporter->import($path, $lastEditDateTime);
        }
    }

    /**
     * @return string[]
     */
    private function getDirectoriesToScan(string $contentDir): array
    {
        return array_values(array_filter([
            $this->mediaDir,
            $contentDir.'/media',
        ], file_exists(...)));
    }

    private function hasNewerFiles(string $dir): bool
    {
        if (! file_exists($dir)) {
            return false;
        }

        /** @var string[] $files */
        $files = scandir($dir);
        foreach ($files as $file) {
            if (\in_array($file, ['.', '..'], true)) {
                continue;
            }

            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                if ($this->hasNewerFiles($path)) {
                    return true;
                }

                continue;
            }

            if ($this->isFileNewer($path)) {
                return true;
            }
        }

        return false;
    }

    private function isFileNewer(string $filePath): bool
    {
        $fileName = $this->extractMediaName($filePath);
        if ('' === $fileName) {
            return false;
        }

        $media = $this->mediaRepository->findOneBy(['fileName' => $fileName]);
        if (! $media instanceof Media) {
            return true;
        }

        $lastEditDateTime = (new DateTime())->setTimestamp(filemtime($filePath));

        return $lastEditDateTime > $media->safegetUpdatedAt();
    }

    private function extractMediaName(string $filePath): string
    {
        $fileName = basename($filePath);

        if (
            str_ends_with($fileName, '.yaml')
            || str_ends_with($fileName, '.json')
        ) {
            return substr($fileName, 0, -5);
        }

        return $fileName;
    }

    private function resolveApp(?string $host): AppConfig
    {
        return null !== $host
            ? $this->apps->switchCurrentApp($host)->get()
            : $this->apps->get();
    }

    private function measure(string $name, callable $operation): float
    {
        if (null !== $this->stopwatch) {
            $this->stopwatch->start($name);
            $operation();

            return $this->stopwatch->stop($name)->getDuration();
        }

        $start = microtime(true);
        $operation();

        return (microtime(true) - $start) * 1000;
    }
}
