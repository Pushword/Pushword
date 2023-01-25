<?php

namespace Pushword\Flat;

use Pushword\Core\Component\App\AppPool;

/**
 * Permit to find error in image or link.
 */
class FlatFileContentDirFinder
{
    /**
     * @var array<string, string>
     */
    protected array $contentDir = [];

    public function __construct(protected AppPool $apps, protected string $projectDir)
    {
    }

    public function get(string $host): string
    {
        if (isset($this->contentDir[$host])) {
            return $this->contentDir[$host];
        }

        $app = $this->apps->get($host);

        $dir = $app->get('flat_content_dir');
        if ('' === $dir || ! \is_string($dir)) {
            throw new \Exception('No `flat_content_dir` dir in `'.$app->getMainHost()."`'s params.");
        }

        $this->contentDir[$host] = $dir;

        if (! file_exists($this->contentDir[$host])) {
            throw new \Exception('Content dir `'.$dir.'` not found.');
        }

        return $this->contentDir[$host];
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
}
