<?php

namespace Pushword\PageScanner\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\PageScanner\Service\AgentDetector;

final class AgentDetectorTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $savedEnv = [];

    /** @var string[] */
    private const array VARS = [
        'AI_AGENT', 'CLAUDECODE', 'CLAUDE_CODE', 'CURSOR_AGENT', 'GEMINI_CLI',
        'CODEX_SANDBOX', 'CODEX_CI', 'CODEX_THREAD_ID', 'AUGMENT_AGENT',
        'AMP_CURRENT_THREAD_ID', 'OPENCODE', 'OPENCODE_CLIENT', 'ANTIGRAVITY_AGENT',
        'PI_CODING_AGENT', 'KIRO_AGENT_PATH', 'REPL_ID', 'COPILOT_MODEL', 'COPILOT_CLI',
    ];

    protected function setUp(): void
    {
        // Start from a clean slate so the suite is deterministic even when run
        // inside an agent (CLAUDECODE & co. are typically set then).
        foreach (self::VARS as $var) {
            $this->savedEnv[$var] = getenv($var);
            putenv($var);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $var => $value) {
            putenv(false === $value ? $var : $var.'='.$value);
        }
    }

    public function testNoAgentEnvReturnsFalse(): void
    {
        self::assertFalse(AgentDetector::isAgent());
    }

    public function testClaudeCodeIsDetected(): void
    {
        putenv('CLAUDECODE=1');
        self::assertTrue(AgentDetector::isAgent());
    }

    public function testCursorIsDetected(): void
    {
        putenv('CURSOR_AGENT=1');
        self::assertTrue(AgentDetector::isAgent());
    }

    public function testAiAgentOverrideIsDetected(): void
    {
        putenv('AI_AGENT=some-custom-agent');
        self::assertTrue(AgentDetector::isAgent());
    }

    public function testEmptyValueIsNotDetected(): void
    {
        putenv('CLAUDECODE=');
        self::assertFalse(AgentDetector::isAgent());
    }
}
