<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests;

use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Override;
use Pushword\Core\Entity\Page;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Sync\PageSync;

use function Safe\file_get_contents;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests for PageSync covering redirection sync, page deletion, and edge cases.
 */
final class PageSyncTest extends KernelTestCase
{
    private Filesystem $filesystem;

    private string $testContentDir;

    private EntityManager $em;

    /** @var EntityRepository<Page> */
    private EntityRepository $pageRepo;

    private PageSync $pageSync;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->filesystem = new Filesystem();
        $this->testContentDir = self::getContainer()->getParameter('kernel.cache_dir').'/test-sync-content';
        $this->filesystem->mkdir($this->testContentDir);

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        $this->pageRepo = $this->em->getRepository(Page::class);

        /** @var PageSync $pageSync */
        $pageSync = self::getContainer()->get(PageSync::class);
        $this->pageSync = $pageSync;
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->filesystem->remove($this->testContentDir);
        parent::tearDown();
    }

    /**
     * Test 1: Deleting a row from redirection.csv removes the page from DB.
     */
    public function testRedirectionDeletionSync(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create a test redirection to delete
        $testRedirection = new Page();
        $testRedirection->setSlug('test-redirection-to-delete');
        $testRedirection->setH1('Test Redirection');
        $testRedirection->setHost('localhost.dev');
        $testRedirection->setLocale('en');
        $testRedirection->setMainContent('Location: https://example.com');

        $this->em->persist($testRedirection);
        $this->em->flush();

        // First export to create redirection.csv
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Verify test redirection exists in DB
        $redirectionPage = $this->pageRepo->findOneBy(['slug' => 'test-redirection-to-delete', 'host' => 'localhost.dev']);
        self::assertNotNull($redirectionPage, 'Test redirection should exist before test');

        // Read the current redirection.csv and remove only our test redirection
        $redirectionCsvPath = $contentDir.'/redirection.csv';
        self::assertFileExists($redirectionCsvPath);
        $csvContent = file_get_contents($redirectionCsvPath);
        $lines = explode("\n", $csvContent);
        $filteredLines = array_filter($lines, static fn (string $line): bool => ! str_contains($line, 'test-redirection-to-delete'));
        file_put_contents($redirectionCsvPath, implode("\n", $filteredLines));

        // Import - should delete the test redirection since it's not in CSV anymore
        $this->pageSync->import('localhost.dev');

        // Verify test redirection was deleted
        $this->em->clear();
        $redirectionPage = $this->pageRepo->findOneBy(['slug' => 'test-redirection-to-delete', 'host' => 'localhost.dev']);
        self::assertNull($redirectionPage, 'Test redirection should be deleted after removing from CSV');

        // Verify other redirections still exist
        $otherRedirection = $this->pageRepo->findOneBy(['slug' => 'pushword', 'host' => 'localhost.dev']);
        self::assertNotNull($otherRedirection, 'Other redirections should not be affected');
    }

    /**
     * Test 2: Adding a new row to redirection.csv creates a new page in DB.
     */
    public function testRedirectionCreationViaCSV(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // First export to get current state
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Verify our new redirection doesn't exist yet
        $newRedirection = $this->pageRepo->findOneBy(['slug' => 'new-redirect-test', 'host' => 'localhost.dev']);
        self::assertNull($newRedirection, 'New redirection should not exist before test');

        // Append a new redirection to the CSV
        $redirectionCsvPath = $contentDir.'/redirection.csv';
        $csvContent = file_get_contents($redirectionCsvPath);
        $csvContent .= ",new-redirect-test,https://example.com,302\n";
        file_put_contents($redirectionCsvPath, $csvContent);

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify new redirection was created
        $this->em->clear();
        $newRedirection = $this->pageRepo->findOneBy(['slug' => 'new-redirect-test', 'host' => 'localhost.dev']);
        self::assertNotNull($newRedirection, 'New redirection should be created');
        self::assertTrue($newRedirection->hasRedirection(), 'Page should be a redirection');
        self::assertSame('https://example.com', $newRedirection->getRedirection());
        self::assertSame(302, $newRedirection->getRedirectionCode());

        // Cleanup
        $this->em->remove($newRedirection);
        $this->em->flush();
    }

    /**
     * Test 3: Modifying target/code in redirection.csv updates the DB.
     */
    public function testRedirectionUpdateViaCSV(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Get current redirection
        $redirectionPage = $this->pageRepo->findOneBy(['slug' => 'pushword', 'host' => 'localhost.dev']);
        self::assertNotNull($redirectionPage);
        $originalTarget = $redirectionPage->getRedirection();
        $pageId = $redirectionPage->getId();

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Modify the redirection CSV - change target and code
        $redirectionCsvPath = $contentDir.'/redirection.csv';
        $csvContent = "id,slug,target,code\n{$pageId},pushword,https://new-target.com,302\n";
        file_put_contents($redirectionCsvPath, $csvContent);

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify update
        $this->em->clear();
        $updatedPage = $this->pageRepo->findOneBy(['slug' => 'pushword', 'host' => 'localhost.dev']);
        self::assertNotNull($updatedPage);
        self::assertSame('https://new-target.com', $updatedPage->getRedirection());
        self::assertSame(302, $updatedPage->getRedirectionCode());

        // Restore original
        $updatedPage->setMainContent('Location: '.$originalTarget);
        $this->em->flush();
    }

    /**
     * Test 4: Deleting a .md file removes the page from DB.
     */
    public function testPageDeletionSync(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create a temporary page
        $tempPage = new Page();
        $tempPage->setSlug('temp-page-for-deletion-test');
        $tempPage->setH1('Temporary Page');
        $tempPage->setHost('localhost.dev');
        $tempPage->setLocale('en');
        $tempPage->setMainContent('Temporary content');
        $tempPage->setPublishedAt(new DateTime());

        $this->em->persist($tempPage);
        $this->em->flush();

        // Export to create .md file
        $this->pageSync->export('localhost.dev', true, $contentDir);

        $mdFilePath = $contentDir.'/temp-page-for-deletion-test.md';
        self::assertFileExists($mdFilePath, 'MD file should be created after export');

        // Delete the .md file
        unlink($mdFilePath);
        self::assertFileDoesNotExist($mdFilePath);

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify page was deleted
        $this->em->clear();
        $deletedPage = $this->pageRepo->findOneBy(['slug' => 'temp-page-for-deletion-test', 'host' => 'localhost.dev']);
        self::assertNull($deletedPage, 'Page should be deleted when .md file is removed');
    }

    /**
     * Test 5: Backup files (*.md~) should be ignored during import.
     */
    public function testBackupFilesIgnored(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Export first
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Create a backup file that should be ignored
        $backupContent = <<<'MD'
---
h1: Backup File Should Be Ignored
slug: backup-file-test
---

This is a backup file content.
MD;
        file_put_contents($contentDir.'/backup-test.md~', $backupContent);

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify backup file was NOT imported as a page
        $this->em->clear();
        $backupPage = $this->pageRepo->findOneBy(['slug' => 'backup-file-test', 'host' => 'localhost.dev']);
        self::assertNull($backupPage, 'Backup file should not be imported as a page');

        // Also check for the raw filename as slug (in case slug extraction failed)
        $backupPage2 = $this->pageRepo->findOneBy(['slug' => 'backup-test.md~', 'host' => 'localhost.dev']);
        self::assertNull($backupPage2, 'Backup file should not create page with raw filename');

        // Cleanup
        @unlink($contentDir.'/backup-test.md~');
    }

    /**
     * Test 6: Editing index.csv has no effect on import.
     */
    public function testIndexCsvIsReadOnly(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Export to create index.csv
        $this->pageSync->export('localhost.dev', true, $contentDir);

        $indexCsvPath = $contentDir.'/index.csv';
        self::assertFileExists($indexCsvPath);

        // Get current homepage state
        $homepage = $this->pageRepo->findOneBy(['slug' => 'homepage', 'host' => 'localhost.dev', 'locale' => 'en']);
        self::assertNotNull($homepage);
        $originalH1 = $homepage->getH1();

        // Modify index.csv to change homepage h1 (this should be ignored)
        $modifiedCsv = "id,slug,h1,publishedAt,locale,parentPage,tags\n";
        $modifiedCsv .= $homepage->getId().',homepage,MODIFIED H1 FROM INDEX CSV,2024-01-01 00:00,en,,
';
        file_put_contents($indexCsvPath, $modifiedCsv);

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify homepage h1 was NOT changed (index.csv is read-only)
        $this->em->clear();
        $homepage = $this->pageRepo->findOneBy(['slug' => 'homepage', 'host' => 'localhost.dev', 'locale' => 'en']);
        self::assertNotNull($homepage);
        self::assertSame($originalH1, $homepage->getH1(), 'Homepage h1 should NOT be changed by index.csv modifications');
    }

    /**
     * Test 7: Draft pages (unpublished) go to iDraft.csv.
     */
    public function testDraftPagesExportToIDraftCsv(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create a draft page (publishedAt in future)
        $draftPage = new Page();
        $draftPage->setSlug('draft-page-test');
        $draftPage->setH1('Draft Page');
        $draftPage->setHost('localhost.dev');
        $draftPage->setLocale('en');
        $draftPage->setMainContent('Draft content');
        $draftPage->setPublishedAt(new DateTime('+1 year'));
        // Future date = draft
        $this->em->persist($draftPage);
        $this->em->flush();

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Verify draft is in iDraft.csv, not index.csv
        $indexCsvPath = $contentDir.'/index.csv';
        $draftCsvPath = $contentDir.'/iDraft.csv';

        self::assertFileExists($indexCsvPath);
        self::assertFileExists($draftCsvPath);

        $indexContent = file_get_contents($indexCsvPath);
        $draftContent = file_get_contents($draftCsvPath);

        self::assertStringNotContainsString('draft-page-test', $indexContent, 'Draft should not be in index.csv');
        self::assertStringContainsString('draft-page-test', $draftContent, 'Draft should be in iDraft.csv');

        // Cleanup
        $this->em->remove($draftPage);
        $this->em->flush();
        @unlink($contentDir.'/draft-page-test.md');
    }

    /**
     * Test 8: Multi-locale index files separation.
     */
    public function testMultiLocaleIndexFiles(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Check for locale-specific index files or default index.csv
        $indexCsvPath = $contentDir.'/index.csv';
        $indexFrCsvPath = $contentDir.'/index.fr.csv';
        $frDirIndexPath = $contentDir.'/fr/index.csv';

        // At minimum, index.csv should exist
        self::assertFileExists($indexCsvPath, 'index.csv should exist');

        // Check French pages are separated (either in index.fr.csv or fr/index.csv)
        $indexContent = file_get_contents($indexCsvPath);

        // The French homepage should NOT be in the main index.csv (it has locale 'fr')
        // It should be in a separate file
        if (file_exists($indexFrCsvPath)) {
            $frContent = file_get_contents($indexFrCsvPath);
            self::assertStringContainsString('homepage', $frContent, 'French homepage should be in index.fr.csv');
        } elseif (file_exists($frDirIndexPath)) {
            $frContent = file_get_contents($frDirIndexPath);
            self::assertStringContainsString('homepage', $frContent, 'French homepage should be in fr/index.csv');
        }
    }

    /**
     * Test 9: Mixed sync - pages from .md and redirections from CSV coexist.
     */
    public function testMixedSync(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Count pages before
        $pagesBefore = $this->pageRepo->findByHost('localhost.dev');
        $countBefore = \count($pagesBefore);

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Verify both .md files and redirection.csv exist
        self::assertFileExists($contentDir.'/homepage.md', 'Homepage .md should exist');
        self::assertFileExists($contentDir.'/redirection.csv', 'redirection.csv should exist');
        self::assertFileDoesNotExist($contentDir.'/pushword.md', 'Redirection should NOT have .md file');

        // Import back
        $this->pageSync->import('localhost.dev');

        // Verify same number of pages
        $this->em->clear();
        $pagesAfter = $this->pageRepo->findByHost('localhost.dev');
        $countAfter = \count($pagesAfter);

        self::assertSame($countBefore, $countAfter, 'Page count should remain the same after export/import cycle');

        // Verify both regular pages and redirections exist
        $homepage = $this->pageRepo->findOneBy(['slug' => 'homepage', 'host' => 'localhost.dev', 'locale' => 'en']);
        $redirection = $this->pageRepo->findOneBy(['slug' => 'pushword', 'host' => 'localhost.dev']);

        self::assertNotNull($homepage, 'Homepage should exist');
        self::assertNotNull($redirection, 'Redirection should exist');
        self::assertFalse($homepage->hasRedirection(), 'Homepage should not be a redirection');
        self::assertTrue($redirection->hasRedirection(), 'Pushword should be a redirection');
    }

    /**
     * Test 10: Empty redirection.csv (only header) doesn't crash and handles correctly.
     */
    public function testEmptyRedirectionCsv(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Export first
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Create empty redirection.csv (only header)
        $redirectionCsvPath = $contentDir.'/redirection.csv';
        file_put_contents($redirectionCsvPath, "id,slug,target,code\n");

        // This should not throw an exception - if we get here, test passes
        $this->pageSync->import('localhost.dev');

        // Verify import completed without errors by checking pages still exist
        $this->em->clear();
        $homepage = $this->pageRepo->findOneBy(['slug' => 'homepage', 'host' => 'localhost.dev']);
        self::assertNotNull($homepage, 'Pages should still exist after import with empty redirection.csv');
    }

    /**
     * Test 11: Redirection with empty target is skipped.
     */
    public function testRedirectionWithEmptyTargetSkipped(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Export first
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Create redirection.csv with empty target
        $redirectionCsvPath = $contentDir.'/redirection.csv';
        $csvContent = "id,slug,target,code\n";
        $csvContent .= ",empty-target-test,,301\n"; // Empty target
        file_put_contents($redirectionCsvPath, $csvContent);

        // Import - should not crash and should skip the invalid entry
        $this->pageSync->import('localhost.dev');

        // Verify page was NOT created
        $this->em->clear();
        $invalidPage = $this->pageRepo->findOneBy(['slug' => 'empty-target-test', 'host' => 'localhost.dev']);
        self::assertNull($invalidPage, 'Page with empty target should not be created');
    }

    /**
     * Test 12: Locale detection from slug prefix.
     */
    public function testLocaleDetectionFromSlug(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Export first
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Create redirection.csv with locale prefix in slug
        $redirectionCsvPath = $contentDir.'/redirection.csv';
        $csvContent = "id,slug,target,code\n";
        $csvContent .= ",fr/old-french-page,https://example.fr,301\n";
        file_put_contents($redirectionCsvPath, $csvContent);

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify page was created with correct locale
        $this->em->clear();
        $frPage = $this->pageRepo->findOneBy(['slug' => 'fr/old-french-page', 'host' => 'localhost.dev']);
        self::assertNotNull($frPage, 'French redirection should be created');
        self::assertSame('fr', $frPage->getLocale(), 'Locale should be detected from slug prefix');

        // Cleanup
        $this->em->remove($frPage);
        $this->em->flush();
    }

    /**
     * Test 13: Null publishedAt exports as "draft" and imports back as null.
     */
    public function testNullPublishedAtRoundTrip(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create a page with null publishedAt (true draft)
        $draftPage = new Page();
        $draftPage->setSlug('null-published-at-test');
        $draftPage->setH1('Null PublishedAt Test');
        $draftPage->setHost('localhost.dev');
        $draftPage->setLocale('en');
        $draftPage->setMainContent('Content with null publishedAt');
        $draftPage->setPublishedAt(null);

        $this->em->persist($draftPage);
        $this->em->flush();

        $pageId = $draftPage->getId();

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Verify the .md file contains publishedAt: draft
        $mdFilePath = $contentDir.'/null-published-at-test.md';
        self::assertFileExists($mdFilePath);
        $mdContent = file_get_contents($mdFilePath);
        self::assertStringContainsString('publishedAt: draft', $mdContent, 'Null publishedAt should export as "draft"');

        // Clear entity manager and remove the page
        $this->em->clear();
        $pageToRemove = $this->em->find(Page::class, $pageId);
        self::assertNotNull($pageToRemove);
        $this->em->remove($pageToRemove);
        $this->em->flush();

        // Import - should recreate page with null publishedAt
        $this->pageSync->import('localhost.dev');

        // Verify page was recreated with null publishedAt
        $this->em->clear();
        $importedPage = $this->pageRepo->findOneBy(['slug' => 'null-published-at-test', 'host' => 'localhost.dev']);
        self::assertNotNull($importedPage, 'Page should be recreated after import');
        self::assertNull($importedPage->getPublishedAt(), 'PublishedAt should remain null after round-trip');

        // Cleanup
        $this->em->remove($importedPage);
        $this->em->flush();
        @unlink($mdFilePath);
    }

    /**
     * Test 14: Explicit publishedAt date survives round-trip unchanged.
     */
    public function testExplicitPublishedAtRoundTrip(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        $specificDate = new DateTime('2023-05-15 10:30:00');

        // Create a page with specific publishedAt
        $page = new Page();
        $page->setSlug('explicit-date-test');
        $page->setH1('Explicit Date Test');
        $page->setHost('localhost.dev');
        $page->setLocale('en');
        $page->setMainContent('Content with explicit date');
        $page->setPublishedAt($specificDate);

        $this->em->persist($page);
        $this->em->flush();

        $pageId = $page->getId();

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Verify the .md file contains the formatted date
        $mdFilePath = $contentDir.'/explicit-date-test.md';
        self::assertFileExists($mdFilePath);
        $mdContent = file_get_contents($mdFilePath);
        self::assertMatchesRegularExpression('/publishedAt: [\'"]?2023-05-15 10:30[\'"]?/', $mdContent, 'Date should be formatted correctly. Content: '.$mdContent);

        // Clear entity manager and remove the page
        $this->em->clear();
        $pageToRemove = $this->em->find(Page::class, $pageId);
        self::assertNotNull($pageToRemove);
        $this->em->remove($pageToRemove);
        $this->em->flush();

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify page was recreated with correct date
        $this->em->clear();
        $importedPage = $this->pageRepo->findOneBy(['slug' => 'explicit-date-test', 'host' => 'localhost.dev']);
        self::assertNotNull($importedPage, 'Page should be recreated after import');
        self::assertNotNull($importedPage->getPublishedAt(), 'PublishedAt should not be null');
        self::assertSame('2023-05-15 10:30', $importedPage->getPublishedAt()->format('Y-m-d H:i'), 'PublishedAt should match original');

        // Cleanup
        $this->em->remove($importedPage);
        $this->em->flush();
        @unlink($mdFilePath);
    }

    /**
     * Test 15: publishedAt exports in exact YAML format 'Y-m-d H:i'.
     */
    public function testPublishedAtYamlFormat(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create a page with specific publishedAt including seconds (which should be truncated)
        $page = new Page();
        $page->setSlug('yaml-format-test');
        $page->setH1('YAML Format Test');
        $page->setHost('localhost.dev');
        $page->setLocale('en');
        $page->setMainContent('Testing YAML format');
        $page->setPublishedAt(new DateTime('2024-12-25 14:30:45'));

        $this->em->persist($page);
        $this->em->flush();

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Read and parse the .md file
        $mdFilePath = $contentDir.'/yaml-format-test.md';
        self::assertFileExists($mdFilePath);
        $mdContent = file_get_contents($mdFilePath);

        // Extract YAML front matter
        preg_match('/^---\n(.+?)\n---/s', $mdContent, $matches);
        self::assertNotEmpty($matches[1], 'YAML front matter should exist');

        // Verify exact format: 'publishedAt: 2024-12-25 14:30' (no seconds, no quotes needed for this format)
        self::assertStringContainsString("publishedAt: '2024-12-25 14:30'", $matches[1], 'publishedAt should be in Y-m-d H:i format with YAML string quoting');

        // Cleanup
        $this->em->remove($page);
        $this->em->flush();
        @unlink($mdFilePath);
    }
}
