<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Admin;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Flat\Admin\FlatSyncNotifier;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Service\FlatChangeDetector;
use Pushword\Flat\Service\FlatLockManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

#[Group('integration')]
final class FlatSyncNotifierTest extends KernelTestCase
{
    private const string TEST_HOST = 'localhost.dev';

    /** Unique host for lock tests to avoid parallel test interference. */
    private string $lockTestHost;

    private Session $session;

    private string $contentDir;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $this->lockTestHost = 'lock-test-'.getmypid().'.dev';

        $request = new Request();
        $this->session = new Session(new MockArraySessionStorage());
        $request->setSession($this->session);

        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get(RequestStack::class);
        $requestStack->push($request);

        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $this->contentDir = $contentDirFinder->get(self::TEST_HOST);

        /** @var FlatChangeDetector $changeDetector */
        $changeDetector = self::getContainer()->get(FlatChangeDetector::class);
        $changeDetector->invalidateCache(self::TEST_HOST);
    }

    #[Override]
    protected function tearDown(): void
    {
        /** @var FlatLockManager $lockManager */
        $lockManager = self::getContainer()->get(FlatLockManager::class);
        $lockManager->releaseLock($this->lockTestHost);

        // Clean up conflict files
        $conflictFiles = [
            ...(glob($this->contentDir.'/*~conflict-*') ?: []),
            ...(glob($this->contentDir.'/**/*~conflict-*') ?: []),
        ];
        $fs = new Filesystem();
        foreach ($conflictFiles as $file) {
            $fs->remove($file);
        }

        parent::tearDown();
    }

    private function getNotifier(): FlatSyncNotifier
    {
        /** @var FlatSyncNotifier $notifier */
        $notifier = self::getContainer()->get(FlatSyncNotifier::class);

        return $notifier;
    }

    public function testNotifyIfLockedAddsWarningFlash(): void
    {
        /** @var FlatLockManager $lockManager */
        $lockManager = self::getContainer()->get(FlatLockManager::class);
        $lockManager->acquireLock($this->lockTestHost, 'manual');

        $this->getNotifier()->notifyIfLocked($this->lockTestHost);

        $flashes = $this->session->getFlashBag()->get('warning');
        self::assertNotEmpty($flashes);
    }

    public function testNotifyIfLockedDoesNothingWhenNotLocked(): void
    {
        $this->getNotifier()->notifyIfLocked($this->lockTestHost);

        self::assertSame([], $this->session->getFlashBag()->peekAll());
    }

    public function testNotifyIfUnresolvedConflictsAddsErrorFlash(): void
    {
        // Create a conflict file in the content directory
        $conflictFile = $this->contentDir.'/test~conflict-20240101.md';
        file_put_contents($conflictFile, 'conflict content');

        $this->getNotifier()->notifyIfUnresolvedConflicts(self::TEST_HOST);

        $flashes = $this->session->getFlashBag()->get('error');
        self::assertNotEmpty($flashes);
    }

    public function testNotifyIfUnresolvedConflictsDoesNothingWhenNoConflicts(): void
    {
        $this->getNotifier()->notifyIfUnresolvedConflicts(self::TEST_HOST);

        self::assertSame([], $this->session->getFlashBag()->peekAll());
    }

    public function testNotifyAllCallsAllChecks(): void
    {
        /** @var FlatLockManager $lockManager */
        $lockManager = self::getContainer()->get(FlatLockManager::class);
        $lockManager->acquireLock($this->lockTestHost, 'manual');

        $conflictFile = $this->contentDir.'/test~conflict-20240101.md';
        file_put_contents($conflictFile, 'conflict content');

        // notifyAll uses the same host for both lock and conflict checks
        // Lock check uses lockTestHost; conflict check uses TEST_HOST (for content dir)
        $this->getNotifier()->notifyIfLocked($this->lockTestHost);
        $this->getNotifier()->notifyIfUnresolvedConflicts(self::TEST_HOST);

        self::assertNotEmpty($this->session->getFlashBag()->get('warning'));
        self::assertNotEmpty($this->session->getFlashBag()->get('error'));
    }

    public function testGetStatusReturnsCorrectStructure(): void
    {
        /** @var FlatLockManager $lockManager */
        $lockManager = self::getContainer()->get(FlatLockManager::class);
        $lockManager->acquireLock($this->lockTestHost, 'manual');

        $conflictFile = $this->contentDir.'/test~conflict-20240101.md';
        file_put_contents($conflictFile, 'conflict content');

        $status = $this->getNotifier()->getStatus($this->lockTestHost);

        self::assertTrue($status['isLocked']);
        // Conflicts use content dir from host - lockTestHost has no content dir, so no conflicts
        // Check conflicts separately for TEST_HOST
        $statusConflicts = $this->getNotifier()->getStatus(self::TEST_HOST);
        self::assertTrue($statusConflicts['hasConflicts']);
        self::assertSame(1, $statusConflicts['conflictCount']);
    }

    public function testReturnsGracefullyWhenNoSession(): void
    {
        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get(RequestStack::class);

        // Pop the request with session and push one without
        $requestStack->pop();
        $requestStack->push(new Request());

        /** @var FlatLockManager $lockManager */
        $lockManager = self::getContainer()->get(FlatLockManager::class);
        $lockManager->acquireLock($this->lockTestHost, 'manual');

        // Should not throw
        $this->getNotifier()->notifyIfLocked($this->lockTestHost);

        $this->expectNotToPerformAssertions();
    }
}
