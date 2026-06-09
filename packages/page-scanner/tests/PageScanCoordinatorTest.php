<?php

namespace Pushword\PageScanner\Tests;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\Core\Site\SiteRegistry;
use Pushword\PageScanner\Service\PageScanCoordinator;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class PageScanCoordinatorTest extends KernelTestCase
{
    private string $varDir = '';

    private ProcessOutputStorage $outputStorage;

    private string|false $previousTestVarDir = false;

    protected function setUp(): void
    {
        self::bootKernel();
        // This test injects explicit temp dirs and asserts against them, so opt out
        // of the per-worker PUSHWORD_TEST_VAR_DIR override the services honor.
        $this->previousTestVarDir = getenv('PUSHWORD_TEST_VAR_DIR');
        putenv('PUSHWORD_TEST_VAR_DIR');
        $this->varDir = sys_get_temp_dir().'/pw-coordinator-'.uniqid();
        $this->outputStorage = new ProcessOutputStorage(new Filesystem(), $this->varDir);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
        putenv(false === $this->previousTestVarDir ? 'PUSHWORD_TEST_VAR_DIR' : 'PUSHWORD_TEST_VAR_DIR='.$this->previousTestVarDir);
        parent::tearDown();
    }

    public function testDispatchErrorIsSurfacedThroughOutputStorage(): void
    {
        $dispatcher = self::createStub(BackgroundTaskDispatcherInterface::class);
        $dispatcher->method('dispatch')->willThrowException(new RuntimeException('nohup failed'));

        $this->coordinator($dispatcher)->startScan(null);

        self::assertSame('error', $this->outputStorage->getStatus('page-scanner'));
        self::assertStringContainsString('nohup failed', $this->outputStorage->read('page-scanner')['content']);
    }

    public function testDispatchErrorWithHostUsesPerHostProcessType(): void
    {
        $dispatcher = self::createStub(BackgroundTaskDispatcherInterface::class);
        $dispatcher->method('dispatch')->willThrowException(new RuntimeException('nohup failed'));

        $this->coordinator($dispatcher)->startScan('localhost.dev');

        self::assertSame('error', $this->outputStorage->getStatus('page-scanner--localhost.dev'));
        self::assertStringContainsString('nohup failed', $this->outputStorage->read('page-scanner--localhost.dev')['content']);
    }

    public function testStartScanMarksProcessRunningOnSuccess(): void
    {
        $this->coordinator(self::createStub(BackgroundTaskDispatcherInterface::class))->startScan(null);

        self::assertSame('running', $this->outputStorage->getStatus('page-scanner'));
    }

    public function testReadResultsFiltersIgnoredErrorsAndKeepsTheRest(): void
    {
        $errors = [42 => [
            ['page' => ['host' => 'localhost.dev', 'slug' => 'a'], 'message' => '404 <a href="/x">/x</a>'],
            ['page' => ['host' => 'localhost.dev', 'slug' => 'a'], 'message' => 'known noise'],
        ]];
        new Filesystem()->dumpFile($this->varDir.'/page-scan', serialize($errors));

        $results = $this->coordinator(
            self::createStub(BackgroundTaskDispatcherInterface::class),
            errorsToIgnore: ['*: known noise'],
        )->readResults(null);

        self::assertGreaterThan(0, $results['lastEdit']);
        self::assertCount(1, $results['errorsByPages'][42]);
        self::assertSame('404 <a href="/x">/x</a>', $results['errorsByPages'][42][0]['message']);
    }

    /**
     * @param string[] $errorsToIgnore
     */
    private function coordinator(BackgroundTaskDispatcherInterface $dispatcher, array $errorsToIgnore = []): PageScanCoordinator
    {
        $processManager = self::createStub(BackgroundProcessManager::class);
        $processManager->method('getProcessInfo')->willReturn(['isRunning' => false, 'startTime' => null, 'pid' => null]);
        $processManager->method('getPidFilePath')->willReturn($this->varDir.'/scanner.pid');

        return new PageScanCoordinator(
            new Filesystem(),
            $this->varDir,
            'PT5M',
            $dispatcher,
            $processManager,
            $this->outputStorage,
            self::getContainer()->get(SiteRegistry::class),
            $errorsToIgnore,
        );
    }
}
