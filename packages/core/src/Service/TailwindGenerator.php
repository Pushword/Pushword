<?php

namespace Pushword\Core\Service;

use Pushword\Core\Entity\PageInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

class TailwindGenerator
{
    public function __construct(
        private readonly bool $tailwindGeneratorisActive, // %pw.tailwind_generator%
        private readonly string $projectDir,
        private readonly string $pathToBin, // %pw.path_to_bin%
        private readonly KernelInterface $kernel,
    ) {
    }

    public function run(PageInterface $page): void
    {
        if (false === $this->tailwindGeneratorisActive) {
            return;
        }

        if ('prod' !== $this->kernel->getEnvironment()) {
            return;
        }

        if (! file_exists($this->projectDir.'/assets')) {
            return;
        }

        $fs = new FileSystem();
        $fs->dumpFile(
            $this->projectDir.'/var/TailwindGeneratorCache/'.$page->getId(),
            serialize($page)
        );

        $cmd = 'cd "'.str_replace('"', '\"', $this->projectDir).'/assets" && '
            .('' !== $this->pathToBin ? 'export PATH="'.str_replace('"', '\"', $this->pathToBin).'" && ' : '')
            .'NODE_ENV=production yarn build >"'.str_replace('"', '\"', $this->projectDir).'/var/log/lastTailwindGeneration" 2>&1 &';
        @proc_open(
            '{ ('.$cmd.') <&3 3<&- 3>/dev/null & } 3<&0;',
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes
        );
    }
}
