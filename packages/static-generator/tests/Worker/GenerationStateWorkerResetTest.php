<?php

namespace Pushword\StaticGenerator\Tests\Worker;

use DateTimeInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\StaticGenerator\GenerationStateManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * `GET /api/static/{host}` reports `lastGeneratedAt` from the generation state
 * file, which the manager memoizes on first read. Under a long-running worker the
 * kernel outlives the request, so a poll made after another process finished a
 * generation would keep reporting the *previous* timestamp — for the life of the
 * worker — if that copy were not flushed between requests. Paired with a correct
 * `queued`/`running` status this is the worse failure: the poll says "completed"
 * and hands back a timestamp from before the build it is being asked about.
 *
 * The test replays two requests around the exact reset a worker performs between
 * them, with an out-of-band generation in between.
 */
#[Group('integration')]
#[Group('worker')]
final class GenerationStateWorkerResetTest extends KernelTestCase
{
    private const string HOST = 'static-worker-probe.dev';

    private const string BEFORE = '2026-08-14T06:06:28+00:00';

    private const string AFTER = '2026-08-14T20:08:50+00:00';

    private ?string $savedState = null;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        // Captured before the first write, so tearDown restores what the rest of
        // the suite left here rather than what this test put there.
        $path = $this->stateFilePath();
        $this->savedState = is_file($path) ? (string) file_get_contents($path) : null;
    }

    protected function tearDown(): void
    {
        $path = $this->stateFilePath();
        if (null === $this->savedState) {
            @unlink($path);
        } else {
            file_put_contents($path, $this->savedState);
        }

        parent::tearDown();
    }

    public function testAPollSeesAGenerationAnotherProcessJustFinished(): void
    {
        $stateManager = self::getContainer()->get(GenerationStateManager::class);

        // --- Request A: the poll reads, and memoizes, the state on disk. ---
        $this->writeStateOutOfBand(self::BEFORE);
        $stateManager->reload();
        self::assertSame(self::BEFORE, $this->lastGeneration($stateManager));

        // The CLI pass (another process) finishes a generation for this host.
        $this->writeStateOutOfBand(self::AFTER);

        // The copy is warm but stale: it predates that generation. This is what
        // the reset must fix — and it proves the guard below is not vacuous.
        self::assertSame(self::BEFORE, $this->lastGeneration($stateManager));

        // --- Between requests: exactly what a FrankenPHP/Runtime worker runs. ---
        self::getContainer()->get('services_resetter')->reset();

        // --- Request B: the poll must report the generation that just landed. ---
        self::assertSame(self::AFTER, $this->lastGeneration($stateManager));
    }

    private function lastGeneration(GenerationStateManager $stateManager): ?string
    {
        return $stateManager->getLastGenerationTime(self::HOST)?->format(DateTimeInterface::ATOM);
    }

    /**
     * Write the state file the way another process would: straight to disk,
     * leaving the entries of hosts this test does not own untouched.
     */
    private function writeStateOutOfBand(string $lastGeneration): void
    {
        $path = $this->stateFilePath();

        $existing = is_file($path) ? (string) file_get_contents($path) : null;

        /** @var array<string, mixed> $state */
        $state = null === $existing ? [] : (json_decode($existing, true) ?? []);
        $state[self::HOST] = ['lastGeneration' => $lastGeneration, 'pages' => []];

        file_put_contents($path, json_encode($state, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
    }

    /** Mirrors GenerationStateManager::getStateFilePath(). */
    private function stateFilePath(): string
    {
        $testVarDir = getenv('PUSHWORD_TEST_VAR_DIR');
        if (false !== $testVarDir && '' !== $testVarDir) {
            return $testVarDir.'/.static-generation-state.json';
        }

        return self::getContainer()->getParameter('kernel.project_dir').'/var/.static-generation-state.json';
    }
}
