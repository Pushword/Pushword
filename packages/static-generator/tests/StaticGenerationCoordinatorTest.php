<?php

namespace Pushword\StaticGenerator\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\BackgroundTask\MessengerBackgroundTaskDispatcher;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\GenerationStateManager;
use Pushword\StaticGenerator\StaticGenerationCoordinator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[Group('integration')]
final class StaticGenerationCoordinatorTest extends KernelTestCase
{
    private string $varDir = '';

    private string|false $previousVarDir = false;

    private ProcessOutputStorage $outputStorage;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->varDir = sys_get_temp_dir().'/pw-static-coordinator-'.uniqid();
        $this->outputStorage = new ProcessOutputStorage(new Filesystem(), $this->varDir);

        // GenerationStateManager resolves its state file from this env var, so
        // pointing it here is the only way to start from an empty state: the
        // worker's own var dir carries whatever earlier tests generated.
        $this->previousVarDir = getenv('PUSHWORD_TEST_VAR_DIR');
        putenv('PUSHWORD_TEST_VAR_DIR='.$this->varDir);
    }

    protected function tearDown(): void
    {
        if (false === $this->previousVarDir) {
            putenv('PUSHWORD_TEST_VAR_DIR');
        } else {
            putenv('PUSHWORD_TEST_VAR_DIR='.$this->previousVarDir);
        }

        new Filesystem()->remove($this->varDir);
        parent::tearDown();
    }

    public function testStartGenerationMarksProcessRunning(): void
    {
        $this->coordinator()->startGeneration(null);

        self::assertSame('running', $this->outputStorage->getStatus('static-generator'));
    }

    /**
     * Both front-ends read this output as text; an inherited agent env must never
     * switch the web-spawned generation to its one-line JSON format.
     */
    public function testStartGenerationForcesTextFormatAndScopesTheCommandToTheHost(): void
    {
        $command = $this->captureDispatch(static fn (StaticGenerationCoordinator $coordinator) => $coordinator->startGeneration('localhost.dev'));

        self::assertSame('php bin/console pw:static localhost.dev --format=text', $command);
    }

    public function testIncrementalReachesTheCommandLine(): void
    {
        $command = $this->captureDispatch(static fn (StaticGenerationCoordinator $coordinator) => $coordinator->startGeneration('localhost.dev', true));

        self::assertStringContainsString('--incremental', $command);
    }

    public function testAllHostsGenerationPassesNoHostArgument(): void
    {
        $command = $this->captureDispatch(static fn (StaticGenerationCoordinator $coordinator) => $coordinator->startGeneration(null));

        self::assertSame('php bin/console pw:static --format=text', $command);
    }

    public function testReadOutputReportsErrorWhenTheConsoleLogMentionsOne(): void
    {
        // The wording StaticAppGenerator::setError() actually emits.
        $failure = 'An error occured when generating localhost.dev/about (status code 500)';
        $this->outputStorage->write('static-generator', "Generating about\n".$failure."\n");

        $state = $this->coordinator()->readOutput('static-generator');

        self::assertSame('error', $state['status']);
        self::assertFalse($state['isRunning']);
        self::assertSame([$failure], $state['errors']);
    }

    public function testReadOutputIsCompletedWhenNothingLooksLikeAnError(): void
    {
        $this->outputStorage->write('static-generator', "Generating a\nlocalhost.dev generated with success.\n");

        $state = $this->coordinator()->readOutput('static-generator');

        self::assertSame('completed', $state['status']);
        self::assertSame([], $state['errors']);
    }

    /**
     * A job still waiting in a messenger queue has no process to find. Deducing
     * its state from that absence answered `completed` — indistinguishable, for a
     * site generated at least once before, from a pass that really ran.
     */
    public function testReadOutputReportsAQueuedPassAsQueuedRatherThanDone(): void
    {
        $this->outputStorage->setStatus('static-generator', 'queued');

        $state = $this->coordinator()->readOutput('static-generator');

        self::assertSame('queued', $state['status']);
        self::assertFalse($state['isRunning']);
    }

    /**
     * The other half of the same distinction: `running` with no live process means
     * the pass died mid-way, and must not be read as still waiting to start.
     */
    public function testReadOutputStillGivesUpOnARunningPassWhoseProcessIsGone(): void
    {
        $this->outputStorage->setStatus('static-generator', 'running');

        self::assertSame('completed', $this->coordinator()->readOutput('static-generator')['status']);
    }

    /**
     * The incident, end to end: messenger mode, every consumer busy elsewhere, so
     * the message is accepted and nothing runs. What the poller reads must say so.
     */
    public function testAGenerationNoConsumerPicksUpReadsAsQueuedNotCompleted(): void
    {
        $bus = self::createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $processManager = self::createStub(BackgroundProcessManager::class);
        $processManager->method('getProcessInfo')->willReturn(['isRunning' => false, 'startTime' => null, 'pid' => null]);
        $processManager->method('getPidFilePath')->willReturn($this->varDir.'/static.pid');

        $coordinator = $this->coordinator(dispatcher: new MessengerBackgroundTaskDispatcher(
            $bus,
            $processManager,
            $this->outputStorage,
            'test',
            [],
        ));

        $coordinator->startGeneration(null);

        self::assertSame('queued', $coordinator->readOutput('static-generator')['status']);
    }

    public function testLastGenerationTimeIsReadPerHost(): void
    {
        $stateManager = $this->stateManager();
        $stateManager->setLastGenerationTime('localhost.dev', new DateTimeImmutable('2026-01-02 03:04:05'));

        $lastGeneration = $this->coordinator($stateManager)->getLastGenerationTime('localhost.dev');

        self::assertNotNull($lastGeneration);
        self::assertSame('2026-01-02 03:04:05', $lastGeneration->format('Y-m-d H:i:s'));
    }

    /**
     * The all-hosts scope answers for the whole export, so it can only be as fresh
     * as its stalest site — and unknown while any site has never been generated.
     */
    public function testAllHostsLastGenerationTimeIsTheOldestOfTheSites(): void
    {
        $stateManager = $this->stateManager();

        $hosts = self::getContainer()->get(SiteRegistry::class)->getHosts();
        self::assertGreaterThan(1, \count($hosts), 'the fixture must configure several sites for this test to mean anything');

        foreach (array_values($hosts) as $i => $knownHost) {
            $stateManager->setLastGenerationTime($knownHost, new DateTimeImmutable('2026-01-0'.($i + 1).' 12:00:00'));
        }

        $oldest = $this->coordinator($stateManager)->getLastGenerationTime(null);
        self::assertNotNull($oldest);
        self::assertSame('2026-01-01 12:00:00', $oldest->format('Y-m-d H:i:s'));
    }

    public function testAllHostsLastGenerationTimeIsUnknownWhileASiteWasNeverGenerated(): void
    {
        $stateManager = $this->stateManager();
        $stateManager->setLastGenerationTime('localhost.dev', new DateTimeImmutable('2026-01-01 12:00:00'));

        self::assertNull($this->coordinator($stateManager)->getLastGenerationTime(null));
    }

    /**
     * Run $act against a coordinator whose dispatcher records instead of spawning,
     * and return the command line it would have run.
     *
     * A real implementation rather than a mock: it keeps the interface's `string[]`
     * contract on the recorded parts, which a callback stub erases.
     *
     * @param callable(StaticGenerationCoordinator): void $act
     */
    private function captureDispatch(callable $act): string
    {
        $dispatcher = new class implements BackgroundTaskDispatcherInterface {
            /** @var string[] */
            public array $commandParts = [];

            public function dispatch(string $processType, array $commandParts, string $commandPattern): void
            {
                $this->commandParts = $commandParts;
            }
        };

        $act($this->coordinator(dispatcher: $dispatcher));

        return implode(' ', $dispatcher->commandParts);
    }

    private function stateManager(): GenerationStateManager
    {
        return new GenerationStateManager($this->varDir);
    }

    private function coordinator(
        ?GenerationStateManager $stateManager = null,
        ?BackgroundTaskDispatcherInterface $dispatcher = null,
    ): StaticGenerationCoordinator {
        $processManager = self::createStub(BackgroundProcessManager::class);
        $processManager->method('getProcessInfo')->willReturn(['isRunning' => false, 'startTime' => null, 'pid' => null]);
        $processManager->method('getPidFilePath')->willReturn($this->varDir.'/static.pid');

        return new StaticGenerationCoordinator(
            $dispatcher ?? self::createStub(BackgroundTaskDispatcherInterface::class),
            $processManager,
            $this->outputStorage,
            self::getContainer()->get(SiteRegistry::class),
            $stateManager ?? $this->stateManager(),
        );
    }
}
