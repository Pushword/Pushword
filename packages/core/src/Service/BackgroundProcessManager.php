<?php

namespace Pushword\Core\Service;

use Exception;
use RuntimeException;

use function Safe\file_get_contents;
use function Safe\json_encode;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final readonly class BackgroundProcessManager
{
    public function __construct(
        private Filesystem $filesystem,
        private string $varDir,
        private string $projectDir,
        private int $maxProcessAge = 3600,
    ) {
    }

    /**
     * @return array{isRunning: bool, startTime: int|null, pid: int|null}
     */
    public function getProcessInfo(string $pidFile): array
    {
        $info = [
            'isRunning' => false,
            'startTime' => null,
            'pid' => null,
        ];

        if (! $this->filesystem->exists($pidFile)) {
            return $info;
        }

        try {
            $pidData = json_decode(file_get_contents($pidFile), true);

            if (! \is_array($pidData)) {
                return $info;
            }

            $pid = isset($pidData['pid']) && is_numeric($pidData['pid']) ? (int) $pidData['pid'] : null;
            $startTime = isset($pidData['startTime']) && is_numeric($pidData['startTime']) ? (int) $pidData['startTime'] : null;
            $commandPattern = isset($pidData['commandPattern']) && \is_string($pidData['commandPattern'])
                ? $pidData['commandPattern']
                : '';

            if (null === $pid || $pid <= 0) {
                return $info;
            }

            $isRunning = $this->isProcessAlive($pid, $commandPattern);

            $info['isRunning'] = $isRunning;
            $info['startTime'] = $startTime;
            $info['pid'] = $pid;
        } catch (Exception) {
            return $info;
        }

        return $info;
    }

    public function isProcessAlive(int $pid, string $commandPattern): bool
    {
        if ($pid <= 0) {
            return false;
        }

        // If the PID matches current process, it's not "another" running process
        if ($pid === getmypid()) {
            return false;
        }

        $procPath = '/proc/'.$pid;
        if ($this->filesystem->exists($procPath)) {
            $cmdlinePath = $procPath.'/cmdline';
            $cmdline = $this->readCmdline($cmdlinePath);

            if (null === $cmdline) {
                return true;
            }

            if ('' !== $commandPattern && str_contains($cmdline, $commandPattern)) {
                return true;
            }
        }

        if (\function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        return false;
    }

    private function readCmdline(string $path): ?string
    {
        if (! file_exists($path) || ! is_readable($path)) {
            return null;
        }

        try {
            return file_get_contents($path);
        } catch (Exception) {
            return null;
        }
    }

    public function cleanupStaleProcess(string $pidFile): void
    {
        $processInfo = $this->getProcessInfo($pidFile);

        if (! $processInfo['isRunning']) {
            $this->filesystem->remove($pidFile);

            return;
        }

        $startTime = $processInfo['startTime'];
        if (null !== $startTime) {
            $age = time() - $startTime;
            if ($age > $this->maxProcessAge) {
                $this->filesystem->remove($pidFile);
                $pid = $processInfo['pid'];
                if (null !== $pid && \function_exists('posix_kill')) {
                    @posix_kill($pid, \SIGTERM);
                }
            }
        }
    }

    /**
     * Start a background process with same-type lock.
     *
     * @param string[] $commandParts
     *
     * @throws RuntimeException if same process type is already running
     */
    public function startBackgroundProcess(
        string $pidFile,
        array $commandParts,
        string $commandPattern,
    ): int {
        // Check if same process type is already running
        $this->cleanupStaleProcess($pidFile);
        $processInfo = $this->getProcessInfo($pidFile);

        if ($processInfo['isRunning']) {
            throw new RuntimeException('Process is already running (PID: '.$processInfo['pid'].')');
        }

        $commandLine = implode(' ', array_map(escapeshellarg(...), $commandParts));

        // Run in background with nohup, discarding output (output is handled by ProcessOutputStorage)
        $command = \sprintf(
            'cd %s && nohup %s > /dev/null 2>&1 & echo $!',
            escapeshellarg($this->projectDir),
            $commandLine,
        );

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(5);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Failed to launch background process: '.$process->getErrorOutput());
        }

        $pidOutput = trim($process->getOutput());
        $pid = (int) $pidOutput;

        if ($pid <= 0) {
            throw new RuntimeException('Invalid PID received: '.$pidOutput);
        }

        $pidData = [
            'pid' => $pid,
            'startTime' => time(),
            'commandPattern' => $commandPattern,
        ];

        $this->filesystem->dumpFile($pidFile, json_encode($pidData, \JSON_PRETTY_PRINT));

        return $pid;
    }

    public function getPidFilePath(string $processType): string
    {
        return $this->varDir.'/'.$processType.'.pid';
    }

    /**
     * Register the current process (for CLI-started processes).
     */
    public function registerProcess(string $pidFile, string $commandPattern): void
    {
        $pidData = [
            'pid' => getmypid(),
            'startTime' => time(),
            'commandPattern' => $commandPattern,
        ];

        $this->filesystem->dumpFile($pidFile, json_encode($pidData, \JSON_PRETTY_PRINT));
    }

    /**
     * Unregister the current process (cleanup PID file).
     */
    public function unregisterProcess(string $pidFile): void
    {
        $this->filesystem->remove($pidFile);
    }
}
