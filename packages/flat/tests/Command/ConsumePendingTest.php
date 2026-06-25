<?php

namespace Pushword\Flat\Tests\Command;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Flat\Service\DeferredExportProcessor;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class ConsumePendingTest extends KernelTestCase
{
    private Application $application;

    #[Override]
    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->application = new Application($kernel);
    }

    public function testConsumePendingWithNoFlagDoesNothing(): void
    {
        $command = $this->application->find('pw:flat:sync');
        $tester = new CommandTester($command);

        // Ensure no pending flag exists
        $processor = self::getContainer()->get(DeferredExportProcessor::class);
        $processor->clearPendingFlag();

        $tester->execute(['--consume-pending' => true, '--format' => 'text']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('No pending export flag found', $tester->getDisplay());
    }

    public function testConsumePendingReadsFlagAndRunsExport(): void
    {
        $command = $this->application->find('pw:flat:sync');
        $tester = new CommandTester($command);

        // Write a pending flag manually
        $processor = self::getContainer()->get(DeferredExportProcessor::class);
        $flagPath = $processor->getPendingFlagPath();
        $flagDir = \dirname($flagPath);
        if (! is_dir($flagDir)) {
            mkdir($flagDir, 0o755, true);
        }

        file_put_contents($flagPath, json_encode([
            'entityTypes' => ['page'],
            'hosts' => [],
        ]));

        $tester->execute(['--consume-pending' => true, '--format' => 'text']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Consuming pending export', $tester->getDisplay());

        // Flag should be cleared after consumption
        self::assertNull($processor->readPendingFlag());
    }

    public function testConsumePendingAgentEmitsSingleJsonLine(): void
    {
        $command = $this->application->find('pw:flat:sync');
        $tester = new CommandTester($command);

        $processor = self::getContainer()->get(DeferredExportProcessor::class);
        $flagPath = $processor->getPendingFlagPath();
        $flagDir = \dirname($flagPath);
        if (! is_dir($flagDir)) {
            mkdir($flagDir, 0o755, true);
        }

        file_put_contents($flagPath, json_encode([
            'entityTypes' => ['page'],
            'hosts' => [],
        ]));

        $tester->execute(['--consume-pending' => true, '--format' => 'agent']);

        self::assertSame(0, $tester->getStatusCode());

        // The nested export must stay silent: exactly one JSON line, no human noise.
        $output = trim($tester->getDisplay());
        self::assertStringNotContainsString("\n", $output);
        self::assertStringNotContainsString('Consuming pending export', $output);
        self::assertStringNotContainsString('PID:', $output);

        $decoded = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('pw:flat:sync', $decoded['tool']);
        self::assertSame('consume-pending', $decoded['mode']);
    }
}
