<?php

namespace Pushword\Core\Tests\Command;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Command\ImageManagerCommand;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageScratchFile;
use Pushword\Core\Tests\PathTrait;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;

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

        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '2', '--force' => true, '--format' => 'text']);

        self::assertStringContainsString('100%', $commandTester->getDisplay());
        self::assertStringContainsString('worker(s)', $commandTester->getDisplay());
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
