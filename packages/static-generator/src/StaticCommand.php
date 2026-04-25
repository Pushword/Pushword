<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator;

use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\Core\Service\SharedOutputInterface;
use Pushword\Core\Service\TeeOutput;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Stopwatch\Stopwatch;
use Throwable;

#[AsCommand(name: 'pw:static', description: 'Generate static version for your website')]
#[AutoconfigureTag('console.command')]
final readonly class StaticCommand
{
    private const string PROCESS_TYPE = 'static-generator';

    private const string COMMAND_PATTERN = 'pw:static';

    public function __construct(
        private StaticAppGenerator $staticAppGenerator,
        private Stopwatch $stopWatch,
        private BackgroundProcessManager $processManager,
        private ProcessOutputStorage $outputStorage,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(name: 'host')]
        ?string $host,
        #[Argument(name: 'page')]
        ?string $page,
        #[Option(description: 'Only regenerate pages that have changed since last generation', name: 'incremental', shortcut: 'i')]
        bool $incremental = false,
        #[Option(description: 'Number of parallel workers (0=auto, 1=sequential)', name: 'workers', shortcut: 'w')]
        int $workers = 0,
    ): int {
        $processType = null === $host ? self::PROCESS_TYPE : self::PROCESS_TYPE.'--'.$host;

        // Check if same process type is already running (via PID file)
        $pidFile = $this->processManager->getPidFilePath($processType);
        $this->processManager->cleanupStaleProcess($pidFile);
        $processInfo = $this->processManager->getProcessInfo($pidFile);

        if ($processInfo['isRunning']) {
            $output->writeln('<error>Static generation is already running (PID: '.$processInfo['pid'].').</error>');

            return Command::FAILURE;
        }

        // Register this process and setup shared output
        $this->processManager->registerProcess($pidFile, self::COMMAND_PATTERN);

        // Only clear storage if not already initialized by web controller
        // (web controller sets status to 'running' before starting background process)
        $currentStatus = $this->outputStorage->getStatus($processType);
        if ('running' !== $currentStatus) {
            $this->outputStorage->clear($processType);
            $this->outputStorage->setStatus($processType, 'running');
        }

        // Create tee output to write to both console and shared storage
        $sharedOutput = new SharedOutputInterface($this->outputStorage, $processType);
        $teeOutput = new TeeOutput([$output, $sharedOutput]);

        try {
            $teeOutput->writeln('<comment>PID: '.getmypid().'</comment>');
            $this->stopWatch->start('generate');

            // Set tee output, stopwatch, and workers for progress reporting
            $this->staticAppGenerator->setOutput($teeOutput);
            $this->staticAppGenerator->setStopwatch($this->stopWatch);
            $this->staticAppGenerator->setWorkers($workers);

            if (null === $host) {
                $this->staticAppGenerator->generate(null, $incremental);
                $msg = 'All websites generated with success';
                if ($incremental) {
                    $msg .= ' (incremental mode)';
                }
            } elseif (null === $page) {
                $this->staticAppGenerator->generate($host, $incremental);
                $msg = $host.' generated with success.';
                if ($incremental) {
                    $msg .= ' (incremental mode)';
                }
            } else {
                $this->staticAppGenerator->generatePage($host, $page);
                $msg = $host.'/'.$page.' generated with success.';
            }

            $event = $this->stopWatch->stop('generate');
            $duration = $event->getDuration();
            $this->printStatus($teeOutput, $msg.' ('.$this->formatDuration($duration).').');

            // Print timing breakdown
            $this->printTimingBreakdown($teeOutput);

            $teeOutput->writeln(\sprintf('<comment>:: peak memory: %.1f MB</comment>', memory_get_peak_usage(true) / 1024 / 1024));

            $status = [] !== $this->staticAppGenerator->getErrors() ? 'error' : 'completed';
            $this->outputStorage->setStatus($processType, $status);

            return Command::SUCCESS;
        } catch (Throwable $throwable) {
            $teeOutput->writeln('<error>Fatal: '.$throwable->getMessage().'</error>');
            $this->outputStorage->setStatus($processType, 'error');

            return Command::FAILURE;
        } finally {
            // Clean up PID file
            $this->processManager->unregisterProcess($pidFile);
        }
    }

    private function printStatus(OutputInterface $output, string $successMessage): void
    {
        if ([] !== $this->staticAppGenerator->getErrors()) {
            foreach ($this->staticAppGenerator->getErrors() as $error) {
                $output->writeln('<error>'.$error.'</error>');
            }

            return;
        }

        $output->writeln('<info>'.$successMessage.'</info>');
    }

    private function printTimingBreakdown(OutputInterface $output): void
    {
        $sections = $this->stopWatch->getSections();
        $timings = [];

        foreach ($sections as $section) {
            foreach ($section->getEvents() as $name => $event) {
                // Skip our main event and internal Symfony events
                if ('generate' === $name) {
                    continue;
                }

                if ('__section__' === $name) {
                    continue;
                }

                // Only include our custom timing events
                if (! \in_array($name, ['kernel.handle', 'html.compress', 'file.write', 'generatePage'], true)) {
                    continue;
                }

                $timings[$name] = ($timings[$name] ?? 0) + $event->getDuration();
            }
        }

        if ([] === $timings) {
            return;
        }

        arsort($timings);

        $parts = [];
        foreach ($timings as $name => $duration) {
            $shortName = match ($name) {
                'kernel.handle' => 'render',
                'html.compress' => 'compress',
                'file.write' => 'write',
                default => 'page',
            };
            $parts[] = \sprintf('%s: %s', $shortName, $this->formatDuration($duration));
        }

        $output->writeln('<comment>⏱ '.implode(' | ', $parts).'</comment>');
    }

    private function formatDuration(float $ms): string
    {
        if ($ms < 1000) {
            return \sprintf('%dms', $ms);
        }

        $seconds = $ms / 1000;
        if ($seconds < 60) {
            return \sprintf('%.1fs', $seconds);
        }

        $minutes = floor($seconds / 60);
        $remaining = $seconds - ($minutes * 60);

        return \sprintf('%dm%02.0fs', $minutes, $remaining);
    }
}
