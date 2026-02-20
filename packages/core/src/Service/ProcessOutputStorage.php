<?php

namespace Pushword\Core\Service;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Shared storage for process output using filesystem.
 * Enables CLI-started processes to share their output with the web UI.
 */
final readonly class ProcessOutputStorage
{
    public function __construct(
        private Filesystem $filesystem,
        private string $varDir,
    ) {
    }

    public function write(string $processType, string $message): void
    {
        $filePath = $this->getOutputFilePath($processType);

        $this->filesystem->appendToFile($filePath, $message);
    }

    /**
     * @return array{content: string, offset: int}
     */
    public function read(string $processType, int $offset = 0): array
    {
        $filePath = $this->getOutputFilePath($processType);

        if (! $this->filesystem->exists($filePath)) {
            return ['content' => '', 'offset' => 0];
        }

        $content = file_get_contents($filePath);
        if (false === $content) {
            return ['content' => '', 'offset' => 0];
        }

        $length = \strlen($content);
        if ($offset >= $length) {
            return ['content' => '', 'offset' => $length];
        }

        return [
            'content' => substr($content, $offset),
            'offset' => $length,
        ];
    }

    public function clear(string $processType): void
    {
        $this->filesystem->remove($this->getOutputFilePath($processType));
        $this->filesystem->remove($this->getStatusFilePath($processType));
    }

    public function setStatus(string $processType, string $status): void
    {
        $this->filesystem->dumpFile($this->getStatusFilePath($processType), $status);
    }

    public function getStatus(string $processType): ?string
    {
        $statusFile = $this->getStatusFilePath($processType);
        if (! $this->filesystem->exists($statusFile)) {
            return null;
        }

        $status = file_get_contents($statusFile);

        return false !== $status ? trim($status) : null;
    }

    public function getOutputFilePath(string $processType): string
    {
        return $this->varDir.'/'.$processType.'-output.txt';
    }

    private function getStatusFilePath(string $processType): string
    {
        return $this->varDir.'/'.$processType.'-status.txt';
    }
}
