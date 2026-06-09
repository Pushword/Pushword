<?php

namespace Pushword\Flat\Sync;

use Closure;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Snippet\Entity\Snippet;
use Pushword\Snippet\Repository\SnippetRepository;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Bidirectional flat-file sync for {@see Snippet} entities.
 *
 * Snippets live in `{flat_content_dir}/pw-snippets/{slug}.md`, one Markdown file
 * per snippet with a YAML front matter holding `name`, `tags` and any custom
 * property. The slug is the filename; the host is the content dir's host. The
 * directory is `pw-` prefixed so it never shadows a real page tree (the page
 * importer skips it, see {@see PageSync}).
 *
 * Global snippets (`host = ''`, "All hosts") have no host directory: they live
 * in `{base_content_dir}/pw-snippets/` (e.g. `content/pw-snippets/`). Since the sync
 * runs once per configured host, the global directory is processed exactly once
 * — during the default app's primary host pass — so it is not synced or deleted
 * repeatedly across hosts.
 *
 * Optional integration: only registered when the `pushword/snippet` package is
 * installed (guarded by `class_exists(Snippet::class)` in the flat services).
 */
final class SnippetSync
{
    /** Content subdirectory holding snippet Markdown files; `pw-` prefixed to avoid colliding with page slugs. */
    public const string DIR = 'pw-snippets';

    private int $importedCount = 0;

    private int $skippedCount = 0;

    private int $deletedCount = 0;

    private int $exportedCount = 0;

    private ?OutputInterface $output = null;

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly SiteRegistry $apps,
        private readonly FlatFileContentDirFinder $contentDirFinder,
        private readonly SnippetRepository $snippetRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SyncStateManager $stateManager,
        ?Filesystem $filesystem = null,
    ) {
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    public function setOutput(?OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function sync(?string $host = null, bool $forceExport = false): void
    {
        $this->resetCounters();
        $this->eachDirectory($host, function (string $dir, string $resolvedHost) use ($forceExport): void {
            $this->syncDirectory($dir, $resolvedHost, $forceExport);
        });
        $this->entityManager->flush();
    }

    public function import(?string $host = null): void
    {
        $this->resetCounters();
        $this->eachDirectory($host, function (string $dir, string $resolvedHost): void {
            $this->importDirectory($dir, $resolvedHost);
            $this->stateManager->recordImport('snippet', $resolvedHost);
        });
        $this->entityManager->flush();
    }

    /**
     * Run an operation for the resolved host's snippet directory and — only on
     * the default app's primary host, so it happens exactly once per full sync —
     * the global (host-less) directory.
     *
     * @param Closure(string $dir, string $host): void $process
     */
    private function eachDirectory(?string $host, Closure $process): void
    {
        $mainHost = $this->resolveApp($host)->getMainHost();
        $process($this->dir($mainHost), $mainHost);

        if ($this->isPrimaryHost($mainHost)) {
            $process($this->globalDir(), '');
        }
    }

    /** Import or export a single directory, based on which side is newer. */
    private function syncDirectory(string $dir, string $host, bool $forceExport): void
    {
        if (! $forceExport && $this->mustImport($dir, $host)) {
            $this->importDirectory($dir, $host);
            $this->stateManager->recordImport('snippet', $host);

            return;
        }

        $this->exportDirectory($dir, $host);
        $this->stateManager->recordExport('snippet', $host);
    }

    private function importDirectory(string $dir, string $host): void
    {
        $existing = [];
        foreach ($this->snippetRepository->findByHost($host) as $snippet) {
            $existing[$snippet->getSlug()] = $snippet;
        }

        $seenSlugs = [];
        foreach ($this->collectFiles($dir) as $path) {
            $slug = Snippet::normalizeSlug(basename($path, '.md'));
            $seenSlugs[$slug] = true;
            $this->importFile($path, $slug, $host, $existing[$slug] ?? null);
        }

        // Delete snippets whose file was removed (only when at least one file exists).
        if ([] === $seenSlugs) {
            return;
        }

        foreach ($existing as $existingSlug => $snippet) {
            if (isset($seenSlugs[$existingSlug])) {
                continue;
            }

            $this->output?->writeln(\sprintf('<comment>Deleting snippet %s</comment>', $existingSlug));
            $this->entityManager->remove($snippet);
            ++$this->deletedCount;
        }
    }

    private function importFile(string $path, string $slug, string $host, ?Snippet $snippet): void
    {
        $fileEditTime = new DateTime()->setTimestamp((int) filemtime($path));

        // Skip when the database row is newer than the file (export-side wins).
        if (null !== $snippet && $snippet->updatedAt >= $fileEditTime) {
            ++$this->skippedCount;

            return;
        }

        $document = YamlFrontMatter::parse($this->filesystem->readFile($path));
        /** @var array<string, mixed> $data */
        $data = $document->matter();

        $snippet ??= new Snippet();
        $snippet->host = $host;
        $snippet->setSlug($slug);
        $snippet->setName(\is_string($data['name'] ?? null) ? $data['name'] : '');
        $snippet->setTags($this->normalizeTags($data['tags'] ?? []));
        $snippet->setContent(trim($document->body()));

        unset($data['name'], $data['tags']);
        $snippet->setCustomProperties($data);

        $snippet->updatedAt = $fileEditTime;

        $this->entityManager->persist($snippet);
        $this->output?->writeln(\sprintf('Imported snippet %s', $slug));
        ++$this->importedCount;
    }

    public function export(?string $host = null): void
    {
        $this->resetCounters();
        $this->eachDirectory($host, function (string $dir, string $resolvedHost): void {
            $this->exportDirectory($dir, $resolvedHost);
            $this->stateManager->recordExport('snippet', $resolvedHost);
        });
    }

    private function exportDirectory(string $dir, string $host): void
    {
        $this->filesystem->mkdir($dir);

        $keepFiles = [];
        foreach ($this->snippetRepository->findByHost($host) as $snippet) {
            $path = $dir.'/'.$snippet->getSlug().'.md';
            $keepFiles[$path] = true;
            $content = $this->generateContent($snippet);

            if (is_file($path) && file_get_contents($path) === $content) {
                ++$this->skippedCount;

                continue;
            }

            $this->filesystem->dumpFile($path, $content);
            // Align mtime with the entity so the next sync does not re-import.
            touch($path, $snippet->updatedAt?->getTimestamp() ?? time());
            ++$this->exportedCount;
        }

        // Remove flat files whose snippet was deleted from the database.
        foreach ($this->collectFiles($dir) as $path) {
            if (isset($keepFiles[$path])) {
                continue;
            }

            $this->filesystem->remove($path);
            ++$this->deletedCount;
        }
    }

    /**
     * A YAML `tags` value is either a list (normalised to strings) or a string
     * (handed to setTags() as-is so it keeps splitting on spaces/commas).
     *
     * @return string[]|string
     */
    private function normalizeTags(mixed $tags): array|string
    {
        if (\is_string($tags)) {
            return $tags;
        }

        if (\is_array($tags)) {
            return array_map(static fn (mixed $tag): string => \is_scalar($tag) ? (string) $tag : '', $tags);
        }

        return [];
    }

    public function generateContent(Snippet $snippet): string
    {
        $data = ['name' => $snippet->getName()];

        $tags = $snippet->getTagList();
        if ([] !== $tags) {
            $data['tags'] = $tags;
        }

        foreach ($snippet->getCustomProperties() as $key => $value) {
            $data[$key] = $value;
        }

        return '---'.\PHP_EOL.Yaml::dump($data, 2).'---'.\PHP_EOL.\PHP_EOL.$snippet->getContent().\PHP_EOL;
    }

    private function mustImport(string $dir, string $host): bool
    {
        if (! is_dir($dir)) {
            return false;
        }

        $lastSyncTime = $this->stateManager->getLastSyncTime('snippet', $host);

        return array_any($this->collectFiles($dir), static fn (string $path): bool => filemtime($path) > $lastSyncTime);
    }

    private function resetCounters(): void
    {
        $this->importedCount = 0;
        $this->skippedCount = 0;
        $this->deletedCount = 0;
        $this->exportedCount = 0;
    }

    /** Global snippets (host = '') live in the base content dir, outside any host folder. */
    private function globalDir(): string
    {
        return $this->contentDirFinder->getBaseDir().'/'.self::DIR;
    }

    private function isPrimaryHost(string $host): bool
    {
        // getDefault() (not get()) so a prior switchSite() in resolveApp() can't
        // make the switched-to host look like the primary one.
        return $this->apps->getDefault()->getMainHost() === $host;
    }

    /** @return string[] */
    private function collectFiles(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = glob($dir.'/*.md');

        return false !== $files ? $files : [];
    }

    private function dir(string $host): string
    {
        return $this->contentDirFinder->get($host).'/'.self::DIR;
    }

    private function resolveApp(?string $host): SiteConfig
    {
        return null !== $host
            ? $this->apps->switchSite($host)->get()
            : $this->apps->get();
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function getDeletedCount(): int
    {
        return $this->deletedCount;
    }

    public function getExportedCount(): int
    {
        return $this->exportedCount;
    }
}
