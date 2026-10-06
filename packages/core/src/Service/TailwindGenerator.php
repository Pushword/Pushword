<?php

declare(strict_types=1);

namespace Pushword\Core\Service;

use Pushword\Core\Entity\Page;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Service\ResetInterface;

class TailwindGenerator implements ResetInterface
{
    private bool $pending = false;

    public function __construct(
        private readonly bool $tailwindGeneratorIsActive, // %pw.tailwind_generator%
        private readonly string $projectDir,
        private readonly string $pathToBin, // %pw.path_to_bin%
        private readonly KernelInterface $kernel,
    ) {
    }

    public function run(Page $page): void
    {
        if (! $this->tailwindGeneratorIsActive) {
            return;
        }

        if ('prod' !== $this->kernel->getEnvironment()) {
            return;
        }

        if (! file_exists($this->projectDir.'/assets') && ! file_exists($this->projectDir.'/vite.config.js')) {
            return;
        }

        $fs = new Filesystem();
        $fs->dumpFile(
            $this->projectDir.'/var/TailwindGeneratorCache/'.($page->id ?? 0),
            $page->mainContent
        );

        $this->pending = true;
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    #[AsEventListener(event: ConsoleEvents::TERMINATE)]
    #[AsEventListener(event: WorkerMessageHandledEvent::class)]
    public function flush(): void
    {
        if (! $this->pending) {
            return;
        }

        $this->pending = false;
        $fs = new Filesystem();
        $pendingFile = $this->projectDir.'/var/tailwind-build.pending';
        $fs->dumpFile($pendingFile, '');

        // Keep the lock until npm exits; waiting callers share one pending build.
        $lock = new LockFactory(new FlockStore($this->projectDir.'/var'))->createLock('pushword-tailwind');
        $lock->acquire(blocking: true);

        try {
            clearstatcache(true, $pendingFile);
            if (! file_exists($pendingFile)) {
                return;
            }

            // A save during the build creates a new marker for the next caller.
            $fs->remove($pendingFile);
            $fs->mkdir($this->projectDir.'/var/log');
            $process = Process::fromShellCommandline(
                'npm run build >'.escapeshellarg($this->projectDir.'/var/log/lastTailwindGeneration').' 2>&1',
                $this->projectDir.'/assets',
                '' !== $this->pathToBin ? ['PATH' => $this->pathToBin] : null,
                timeout: null,
            );
            $process->run();
        } finally {
            $lock->release();
        }
    }

    public function reset(): void
    {
        $this->pending = false;
    }
}
