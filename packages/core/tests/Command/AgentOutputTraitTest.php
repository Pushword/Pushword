<?php

namespace Pushword\Core\Tests\Command;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Command\AgentOutputTrait;
use Symfony\Component\Console\Output\BufferedOutput;

final class AgentOutputTraitTest extends TestCase
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
        // Clear agent env so `auto` resolution is deterministic in-suite.
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

    public function testIsAgentFormatResolvesForcedFlagsUnknownAndAutoDetection(): void
    {
        $subject = new class {
            use AgentOutputTrait;

            public function resolve(string $format): bool
            {
                return $this->isAgentFormat($format);
            }
        };

        // Forced on, forced/unknown off.
        self::assertTrue($subject->resolve('agent'));
        self::assertTrue($subject->resolve('json'));
        self::assertFalse($subject->resolve('text'));
        self::assertFalse($subject->resolve('whatever'));

        // `auto` delegates to env detection (cleared in setUp).
        self::assertFalse($subject->resolve('auto'));
        putenv('CLAUDECODE=1');
        self::assertTrue($subject->resolve('auto'));
    }

    public function testWriteAgentJsonEmitsSingleUnescapedLine(): void
    {
        $subject = new class {
            use AgentOutputTrait;

            /** @param array<string, mixed> $data */
            public function emit(BufferedOutput $output, array $data): void
            {
                $this->writeAgentJson($output, $data);
            }
        };

        $output = new BufferedOutput();
        $subject->emit($output, ['tool' => 'pw:x', 'path' => '/a/b', 'label' => 'café']);

        $raw = $output->fetch();
        self::assertStringNotContainsString("\n", trim($raw));
        self::assertStringContainsString('/a/b', $raw);
        self::assertStringNotContainsString('\/', $raw);
        self::assertStringContainsString('café', $raw);

        $decoded = json_decode(trim($raw), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('pw:x', $decoded['tool']);
    }
}
