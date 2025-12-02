<?php

namespace Pushword\StaticGenerator\Generator;

use Exception;
use Symfony\Component\Process\Process;

class Compressor
{
    public const ZSTD = 'zstd';

    public const BROTLI = 'brotli';

    public const GZIP = 'gzip';

    public const COMPRESSORS = [self::ZSTD, self::BROTLI, self::GZIP];

    private const int MAX_CONCURRENT_PROCESSES = 10;

    /** @var array<string> */
    public readonly array $availableCompressors;

    /** @var array<Process> */
    private array $runningProcesses = [];

    public function __construct()
    {
        $availableCompressors = [];
        foreach (self::COMPRESSORS as $compressor) {
            if ($this->initCompressorsAvailability($compressor)) {
                $availableCompressors[] = $compressor;
            }
        }

        $this->availableCompressors = $availableCompressors;
    }

    public function __destruct()
    {
        $this->waitForCompressionToFinish();
    }

    private function initCompressorsAvailability(string $compressorName): bool
    {
        $process = new Process(['which', $compressorName]);
        $process->run();

        return $process->isSuccessful();
    }

    private function isCompressorAvailable(string $compressor): bool
    {
        return in_array($compressor, $this->availableCompressors, true);
    }

    public function compress(string $filePath, string $compressorName): void
    {
        if (! $this->isCompressorAvailable($compressorName)) {
            return;
        }

        $this->throttleIfNeeded();

        try {
            $cmd = match ($compressorName) {
                'zstd' => 'zstd -f --stdout '.escapeshellarg($filePath).' > '.escapeshellarg($filePath.'.zst'),
                'brotli' => 'brotli --stdout '.escapeshellarg($filePath).' > '.escapeshellarg($filePath.'.br'),
                'gzip' => 'gzip -c '.escapeshellarg($filePath).' > '.escapeshellarg($filePath.'.gz'),
                default => null,
            };

            if (null === $cmd) {
                return;
            }

            $process = Process::fromShellCommandline($cmd);
            $process->start();
            $this->runningProcesses[] = $process;
        } catch (Exception $exception) {
            throw new Exception('Failed to compress `'.$filePath.'` with `'.$compressorName.'`: '.$exception->getMessage(), $exception->getCode(), $exception);
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
        if (\count($this->runningProcesses) < self::MAX_CONCURRENT_PROCESSES) {
            return;
        }

        // Remove finished processes and wait for one to finish if still at limit
        $this->runningProcesses = array_filter(
            $this->runningProcesses,
            static fn (Process $p): bool => $p->isRunning()
        );

        // Still at limit? Wait for at least one to finish
        while (\count($this->runningProcesses) >= self::MAX_CONCURRENT_PROCESSES) {
            usleep(10000); // 10ms
            $this->runningProcesses = array_filter(
                $this->runningProcesses,
                static fn (Process $p): bool => $p->isRunning()
            );
        }
    }
}
