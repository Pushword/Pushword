<?php

namespace Pushword\Core\Service;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Writes output to multiple OutputInterface instances simultaneously.
 * Used to output to both console and shared storage at the same time.
 */
final readonly class TeeOutput implements OutputInterface
{
    /**
     * @param OutputInterface[] $outputs
     */
    public function __construct(
        private array $outputs,
    ) {
    }

    public function write(string|iterable $messages, bool $newline = false, int $options = 0): void
    {
        foreach ($this->outputs as $output) {
            $output->write($messages, $newline, $options);
        }
    }

    public function writeln(string|iterable $messages, int $options = 0): void
    {
        foreach ($this->outputs as $output) {
            $output->writeln($messages, $options);
        }
    }

    public function setVerbosity(int $level): void
    {
        foreach ($this->outputs as $output) {
            $output->setVerbosity($level);
        }
    }

    public function getVerbosity(): int
    {
        return $this->outputs[0]->getVerbosity();
    }

    public function isQuiet(): bool
    {
        return $this->outputs[0]->isQuiet();
    }

    public function isVerbose(): bool
    {
        return $this->outputs[0]->isVerbose();
    }

    public function isVeryVerbose(): bool
    {
        return $this->outputs[0]->isVeryVerbose();
    }

    public function isDebug(): bool
    {
        return $this->outputs[0]->isDebug();
    }

    public function setDecorated(bool $decorated): void
    {
        foreach ($this->outputs as $output) {
            $output->setDecorated($decorated);
        }
    }

    public function isDecorated(): bool
    {
        return $this->outputs[0]->isDecorated();
    }

    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        foreach ($this->outputs as $output) {
            $output->setFormatter($formatter);
        }
    }

    public function getFormatter(): OutputFormatterInterface
    {
        return $this->outputs[0]->getFormatter();
    }

    public function isSilent(): bool
    {
        return $this->outputs[0]->isSilent();
    }
}
