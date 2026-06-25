<?php

namespace Pushword\Core\Service;

/**
 * Detects whether the current process is being driven by an AI coding agent
 * (Claude Code, Cursor, Gemini CLI, Codex, …).
 *
 * Mirrors the environment-variable conventions of laravel/agent-detector so
 * commands can emit agent-optimized output without any extra configuration.
 */
final class AgentDetector
{
    /**
     * Environment variables whose non-empty presence signals an agent.
     *
     * @var string[]
     */
    private const array AGENT_ENV_VARS = [
        'CLAUDECODE',
        'CLAUDE_CODE',
        'CURSOR_AGENT',
        'GEMINI_CLI',
        'CODEX_SANDBOX',
        'CODEX_CI',
        'CODEX_THREAD_ID',
        'AUGMENT_AGENT',
        'AMP_CURRENT_THREAD_ID',
        'OPENCODE',
        'OPENCODE_CLIENT',
        'ANTIGRAVITY_AGENT',
        'PI_CODING_AGENT',
        'KIRO_AGENT_PATH',
        'REPL_ID',
        'COPILOT_MODEL',
        'COPILOT_CLI',
    ];

    public static function isAgent(): bool
    {
        // Explicit override — lets any agent (or a human) opt in.
        if ('' !== (string) getenv('AI_AGENT')) {
            return true;
        }

        return array_any(self::AGENT_ENV_VARS, static fn (string $var): bool => '' !== (string) getenv($var));
    }
}
