<?php

namespace Pushword\Core\Command;

use Pushword\Core\Service\AgentDetector;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Shared agent-optimized output primitives for console commands.
 *
 * A command exposes a `--format` option (auto|agent|text), resolves it once with
 * {@see self::isAgentFormat()}, suppresses its human chatter/progress when active
 * and emits a single compact JSON document with {@see self::writeAgentJson()}.
 * Inspired by laravel/pao.
 */
trait AgentOutputTrait
{
    /**
     * Resolve whether to emit agent-optimized JSON for the given --format value.
     * `agent`/`json` force it, `text` disables it, `auto` defers to detection.
     */
    protected function isAgentFormat(string $format): bool
    {
        return \in_array($format, ['agent', 'json'], true)
            || ('auto' === $format && AgentDetector::isAgent());
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function writeAgentJson(OutputInterface $output, array $data): void
    {
        $output->writeln(json_encode($data, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));
    }
}
