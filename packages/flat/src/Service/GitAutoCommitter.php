<?php

declare(strict_types=1);

namespace Pushword\Flat\Service;

use Psr\Log\LoggerInterface;
use Pushword\Flat\FlatFileContentDirFinder;
use Symfony\Component\Process\Process;

final readonly class GitAutoCommitter
{
    private const int MAX_HISTORY = 50;

    public function __construct(
        private bool $enabled,
        private FlatFileContentDirFinder $contentDirFinder,
        private LoggerInterface $logger,
        private string $varDir,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return list<array{timestamp: int, success: bool, steps: list<array{command: string, success: bool, output: string}>}>
     */
    public function getHistory(): array
    {
        return $this->readStatusFile();
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

        /** @var list<array{command: string, success: bool, output: string}> $steps */
        $steps = [];

        if (! $this->runStep($gitDir, ['add', '-A'], 'git add -A', $steps)) {
            $this->saveStatus($steps, false);

            return false;
        }

        if (! $this->runStep($gitDir, ['commit', '-m', $message], 'git commit', $steps)) {
            $this->saveStatus($steps, false);

            return false;
        }

        $pullOk = $this->runStep($gitDir, ['pull', '--rebase'], 'git pull --rebase', $steps);
        $pushOk = $this->runStep($gitDir, ['push'], 'git push', $steps);

        $this->saveStatus($steps, $pullOk && $pushOk);

        return true;
    }

    /**
     * @param string[]                                                    $args
     * @param list<array{command: string, success: bool, output: string}> &$steps
     */
    private function runStep(string $gitDir, array $args, string $label, array &$steps): bool
    {
        $process = new Process(['git', '-C', $gitDir, ...$args]);
        $process->run();

        $steps[] = [
            'command' => $label,
            'success' => $process->isSuccessful(),
            'output' => $process->isSuccessful() ? $process->getOutput() : $process->getErrorOutput(),
        ];

        if (! $process->isSuccessful()) {
            $this->logger->warning('GitAutoCommitter: {label} failed: {error}', [
                'label' => $label,
                'error' => $process->getErrorOutput(),
            ]);
        }

        return $process->isSuccessful();
    }

    private function statusFilePath(): string
    {
        return $this->varDir.'/git-autocommit-status.json';
    }

    /**
     * @return list<array{timestamp: int, success: bool, steps: list<array{command: string, success: bool, output: string}>}>
     */
    private function readStatusFile(): array
    {
        $file = $this->statusFilePath();
        if (! file_exists($file)) {
            return [];
        }

        /** @var list<array{timestamp: int, success: bool, steps: list<array{command: string, success: bool, output: string}>}> */
        return json_decode(file_get_contents($file) ?: '[]', true);
    }

    /**
     * @param list<array{command: string, success: bool, output: string}> $steps
     */
    private function saveStatus(array $steps, bool $success): void
    {
        $history = $this->readStatusFile();

        array_unshift($history, [
            'timestamp' => time(),
            'success' => $success,
            'steps' => $steps,
        ]);

        file_put_contents(
            $this->statusFilePath(),
            json_encode(\array_slice($history, 0, self::MAX_HISTORY), \JSON_PRETTY_PRINT),
        );
    }
}
