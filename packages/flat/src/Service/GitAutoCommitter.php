<?php

declare(strict_types=1);

namespace Pushword\Flat\Service;

use Psr\Log\LoggerInterface;
use Pushword\Flat\FlatFileContentDirFinder;
use Symfony\Component\Process\Process;

final readonly class GitAutoCommitter
{
    public function __construct(
        private bool $enabled,
        private FlatFileContentDirFinder $contentDirFinder,
        private LoggerInterface $logger,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function commitIfChanges(?string $host = null): bool
    {
        if (! $this->enabled) {
            return false;
        }

        $dir = null !== $host
            ? $this->contentDirFinder->get($host)
            : $this->contentDirFinder->getBaseDir();

        return $this->commit($dir, 'Auto-commit: content updated via admin');
    }

    public function commit(string $dir, string $message): bool
    {
        if (! is_dir($dir.'/.git') && ! is_dir(\dirname($dir).'/.git')) {
            $this->logger->info('GitAutoCommitter: no git repo found in {dir}, skipping', ['dir' => $dir]);

            return false;
        }

        // Find the actual git root
        $gitDir = is_dir($dir.'/.git') ? $dir : \dirname($dir);

        $status = new Process(['git', '-C', $gitDir, 'status', '--porcelain']);
        $status->run();

        if ('' === trim($status->getOutput())) {
            return false;
        }

        $add = new Process(['git', '-C', $gitDir, 'add', '-A']);
        $add->run();

        if (! $add->isSuccessful()) {
            $this->logger->warning('GitAutoCommitter: git add failed: {error}', ['error' => $add->getErrorOutput()]);

            return false;
        }

        $commit = new Process(['git', '-C', $gitDir, 'commit', '-m', $message]);
        $commit->run();

        if (! $commit->isSuccessful()) {
            $this->logger->warning('GitAutoCommitter: git commit failed: {error}', ['error' => $commit->getErrorOutput()]);

            return false;
        }

        $push = new Process(['git', '-C', $gitDir, 'push']);
        $push->run();

        if (! $push->isSuccessful()) {
            $this->logger->warning('GitAutoCommitter: git push failed: {error}', ['error' => $push->getErrorOutput()]);
        }

        return true;
    }
}
