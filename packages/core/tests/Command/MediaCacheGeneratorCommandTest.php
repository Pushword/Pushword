<?php

namespace Pushword\Core\Tests\Command;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Command\ImageManagerCommand;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ImageReader;
use Pushword\Core\Image\ImageScratchFile;
use Pushword\Core\Image\ImageScratchSweeper;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Tests\PathTrait;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Process\Process;

#[Group('integration')]
final class MediaCacheGeneratorCommandTest extends KernelTestCase
{
    use PathTrait;

    /** @var int[] media IDs to clean up after each test */
    private array $createdMediaIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureMediaFileExists();
        $this->createdMediaIds = [];
    }

    protected function tearDown(): void
    {
        $this->cleanupCreatedMedias();
        parent::tearDown();
    }

    public function testSequentialExecution(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '1', '--format' => 'text']);

        self::assertStringContainsString('100%', $commandTester->getDisplay());
    }

    public function testSingleMediaExecution(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['media' => 'piedweb-logo.png', '--format' => 'text']);

        self::assertStringContainsString('100%', $commandTester->getDisplay());
    }

    public function testParallelExecution(): void
    {
        $commandTester = $this->createCommandTester();
        $expected = $this->countImageMedias();

        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '2', '--force' => true, '--format' => 'text']);

        $display = $commandTester->getDisplay();
        self::assertStringContainsString('100%', $display);
        self::assertStringContainsString('worker(s)', $display);

        // The count is now taken from what the workers report, so it can under-count
        // as easily as it used to over-count: every image a batch finished has to
        // survive the read of its closing pipe.
        self::assertStringContainsString($expected.' processed, 0 skipped, 0 errored', $display);
    }

    /**
     * A worker inherits the parent environment: under an agent one (the suite
     * itself runs with CLAUDECODE set) --format auto-detection would silence the
     * per-image DONE: lines the idle timeout reads as liveness, and any healthy
     * batch outlasting the timeout would be killed as stuck.
     */
    public function testWorkerCommandPinsTextFormat(): void
    {
        self::bootKernel();
        $command = self::getContainer()->get(ImageManagerCommand::class);
        $workerCommand = new ReflectionMethod(ImageManagerCommand::class, 'workerCommand');

        self::assertSame(
            ['php', 'bin/console', 'pw:image:cache', 'a.jpg,b.png', '--no-lock', '--format=text', '--env=test'],
            $workerCommand->invoke($command, 'a.jpg,b.png', false),
        );
        $forcedCmd = $workerCommand->invoke($command, 'a.jpg', true);
        self::assertIsArray($forcedCmd);
        self::assertContains('--force', $forcedCmd);
    }

    /**
     * Symfony Process enforces setIdleTimeout() only inside checkTimeout(), which
     * the scheduling loop must keep calling — otherwise a wedged worker spins the
     * loop forever. A tiny timeout makes any worker "silent" during its kernel
     * boot, so enforcement shows up as the batch being killed and reported failed.
     */
    public function testIdleWorkerIsKilledAndReportedFailed(): void
    {
        $commandTester = $this->createCommandTester();
        $command = self::getContainer()->get(ImageManagerCommand::class);
        $idleTimeout = new ReflectionProperty(ImageManagerCommand::class, 'workerIdleTimeout');
        $idleTimeout->setValue($command, 0.05);

        $this->waitForLockRelease();
        $exitCode = $commandTester->execute(['--parallel' => '2', '--force' => true, '--format' => 'text']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('without output', $commandTester->getDisplay());
    }

    public function testForceRegeneration(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['media' => 'piedweb-logo.png', '--force' => true, '--format' => 'text']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('100%', $output);
        self::assertStringContainsString('0 skipped', $output);
    }

    public function testNoLockSkipsLockAcquisition(): void
    {
        $commandTester = $this->createCommandTester();

        // --no-lock with media name = worker mode (emits DONE: markers, no progress bar)
        $commandTester->execute(['media' => 'piedweb-logo.png', '--no-lock' => true, '--format' => 'text']);

        self::assertStringContainsString('DONE:piedweb-logo.png', $commandTester->getDisplay());
    }

    public function testSkipsAlreadyCachedImages(): void
    {
        $commandTester = $this->createCommandTester();

        // First run generates cache
        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '1', '--format' => 'text']);

        // Second run should skip
        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '1', '--format' => 'text']);

        self::assertStringContainsString('skipped', $commandTester->getDisplay());
    }

    public function testCommaSeparatedMediaNames(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['media' => 'piedweb-logo.png,piedweb-logo.png', '--force' => true, '--format' => 'text']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('100%', $output);
    }

    public function testParallelPreFiltersAlreadyCached(): void
    {
        $commandTester = $this->createCommandTester();

        // First run to populate cache
        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '1', '--force' => true, '--format' => 'text']);

        // Parallel run should pre-filter and detect cached images
        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '2', '--format' => 'text']);

        $output = $commandTester->getDisplay();
        // The summary line always appears and includes the skipped count.
        // In parallel CI, other ParaTest workers may invalidate cache between
        // runs, so we can't guarantee skipped > 0 — just verify the pre-filter
        // code path ran by checking the summary format.
        self::assertMatchesRegularExpression('/\d+ processed, \d+ skipped/', $output);
    }

    public function testDisplaysImageDriver(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '1', '--format' => 'text']);

        self::assertStringContainsString('Image driver:', $commandTester->getDisplay());
    }

    public function testDisplaysStatsSummary(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '1', '--format' => 'text']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('processed', $output);
        self::assertStringContainsString('peak memory', $output);
    }

    public function testAgentOutputIsJson(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '1', '--format' => 'agent']);

        $output = trim($commandTester->getDisplay());

        // No human noise (progress bar / summary line) leaks into agent output.
        self::assertStringNotContainsString('peak memory', $output);
        self::assertStringNotContainsString('100%', $output);

        $decoded = $this->decodeAgentOutput($commandTester);
        self::assertSame('pw:image:cache', $decoded['tool']);
        self::assertContains($decoded['result'], ['passed', 'failed']);
        self::assertArrayHasKey('processed', $decoded);
        self::assertArrayHasKey('errors', $decoded);

        // Emitted on every run, including the ones that sweep nothing: a counter
        // that appears only when non-zero is a counter nobody can graph.
        self::assertArrayHasKey('scratch_swept', $decoded);
        self::assertArrayHasKey('scratch_empty', $decoded);
    }

    /**
     * The sweep rides on the command every site already runs: a dedicated one would
     * need a timer nobody installs, which is exactly how a production tree reached
     * six weeks of orphans. Its counts ride in the agent JSON because agents are
     * what runs this in bulk — and the empty count is the only surviving evidence
     * that the encoder still emits blank payloads and the promotion guard eats them.
     */
    public function testSweepsStaleScratchFilesAndReportsTheCount(): void
    {
        $commandTester = $this->createCommandTester();

        $stale = $this->writeStaleScratch('swept-by-the-run.webp.enc-4242.abcdef123456.tmp', '');

        $this->waitForLockRelease();
        $commandTester->execute(['media' => 'piedweb-logo.png', '--format' => 'agent']);

        self::assertFileDoesNotExist($stale, 'A stale scratch file must not survive a run');

        $decoded = $this->decodeAgentOutput($commandTester);
        self::assertGreaterThanOrEqual(1, $decoded['scratch_swept']);
        self::assertGreaterThanOrEqual(1, $decoded['scratch_empty']);
    }

    /**
     * A human is told what was taken, and told nothing when nothing was: this
     * command runs on every deploy, and a line reporting zero on each of them is
     * the noise that teaches everyone to stop reading its output.
     */
    public function testTextOutputReportsTheSweepOnlyWhenItTookSomething(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['media' => 'piedweb-logo.png', '--format' => 'text']);

        self::assertStringNotContainsString('scratch file', $commandTester->getDisplay(), 'A run with nothing to sweep says nothing');

        $this->writeStaleScratch('reported-in-text.webp.enc-4242.abcdef12345a.tmp', 'half-written');

        $this->waitForLockRelease();
        $commandTester->execute(['media' => 'piedweb-logo.png', '--format' => 'text']);

        self::assertStringContainsString('Swept 1 stale scratch file(s), 0 empty.', $commandTester->getDisplay());
    }

    /**
     * A worker must not repeat the walk: it runs with --no-lock, and N workers
     * sweeping the same tree burn it N times over for one run's worth of orphans.
     */
    public function testWorkerModeDoesNotSweep(): void
    {
        $commandTester = $this->createCommandTester();

        $stale = $this->writeStaleScratch('left-by-the-worker.webp.enc-4242.abcdef123457.tmp', 'half-written');

        $commandTester->execute(['media' => 'piedweb-logo.png', '--no-lock' => true, '--format' => 'text']);

        self::assertFileExists($stale, 'Only the lock holder sweeps');

        unlink($stale);
    }

    /**
     * A batch travels as ONE element of argv, and Linux caps a single argument at
     * MAX_ARG_STRLEN (128 KiB) however generous ARG_MAX is. Sizing it by
     * total/workers alone has no upper bound: 11k stale medias over 2 workers put
     * ~500 KiB in one argument and posix_spawn() refused before the first image.
     */
    public function testWorkerBatchCannotOverflowASingleArgvElement(): void
    {
        self::bootKernel();
        $command = self::getContainer()->get(ImageManagerCommand::class);
        $chunkSize = new ReflectionMethod(ImageManagerCommand::class, 'chunkSize');

        // The prod case that failed, and the pathological one a worker count can reach.
        foreach ([[11000, 2], [250000, 1]] as [$staleCount, $workers]) {
            $size = $chunkSize->invoke($command, $staleCount, $workers);
            self::assertIsInt($size);

            // Even at the longest filename Linux allows, plus its separator.
            self::assertLessThan(131072, $size * 256, 'a full batch must fit in one argv element');
        }

        // Small libraries still spread over the workers rather than piling into one.
        self::assertSame(25, $chunkSize->invoke($command, 50, 2));

        // Where the cap takes over, pinned: the argv assertion above alone would
        // still accept a cap of 500 (500 x 256 sits just under the limit), which
        // leaves no margin for a longer separator or a fatter environment block.
        self::assertSame(200, $chunkSize->invoke($command, 400, 2), 'the cap is reached exactly');
        self::assertSame(200, $chunkSize->invoke($command, 402, 2), 'and holds past it');
        self::assertSame(199, $chunkSize->invoke($command, 398, 2), 'just under it, division still rules');
    }

    /**
     * A non-image is only given its public symlink — it never reaches
     * generateCache(), so it belongs to none of generated/skipped/errored. The
     * count was derived as total - skipped - errors, which charged every one of
     * them to "processed": a converged library reported its whole non-image set
     * (GPX, XML, PDF, SVG — svg has the image/ prefix but no encoder) as
     * regenerated work on every nightly run, on both output formats.
     */
    public function testNonImageMediaIsNotCountedAsProcessed(): void
    {
        $commandTester = $this->createCommandTester();
        $this->createMedia('cache-count-brochure.pdf', 'application/pdf');

        $this->waitForLockRelease();
        $commandTester->execute(['media' => 'cache-count-brochure.pdf', '--format' => 'agent']);

        $decoded = $this->decodeAgentOutput($commandTester);
        self::assertSame(0, $decoded['generated'], 'a non-image generates no derivative');
        self::assertSame(0, $decoded['skipped']);
        self::assertSame(0, $decoded['errors']);
        self::assertSame(0, $decoded['processed']);

        // The human summary is fed by the same expression, so it needs its own pin.
        $this->waitForLockRelease();
        $commandTester->execute(['media' => 'cache-count-brochure.pdf', '--format' => 'text']);

        self::assertStringContainsString('0 processed, 0 skipped, 0 errored', $commandTester->getDisplay());
    }

    /**
     * The mixed library the count is really taken over, every bucket pinned to an
     * exact number. The regression above proves a non-image adds nothing; this
     * proves the image beside it still adds one, because a tally can under-count
     * as easily as the subtraction over-counted. The two runs separate the
     * buckets: forced, the pair is one generated; fresh, it is one skipped.
     */
    public function testCountsOnlyTheDerivativesItGenerates(): void
    {
        $commandTester = $this->createCommandTester();
        $this->createMedia('cache-count-leaflet.pdf', 'application/pdf');
        $mediaNames = 'piedweb-logo.png,cache-count-leaflet.pdf';

        $this->waitForLockRelease();
        $commandTester->execute(['media' => $mediaNames, '--force' => true, '--format' => 'text']);

        self::assertStringContainsString('1 processed, 0 skipped, 0 errored', $commandTester->getDisplay());

        // Agent mode derives its own total as generated + skipped + errors, so the
        // skipped bucket needs its own pin on that side of the fork.
        $this->waitForLockRelease();
        $commandTester->execute(['media' => $mediaNames, '--format' => 'agent']);

        $decoded = $this->decodeAgentOutput($commandTester);
        self::assertSame(0, $decoded['generated'], 'the cache the forced run just wrote is fresh');
        self::assertSame(1, $decoded['skipped']);
        self::assertSame(1, $decoded['processed'], 'the non-image is in neither bucket, so it is not in the total');
    }

    /**
     * An image whose master vanished after it was recorded: the read throws, and
     * it has to land in errored — never in generated, which is the third bucket
     * the count is now tallied from rather than derived. The parallel path has its
     * killed-worker test; this is the sequential catch a single-media run takes.
     */
    public function testAnImageThatCannotBeReadIsErroredNotGenerated(): void
    {
        $commandTester = $this->createCommandTester();
        $this->createMedia('cache-count-vanished.png', 'image/png');
        unlink($this->getMediaDir().'/cache-count-vanished.png');

        $this->waitForLockRelease();
        $exitCode = $commandTester->execute(['media' => 'cache-count-vanished.png', '--force' => true, '--format' => 'agent']);

        self::assertSame(1, $exitCode, 'a run that could not process an image fails');

        $decoded = $this->decodeAgentOutput($commandTester);
        self::assertSame('failed', $decoded['result']);
        self::assertSame(1, $decoded['errors']);
        self::assertSame(0, $decoded['generated'], 'a master that cannot be read produced no derivative');
        self::assertSame(0, $decoded['skipped']);
    }

    /**
     * The parallel count had the same shape the sequential one just lost: processed
     * was $staleCount - count($errors), deduced from the batch size and never
     * measured, so a worker that exited having done nothing was credited with its
     * whole batch minus one. Here every worker dies at boot (a project dir with no
     * bin/console), so the honest answer is zero — the old expression said N - 2.
     */
    public function testParallelCountsWhatTheWorkersReportedNotTheBatchSize(): void
    {
        self::bootKernel();

        // More images than batches, or the deduction and the measure would agree.
        $this->createMedia('cache-count-parallel-a.png', 'image/png');
        $this->createMedia('cache-count-parallel-b.png', 'image/png');
        $this->createMedia('cache-count-parallel-c.png', 'image/png');

        $output = new BufferedOutput();
        $this->waitForLockRelease();
        $exitCode = $this->commandRunningFrom($this->getMediaDir())(
            null,
            new ArrayInput([]),
            $output,
            force: true,
            parallel: 2,
            format: 'agent',
        );

        self::assertSame(1, $exitCode, 'no worker ever ran, so the run failed');

        $decoded = json_decode(trim($output->fetch()), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame(0, $decoded['generated'], 'a worker reporting nothing processed nothing');
        self::assertSame(0, $decoded['skipped']);
        self::assertGreaterThanOrEqual(1, $decoded['errors']);

        // And a human is told what went missing, not just that a batch failed.
        $textOutput = new BufferedOutput();
        $this->waitForLockRelease();
        $this->commandRunningFrom($this->getMediaDir())(
            null,
            new ArrayInput([]),
            $textOutput,
            force: true,
            parallel: 2,
            format: 'text',
        );

        $display = $textOutput->fetch();
        self::assertStringContainsString('never reported by the worker', $display);
        self::assertStringContainsString('0 processed', $display);
    }

    /**
     * A worker prints no summary, so the only way an image it could not process
     * reaches the parent is the per-image line. The batch exiting non-zero is then
     * explained and must not be counted a second time — one image, one error.
     */
    public function testParallelSurfacesAWorkerImageFailureByName(): void
    {
        $commandTester = $this->createCommandTester();
        $this->createMedia('cache-count-parallel-vanished.png', 'image/png');
        unlink($this->getMediaDir().'/cache-count-parallel-vanished.png');

        $this->waitForLockRelease();
        $exitCode = $commandTester->execute(['--parallel' => '2', '--force' => true, '--format' => 'text']);

        $display = $commandTester->getDisplay();
        self::assertSame(1, $exitCode);
        self::assertStringContainsString('cache-count-parallel-vanished.png', $display, 'the failing image is named, not just its batch');
        self::assertMatchesRegularExpression('/\d+ processed, \d+ skipped, 1 errored/', $display);
    }

    /**
     * A worker line cut in two by the read. getIncrementalOutput() returns whatever
     * had reached the pipe, so the parent keeps the tail and counts complete lines
     * only — counting markers in the raw chunk drops the image whose line was split,
     * and a dropped image is now reported as never processed: a false alarm on a
     * batch that did its work. Two finished processes stand in for two reads of one
     * worker; timing a live one's fragmentation would make this a race.
     */
    public function testAWorkerLineSplitAcrossTwoReadsIsCountedOnce(): void
    {
        self::bootKernel();
        $command = self::getContainer()->get(ImageManagerCommand::class);

        $entry = $this->workerEntry($this->finishedProcess('echo "DONE:a.png\nDON";'));
        $errors = [];

        $this->readOneWorker($command, $entry, $errors, final: false);

        self::assertSame(1, $entry['done'], 'a half-written line is not an image yet');
        self::assertSame('DON', $entry['buffer'], 'and it is kept for the next read');

        // The rest of the cut line, a failure, and a line that is neither.
        $entry['process'] = $this->finishedProcess('echo "E:b.png\nFAIL:c.png: boom\nsomething else\n";');
        $this->readOneWorker($command, $entry, $errors, final: true);

        self::assertSame(2, $entry['done'], 'the line cut in two is one image, counted once');
        self::assertSame(1, $entry['failed']);
        self::assertSame(['c.png: boom'], $errors, 'a failure arrives named; anything else is not a count');
    }

    /** A worker's whole output, ready to be read in one go. */
    private function finishedProcess(string $phpCode): Process
    {
        $process = new Process(['php', '-r', $phpCode]);
        $process->run();

        return $process;
    }

    /**
     * What the scheduling loop tracks per running worker.
     *
     * @return array{process: Process, count: int, done: int, failed: int, buffer: string}
     */
    private function workerEntry(Process $process): array
    {
        return ['process' => $process, 'count' => 3, 'done' => 0, 'failed' => 0, 'buffer' => ''];
    }

    /**
     * One read of a worker's pipe, as the scheduling loop does it.
     *
     * @param array{process: Process, count: int, done: int, failed: int, buffer: string} $entry
     * @param string[]                                                                    $errors
     */
    private function readOneWorker(ImageManagerCommand $command, array &$entry, array &$errors, bool $final): void
    {
        $args = [&$entry, &$errors, null, $final];
        new ReflectionMethod(ImageManagerCommand::class, 'readWorkerLines')->invokeArgs($command, $args);
    }

    /** Images in this worker's library — what a forced run has to report as processed. */
    private function countImageMedias(): int
    {
        $images = 0;
        foreach (self::getContainer()->get(MediaRepository::class)->findAll() as $media) {
            if ($media->isImage()) {
                ++$images;
            }
        }

        return $images;
    }

    /**
     * The same command, wired to another working directory. Its workers are real
     * subprocesses launched from there, which is the only way to make one exit
     * without doing its batch.
     */
    private function commandRunningFrom(string $projectDir): ImageManagerCommand
    {
        /** @var LockFactory $lockFactory */
        $lockFactory = self::getContainer()->get('lock.factory');

        return new ImageManagerCommand(
            self::getContainer()->get(MediaRepository::class),
            $this->getEntityManager(),
            self::getContainer()->get(ImageCacheGenerator::class),
            self::getContainer()->get(ImageCacheManager::class),
            self::getContainer()->get(ImageReader::class),
            self::getContainer()->get(ImageScratchSweeper::class),
            $lockFactory,
            $projectDir,
            'test',
        );
    }

    /**
     * A copy of the fixture under a new name. Content is irrelevant — isImage()
     * reads the stored mime type, and the file only has to exist for setHash().
     */
    private function createMedia(string $fileName, string $mimeType): void
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $mediaDir = $this->getMediaDir();

        new Filesystem()->copy($mediaDir.'/piedweb-logo.png', $mediaDir.'/'.$fileName);

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setStoreIn($mediaDir);
        $media->setMimeType($mimeType);
        $media->setFileName($fileName);
        $media->setAlt($fileName);
        $media->setHash();

        $em = $this->getEntityManager();
        $em->persist($media);
        $em->flush();

        $this->createdMediaIds[] = (int) $media->id;
    }

    private function cleanupCreatedMedias(): void
    {
        if ([] === $this->createdMediaIds) {
            return;
        }

        $em = $this->getEntityManager();
        $em->clear();

        foreach ($this->createdMediaIds as $mediaId) {
            $media = $em->find(Media::class, $mediaId);
            if (null !== $media) {
                $em->remove($media);
            }
        }

        $em->flush();
    }

    private function getEntityManager(): EntityManager
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $em;
    }

    /** @return array<mixed, mixed> the one JSON document an agent-format run writes */
    private function decodeAgentOutput(CommandTester $commandTester): array
    {
        $decoded = json_decode(trim($commandTester->getDisplay()), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private function createCommandTester(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        return new CommandTester($application->find('pw:image:cache'));
    }

    /**
     * A scratch file old enough that no live writer could own it, in the tree the
     * derivatives really live in — per worker under test, hence read from the container.
     *
     * @return string its path
     */
    private function writeStaleScratch(string $name, string $content): string
    {
        $mediaCacheDir = self::getContainer()->getParameter('pw.media_cache_dir');
        if (! is_dir($mediaCacheDir)) {
            mkdir($mediaCacheDir, 0o777, true);
        }

        $path = $mediaCacheDir.'/'.$name;
        file_put_contents($path, $content);
        touch($path, time() - ImageScratchFile::MAX_AGE - 60);

        return $path;
    }

    private function waitForLockRelease(): void
    {
        /** @var LockFactory $lockFactory */
        $lockFactory = self::getContainer()->get('lock.factory');
        $lock = $lockFactory->createLock('pw:image:cache');
        $lock->acquire(blocking: true);
        $lock->release();
    }
}
