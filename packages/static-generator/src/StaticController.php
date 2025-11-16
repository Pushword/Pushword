<?php

namespace Pushword\StaticGenerator;

use function Safe\file_get_contents;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AutoconfigureTag('controller.service_arguments')]
class StaticController extends AbstractController
{
    private const PID_FILE = 'static-generator.pid';

    private const OUTPUT_FILE = 'static-generator-output.txt';

    private const LOCK_FILE = 'static-generator.lock';

    private const MAX_PROCESS_AGE = 3600; // 1 hour timeout

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $projectDir
    ) {
    }

    #[Route(path: '/~static', name: 'old_piedweb_static_generate', methods: ['GET'], priority: 1)]
    public function redirectOldStaticRoute(): RedirectResponse
    {
        return $this->redirectToRoute('piedweb_static_generate', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(path: '/{host}', name: 'piedweb_static_generate', methods: ['GET'], priority: -1)]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function generateStatic(?string $host = null): Response
    {
        // Validate host parameter if provided
        if (null !== $host && ! $this->isValidHost($host)) {
            throw $this->createNotFoundException('Invalid host parameter');
        }

        $staticDir = $this->projectDir.'/var/static';
        $this->filesystem->mkdir($staticDir);
        $pidFile = $staticDir.'/'.self::PID_FILE;
        $outputFile = $staticDir.'/'.self::OUTPUT_FILE;
        $lockFile = $staticDir.'/'.self::LOCK_FILE;

        // Clean up stale processes
        $this->cleanupStaleProcess($pidFile, $lockFile);

        // Check if a process is already running
        $processInfo = $this->getProcessInfo($pidFile);

        if ($processInfo['isRunning']) {
            return $this->render('@PushwordStatic/running.html.twig', [
                'host' => $host,
                'startTime' => $processInfo['startTime'],
            ]);
        }

        // Check if we should display results
        if ($this->filesystem->exists($outputFile)) {
            $output = file_get_contents($outputFile);
            $errors = $this->parseErrors($output);

            // Clean up files
            $this->cleanup($pidFile, $outputFile, $lockFile);

            return $this->render('@PushwordStatic/results.html.twig', [
                'errors' => $errors,
                'output' => $output,
                'host' => $host,
            ]);
        }

        // Start new process
        try {
            $this->startBackgroundProcess($host, $pidFile, $outputFile, $lockFile);
        } catch (\Exception $e) {
            $this->cleanup($pidFile, $outputFile, $lockFile);

            throw new \RuntimeException('Failed to start background process: '.$e->getMessage(), 0, $e);
        }

        // Redirect to show running status
        return $this->redirectToRoute('piedweb_static_generate', ['host' => $host]);
    }

    private function isValidHost(string $host): bool
    {
        // Basic validation - adjust based on your needs
        return 1 === preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $host);
    }

    /**
     * @return array{isRunning: bool, startTime: int|null, pid: int|null}
     */
    private function getProcessInfo(string $pidFile): array
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

            if (! is_array($pidData)) {
                return $info;
            }

            $pid = isset($pidData['pid']) && is_numeric($pidData['pid']) ? (int) $pidData['pid'] : null;
            $startTime = isset($pidData['startTime']) && is_numeric($pidData['startTime']) ? (int) $pidData['startTime'] : null;

            if (null === $pid || $pid <= 0) {
                return $info;
            }

            // Check if process is still running
            $isRunning = $this->isProcessAlive($pid);

            $info['isRunning'] = $isRunning;
            $info['startTime'] = $startTime;
            $info['pid'] = $pid;
        } catch (\Exception $e) {
            // If we can't read the file, assume no process is running
            return $info;
        }

        return $info;
    }

    private function isProcessAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        // Check using /proc filesystem (Linux)
        if ($this->filesystem->exists('/proc/'.$pid)) {
            // Additional verification: check if it's our process
            try {
                $cmdline = @file_get_contents('/proc/'.$pid.'/cmdline');
                if (str_contains($cmdline, 'pushword:static:generate')) {
                    return true;
                }
            } catch (\Exception $e) {
                // Process exists but we can't read cmdline, assume it's alive
                return true;
            }
        }

        // Fallback: use posix_kill with signal 0 (doesn't kill, just checks)
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        return false;
    }

    private function cleanupStaleProcess(string $pidFile, string $lockFile): void
    {
        $processInfo = $this->getProcessInfo($pidFile);

        if (! $processInfo['isRunning']) {
            return;
        }

        // Check if process is too old
        $startTime = $processInfo['startTime'];
        if (null !== $startTime) {
            $age = time() - $startTime;
            if ($age > self::MAX_PROCESS_AGE) {
                // Process is stale, clean it up
                $this->cleanup($pidFile, null, $lockFile);

                // Optionally try to kill the process
                $pid = $processInfo['pid'];
                if (null !== $pid && function_exists('posix_kill')) {
                    @posix_kill($pid, \SIGTERM);
                }
            }
        }
    }

    /**
     * @return array<string>
     */
    private function parseErrors(string $output): array
    {
        $errors = [];

        if ('' === $output) {
            return $errors;
        }

        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $line = trim($line);
            $lowerLine = strtolower($line);

            if ('' !== $line && (
                str_contains($lowerLine, 'error')
                || str_contains($lowerLine, 'failed')
                || str_contains($lowerLine, 'exception')
            )) {
                $errors[] = $line;
            }
        }

        return $errors;
    }

    private function startBackgroundProcess(
        ?string $host,
        string $pidFile,
        string $outputFile,
        string $lockFile
    ): void {
        // Create lock file to prevent race conditions
        $this->filesystem->dumpFile($lockFile, (string) time());

        $commandParts = ['php', 'bin/console', 'pushword:static:generate'];
        if (null !== $host) {
            $commandParts[] = $host;
        }

        // Build the shell command with proper escaping
        $commandLine = implode(' ', array_map('escapeshellarg', $commandParts));

        // Use sh -c to properly detach the process in background
        $command = sprintf(
            'cd %s && sh -c "%s >> %s 2>&1 & echo \\$!"',
            escapeshellarg($this->projectDir),
            $commandLine,
            escapeshellarg($outputFile)
        );

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(5); // Timeout for launching, not for the background process
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Failed to launch background process: '.$process->getErrorOutput());
        }

        // Get the PID from output
        $pidOutput = trim($process->getOutput());
        $pid = (int) $pidOutput;

        if ($pid <= 0) {
            throw new \RuntimeException('Invalid PID received: '.$pidOutput);
        }

        // Store process information
        $pidData = [
            'pid' => $pid,
            'startTime' => time(),
            'host' => $host,
        ];

        $this->filesystem->dumpFile($pidFile, \Safe\json_encode($pidData, \JSON_PRETTY_PRINT));

        // Remove lock file
        $this->filesystem->remove($lockFile);
    }

    private function cleanup(?string $pidFile, ?string $outputFile, ?string $lockFile): void
    {
        $filesToRemove = [$pidFile, $lockFile, $outputFile];

        foreach ($filesToRemove as $file) {
            if (null !== $file && $this->filesystem->exists($file)) {
                $this->filesystem->remove($file);
            }
        }
    }
}
