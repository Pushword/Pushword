<?php

namespace Pushword\Core\Service;

use Pushword\Core\Entity\PageInterface;
use Symfony\Component\Filesystem\Filesystem;

class TailwindGenerator
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function run(PageInterface $page): void
    {
        if (! file_exists($this->projectDir.'/assets/webpack.config.js')) {
            return;
        }

        $fs = new FileSystem();
        $fs->dumpFile(
            $this->projectDir.'/var/TailwindGeneratorCache/'.$page->getId(),
            serialize($page)
        );

        @exec('cd "'.str_replace('"', '\"', $this->projectDir).'/assets" && yarn encore production >/dev/null 2>&1 &');
    }
}
