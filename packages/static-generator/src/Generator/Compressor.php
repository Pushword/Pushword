<?php

namespace Pushword\StaticGenerator\Generator;

use Exception;
use Symfony\Component\Process\Process;

class Compressor
{
    /** @var array<CompressionAlgorithm> */
    public readonly array $availableCompressors;

    /** @var array<Process> */
    private array $runningProcesses = [];

    private readonly int $maxConcurrentProcesses;

    /**
     * @param array<CompressionAlgorithm>|null $availableCompressors
     */
    public function __construct(?array $availableCompressors = null)
    {
        if (null !== $availableCompressors) {
            $this->availableCompressors = $availableCompressors;
        } else {
            $detected = [];
            foreach (CompressionAlgorithm::cases() as $algorithm) {
                if ($this->isAlgorithmInstalled($algorithm)) {
                    $detected[] = $algorithm;
                }
            }

            $this->availableCompressors = $detected;
        }

        $this->maxConcurrentProcesses = (int) (shell_exec('nproc') ?: 10);
    }

    public function __destruct()
    {
        $this->waitForCompressionToFinish();
    }

    private function isAlgorithmInstalled(CompressionAlgorithm $algorithm): bool
    {
        $process = new Process(['which', $algorithm->value]);
        $process->run();

        return $process->isSuccessful();
    }

    private function isAlgorithmAvailable(CompressionAlgorithm $algorithm): bool
    {
        return \in_array($algorithm, $this->availableCompressors, true);
    }

    public function compress(string $filePath, CompressionAlgorithm $algorithm): void
    {
        if (! $this->isAlgorithmAvailable($algorithm)) {
            return;
        }

        $this->throttleIfNeeded();

        try {
            $cmd = match ($algorithm) {
                CompressionAlgorithm::Zstd => 'zstd -f --stdout '.escapeshellarg($filePath).' > '.escapeshellarg($filePath.'.zst'),
                CompressionAlgorithm::Brotli => 'brotli --stdout '.escapeshellarg($filePath).' > '.escapeshellarg($filePath.'.br'),
                CompressionAlgorithm::Gzip => 'gzip -c '.escapeshellarg($filePath).' > '.escapeshellarg($filePath.'.gz'),
            };

            $process = Process::fromShellCommandline($cmd);
            $process->start();
            $this->runningProcesses[] = $process;
        } catch (Exception $exception) {
            throw new Exception('Failed to compress `'.$filePath.'` with `'.$algorithm->value.'`: '.$exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function waitForCompressionToFinish(): void
    {
        foreach ($this->runningProcesses as $process) {
            if ($process->isRunning()) {
                $process->wait();
            }

            if (! $process->isSuccessful()) {
                throw new Exception('Compression failed: '.$process->getErrorOutput());
            }
        }

        $this->runningProcesses = [];
    }

    private function throttleIfNeeded(): void
    {
        // Remove finished processes first
        $this->runningProcesses = array_filter(
            $this->runningProcesses,
            static fn (Process $p): bool => $p->isRunning()
        );

        if (\count($this->runningProcesses) < $this->maxConcurrentProcesses) {
            return;
        }

        // Wait for at least one process to finish (blocking, no CPU waste)
        $processToWait = reset($this->runningProcesses);
        if (false !== $processToWait) {
            $processToWait->wait();
        }

        // Clean up after waiting
        $this->runningProcesses = array_filter(
            $this->runningProcesses,
            static fn (Process $p): bool => $p->isRunning()
        );
    }
}
