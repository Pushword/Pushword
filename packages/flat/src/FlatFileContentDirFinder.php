<?php

namespace Pushword\Flat;

use Exception;
use Pushword\Core\Component\App\AppPool;

/**
 * Permit to find error in image or link.
 */
class FlatFileContentDirFinder
{
    /** @var AppPool */
    protected $apps;

    protected $projectDir;

    protected $contentDir = [];

    public function __construct(
        AppPool $apps,
        string $projectDir
    ) {
        $this->projectDir = $projectDir;
        $this->apps = $apps;
    }

    public function get(string $host): string
    {
        if (isset($this->contentDir[$host])) {
            return $this->contentDir[$host];
        }

        $app = $this->apps->get($host);

        $dir = $app->get('flat_content_dir');
        if (! $dir) {
            throw new Exception('No content dir in app\'s param.');
        }

        $this->contentDir[$host] = $dir;

        if (! file_exists($this->contentDir[$host])) {
            throw new Exception('Content dir `'.$dir.'` not found.');
        }

        return $this->contentDir[$host];
    }

    public function has(string $host): bool
    {
        if (isset($this->contentDir[$host])) {
            return $this->contentDir[$host];
        }

        $app = $this->apps->get($host);

        $dir = $app->get('flat_content_dir');
        if (! $dir) {
            return false;
        }

        return true;
    }
}
