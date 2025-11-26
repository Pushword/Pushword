<?php

namespace Pushword\Flat\Sync;

use DateTime;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Flat\Exporter\PageExporter;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Importer\PageImporter;

use function Safe\filemtime;
use function Safe\scandir;

use Symfony\Component\Stopwatch\Stopwatch;

final readonly class PageSync
{
    public function __construct(
        private AppPool $apps,
        private FlatFileContentDirFinder $contentDirFinder,
        private PageImporter $pageImporter,
        private PageExporter $pageExporter,
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

        $this->measure('page_import', function () use ($contentDir): void {
            $this->importDirectory($contentDir);
            $this->pageImporter->finishImport();
        });
    }

    public function export(?string $host = null, bool $force = false, ?string $exportDir = null): void
    {
        $app = $this->resolveApp($host);
        $targetDir = $exportDir ?? $this->contentDirFinder->get($app->getMainHost());

        $this->measure('page_export', function () use ($force, $targetDir): void {
            $this->pageExporter->exportDir = $targetDir;
            $this->pageExporter->exportPages($force);
        });
    }

    public function mustImport(?string $host = null): bool
    {
        $app = $this->resolveApp($host);
        $contentDir = $this->contentDirFinder->get($app->getMainHost());

        return $this->hasNewerFiles($contentDir, $app->getMainHost());
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
            $this->pageImporter->import($path, $lastEditDateTime);
        }
    }

    private function hasNewerFiles(string $dir, string $host): bool
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
                if ($this->hasNewerFiles($path, $host)) {
                    return true;
                }

                continue;
            }

            if ($this->isFileNewer($path, $host)) {
                return true;
            }
        }

        return false;
    }

    private function isFileNewer(string $filePath, string $host): bool
    {
        if (! str_ends_with($filePath, '.md')) {
            return false;
        }

        $document = $this->pageImporter->getDocumentFromFile($filePath);
        if (null === $document) {
            return true;
        }

        $slug = $this->pageImporter->getSlug($filePath, $document);
        $page = $this->pageImporter->pageRepo->findOneBy([
            'slug' => $slug,
            'host' => $host,
        ]);

        if (null === $page) {
            return true;
        }

        $lastEditDateTime = (new DateTime())->setTimestamp(filemtime($filePath));

        return $lastEditDateTime > $page->getUpdatedAt();
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
