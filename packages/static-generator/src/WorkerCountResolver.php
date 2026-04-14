<?php

namespace Pushword\StaticGenerator;

use Symfony\Component\Process\Process;

final class WorkerCountResolver
{
    private const int MEMORY_PER_WORKER_MB = 100;

    private const int MIN_PAGES_FOR_PARALLEL = 10;

    public static function resolve(int $requested, int $pageCount): int
    {
        if ($requested > 0) {
            return min($requested, $pageCount);
        }

        if ($pageCount < self::MIN_PAGES_FOR_PARALLEL) {
            return 1;
        }

        $cpuLimit = self::detectCpuCount();
        $memoryLimit = max(1, (int) floor(self::detectAvailableMemoryMb() / self::MEMORY_PER_WORKER_MB));

        return min($cpuLimit, $memoryLimit, $pageCount);
    }

    private static function detectCpuCount(): int
    {
        $process = new Process(['nproc']);
        $process->run();

        return $process->isSuccessful() ? (int) trim($process->getOutput()) : 4;
    }

    private static function detectAvailableMemoryMb(): int
    {
        if (is_readable('/proc/meminfo')) {
            $content = file_get_contents('/proc/meminfo');
            if (\is_string($content) && 1 === preg_match('/MemAvailable:\s+(\d+)\s+kB/', $content, $matches)) {
                return (int) ((int) $matches[1] / 1024);
            }
        }

        // Fallback: assume enough memory for CPU-based limit
        return self::detectCpuCount() * self::MEMORY_PER_WORKER_MB;
    }
}
