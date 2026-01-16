<?php

namespace Pushword\Flat;

use Exception;
use Pushword\Core\Component\App\AppPool;
use Symfony\Component\Filesystem\Path;

/**
 * Permit to find error in image or link.
 */
final class FlatFileContentDirFinder
{
    /**
     * @var array<string, string>
     */
    private array $contentDir = [];

    public function __construct(
        private readonly AppPool $apps,
        private readonly string $projectDir
    ) {
    }

    public function get(string $host): string
    {
        if (isset($this->contentDir[$host])) {
            return $this->contentDir[$host];
        }

        $app = $this->apps->get($host);
        $mainHost = $app->getMainHost();

        $dir = $app->get('flat_content_dir');
        if ('' === $dir || ! \is_string($dir)) {
            throw new Exception('No `flat_content_dir` dir in `'.$app->getMainHost()."`'s params.");
        }

        $flatContentDir = str_replace('_host_', $mainHost, $dir);
        $flatContentDir = Path::canonicalize($flatContentDir);
        $this->contentDir[$host] = $flatContentDir;

        if (! file_exists($flatContentDir)) {
            if (str_starts_with($flatContentDir, $this->projectDir)) {
                mkdir($flatContentDir, 0755, true);
            } else {
                throw new Exception('Content dir `'.$dir.'` not found.');
            }
        }

        return $flatContentDir;
    }

    public function has(string $host): bool
    {
        if (isset($this->contentDir[$host])) {
            return (bool) $this->contentDir[$host];
        }

        $app = $this->apps->get($host);

        $dir = $app->get('flat_content_dir');

        return (bool) $dir;
    }

    /**
     * Get the base content directory (parent of host-specific directories).
     * For example, if flat_content_dir is '/content/_host_', returns '/content'.
     */
    public function getBaseDir(): string
    {
        $app = $this->apps->get();
        $dir = $app->get('flat_content_dir');

        if ('' === $dir || ! \is_string($dir)) {
            throw new Exception('No `flat_content_dir` configured.');
        }

        // Remove _host_ placeholder and trailing slash to get parent directory
        $baseDir = str_replace('/_host_', '', $dir);
        $baseDir = str_replace('_host_', '', $baseDir);
        $baseDir = Path::canonicalize($baseDir);

        if (! file_exists($baseDir)) {
            if (str_starts_with($baseDir, $this->projectDir)) {
                mkdir($baseDir, 0755, true);
            } else {
                throw new Exception('Content base dir `'.$baseDir.'` not found.');
            }
        }

        return $baseDir;
    }
}
