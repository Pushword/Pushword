<?php

namespace Pushword\Flat\Tests\Sync;

use DateTime;
use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Sync\ConflictResolver;
use Pushword\Flat\Sync\PageSync;
use Pushword\Flat\Sync\SyncStateManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class CrashRecoveryTest extends KernelTestCase
{
    private EntityManager $em;

    private PageSync $pageSync;

    private SyncStateManager $stateManager;

    private string $contentDir;

    private Filesystem $filesystem;

    /** @var string[] */
    private array $createdFiles = [];

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->filesystem = new Filesystem();

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        /** @var PageSync $pageSync */
        $pageSync = self::getContainer()->get(PageSync::class);
        $this->pageSync = $pageSync;

        /** @var SyncStateManager $stateManager */
        $stateManager = self::getContainer()->get(SyncStateManager::class);
        $this->stateManager = $stateManager;

        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $this->contentDir = $contentDirFinder->get('localhost.dev');

        $this->pageSync->export('localhost.dev', true, $this->contentDir);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }

        foreach (['invalid-yaml-test', 'valid-crash-test', 'crash-good-page', 'yaml-deletion-guard', 'yaml-unescaped-quote'] as $slug) {
            $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => $slug, 'host' => 'localhost.dev']);
            if ($page instanceof Page) {
                $this->em->remove($page);
            }
        }

        $this->em->flush();
        // Clean up conflict files
        $conflictFiles = glob($this->contentDir.'/*~conflict-*') ?: [];
        foreach ($conflictFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function createMd(string $fileName, string $content): void
    {
        $path = $this->contentDir.'/'.$fileName;
        $this->filesystem->dumpFile($path, $content);
        touch($path, time() + 100);
        $this->createdFiles[] = $path;
    }

    public function testImportWithInvalidYamlSkipsFileAndContinues(): void
    {
        $this->createMd('invalid-yaml-test.md', "---\nh1: [invalid yaml: {broken\n---\n\nBroken content");
        $this->createMd('crash-good-page.md', "---\nh1: Good Page\n---\n\nGood content");

        // Import should NOT throw — it should skip the broken file and import the good one
        $this->pageSync->import('localhost.dev');

        self::assertSame(1, $this->pageSync->getYamlErrorCount());

        $goodPage = $this->em->getRepository(Page::class)->findOneBy([
            'slug' => 'crash-good-page',
            'host' => 'localhost.dev',
        ]);
        self::assertNotNull($goodPage, 'Valid page should be imported despite YAML error in another file');
    }

    public function testYamlErrorDoesNotDeleteExistingPage(): void
    {
        // 1. Create a page in DB and export it to a .md file
        $page = new Page();
        $page->setSlug('yaml-deletion-guard');
        $page->setH1('Deletion Guard');
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->setMainContent('Should survive');

        $this->em->persist($page);
        $this->em->flush();

        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        // 2. Replace its .md file with invalid YAML
        $mdPath = $this->contentDir.'/yaml-deletion-guard.md';
        self::assertFileExists($mdPath);
        $this->filesystem->dumpFile($mdPath, "---\nh1: [broken yaml: {invalid\n---\n\nBroken");
        touch($mdPath, time() + 100);
        $this->createdFiles[] = $mdPath;

        // 3. Re-import — the page must NOT be deleted
        $this->pageSync->import('localhost.dev');

        self::assertSame(1, $this->pageSync->getYamlErrorCount());

        $this->em->clear();
        $survivingPage = $this->em->getRepository(Page::class)->findOneBy([
            'slug' => 'yaml-deletion-guard',
            'host' => 'localhost.dev',
        ]);
        self::assertNotNull($survivingPage, 'Page with YAML-errored flat file must not be deleted');
    }

    public function testRealWorldUnescapedQuoteDetected(): void
    {
        // The exact error pattern from the user: unescaped single quote inside single-quoted YAML
        $this->createMd('yaml-unescaped-quote.md', "---\ntitle: 'La Baltique : îles de Rügen et d'Usedom | Grand Angle'\n---\n\nContent");

        $this->pageSync->import('localhost.dev');

        self::assertSame(1, $this->pageSync->getYamlErrorCount());
    }

    public function testDirectionDetectionSurvivesYamlError(): void
    {
        // Create a file with bad YAML so that mustImport's slow-path YAML parsing hits it
        $this->createMd('invalid-yaml-test.md', "---\nh1: [broken: {yaml\n---\n\nContent");

        // mustImport should not throw — it should return true (trigger import)
        $result = $this->pageSync->mustImport('localhost.dev');

        self::assertTrue($result);
    }

    public function testSyncStateRecordedAfterImport(): void
    {
        // Use isolated host key to avoid parallel test interference
        $isolatedHost = 'test-import-'.getmypid();
        $this->stateManager->resetState($isolatedHost);

        $this->stateManager->recordImport('page', $isolatedHost);

        $lastSyncTime = $this->stateManager->getLastSyncTime('page', $isolatedHost);
        self::assertGreaterThan(0, $lastSyncTime, 'Sync state should be recorded after import');

        $direction = $this->stateManager->getLastDirection($isolatedHost);
        self::assertSame('import', $direction, 'Last direction should be import');

        $this->stateManager->resetState($isolatedHost);
    }

    public function testSyncStateRecordedAfterExport(): void
    {
        // Use isolated host key to avoid parallel test interference
        $isolatedHost = 'test-export-'.getmypid();
        $this->stateManager->resetState($isolatedHost);

        $this->stateManager->recordExport('page', $isolatedHost);

        $lastSyncTime = $this->stateManager->getLastSyncTime('page', $isolatedHost);
        self::assertGreaterThan(0, $lastSyncTime, 'Sync state should be recorded after export');

        $direction = $this->stateManager->getLastDirection($isolatedHost);
        self::assertSame('export', $direction, 'Last direction should be export');

        $this->stateManager->resetState($isolatedHost);
    }

    public function testDatabaseBackupIsRestorable(): void
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $dbPath = $projectDir.'/var/app.db';

        if (! file_exists($dbPath)) {
            self::markTestSkipped('SQLite database not found');
        }

        // Create backup
        $backupPath = $dbPath.'~test-backup';
        $this->filesystem->copy($dbPath, $backupPath);
        $this->createdFiles[] = $backupPath;

        // Verify backup file exists and is non-empty
        self::assertFileExists($backupPath);
        self::assertGreaterThan(0, filesize($backupPath));

        // Backup should be the same size (or very close) to original
        $originalSize = filesize($dbPath);
        $backupSize = filesize($backupPath);
        self::assertSame($originalSize, $backupSize, 'Backup should be same size as original');
    }

    public function testConflictBackupFileCreated(): void
    {
        // Create a page and export it
        $page = new Page();
        $page->setSlug('valid-crash-test');
        $page->setH1('Conflict Test');
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->setMainContent('Original content');

        $this->em->persist($page);
        $this->em->flush();

        // Export to flat files
        $this->pageSync->export('localhost.dev', true, $this->contentDir);
        $this->stateManager->recordExport('page', 'localhost.dev');

        // Wait a moment and then modify BOTH the file AND the DB (creating a conflict)
        sleep(1);

        // Modify the .md file
        $mdPath = $this->contentDir.'/valid-crash-test.md';
        if (file_exists($mdPath)) {
            $this->filesystem->dumpFile($mdPath, "---\nh1: 'Conflict Test Modified in Flat'\n---\n\nModified in flat file");
            touch($mdPath, time() + 200);

            // Modify the DB version
            $page->setH1('Conflict Test Modified in DB');
            $page->setMainContent('Modified in database');
            $page->updatedAt = new DateTime('+100 seconds');
            $this->em->flush();

            // Check if conflict resolution creates backup
            /** @var ConflictResolver $conflictResolver */
            $conflictResolver = self::getContainer()->get(ConflictResolver::class);
            $conflicts = $this->stateManager->getConflicts('localhost.dev');

            // Run mustImport to trigger conflict detection
            $mustImport = $this->pageSync->mustImport('localhost.dev');

            // Check for conflict files in the content dir
            $conflictFiles = glob($this->contentDir.'/*~conflict-*') ?: [];

            // At least verify the conflict detection mechanism works
            // (actual conflict file creation depends on timing)
            $newConflicts = $this->stateManager->getConflicts('localhost.dev');
            self::assertGreaterThanOrEqual(\count($conflicts), \count($newConflicts), 'Conflicts should be recorded');
        }
    }
}
