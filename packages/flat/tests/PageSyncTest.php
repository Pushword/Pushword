<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests;

use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Sync\PageSync;

use function Safe\file_get_contents;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests for PageSync covering redirection sync, page deletion, and edge cases.
 */
#[Group('integration')]
final class PageSyncTest extends KernelTestCase
{
    private EntityManager $em;

    /** @var EntityRepository<Page> */
    private EntityRepository $pageRepo;

    private PageSync $pageSync;

    /** @var string[] Files created during the test that should be cleaned up */
    private array $createdFiles = [];

    /** @var string[] Directories created during the test that should be cleaned up */
    private array $createdDirs = [];

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->createdFiles = [];
        $this->createdDirs = [];

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
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }

        foreach (array_reverse($this->createdDirs) as $dir) {
            @rmdir($dir);
        }

        parent::tearDown();
    }

    private function trackFile(string $path): void
    {
        $this->createdFiles[] = $path;
    }

    private function trackDir(string $path): void
    {
        $this->createdDirs[] = $path;
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
        $testRedirection->host = 'localhost.dev';
        $testRedirection->locale = 'en';
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
        $csvContent .= "new-redirect-test,https://example.com,302\n";
        file_put_contents($redirectionCsvPath, $csvContent);

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify new redirection was created
        $this->em->clear();
        $newRedirection = $this->pageRepo->findOneBy(['slug' => 'new-redirect-test', 'host' => 'localhost.dev']);
        self::assertNotNull($newRedirection, 'New redirection should be created');
        self::assertTrue($newRedirection->hasRedirection(), 'Page should be a redirection');
        self::assertSame('https://example.com', $newRedirection->getRedirectionUrl());
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
        $originalTarget = $redirectionPage->getRedirectionUrl();

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Modify the redirection CSV - change target and code
        $redirectionCsvPath = $contentDir.'/redirection.csv';
        $csvContent = "slug,target,code\npushword,https://new-target.com,302\n";
        file_put_contents($redirectionCsvPath, $csvContent);

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify update
        $this->em->clear();
        $updatedPage = $this->pageRepo->findOneBy(['slug' => 'pushword', 'host' => 'localhost.dev']);
        self::assertNotNull($updatedPage);
        self::assertSame('https://new-target.com', $updatedPage->getRedirectionUrl());
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
        $tempPage->host = 'localhost.dev';
        $tempPage->locale = 'en';
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
        $this->trackFile($contentDir.'/backup-test.md~');
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
        $modifiedCsv .= $homepage->id.',homepage,MODIFIED H1 FROM INDEX CSV,2024-01-01 00:00,en,,
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
        $draftPage->host = 'localhost.dev';
        $draftPage->locale = 'en';
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
        $this->trackFile($contentDir.'/draft-page-test.md');
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
        file_put_contents($redirectionCsvPath, "slug,target,code\n");

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
        $csvContent = "slug,target,code\n";
        $csvContent .= "empty-target-test,,301\n"; // Empty target
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
        self::assertSame('fr', $frPage->locale, 'Locale should be detected from slug prefix');

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
        $draftPage->host = 'localhost.dev';
        $draftPage->locale = 'en';
        $draftPage->setMainContent('Content with null publishedAt');
        $draftPage->setPublishedAt(null);

        $this->em->persist($draftPage);
        $this->em->flush();

        $pageId = $draftPage->id;

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
        $this->trackFile($mdFilePath);
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
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->setMainContent('Content with explicit date');
        $page->setPublishedAt($specificDate);

        $this->em->persist($page);
        $this->em->flush();

        $pageId = $page->id;

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
        $this->trackFile($mdFilePath);
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
        $page->host = 'localhost.dev';
        $page->locale = 'en';
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
        $this->trackFile($mdFilePath);
    }

    /**
     * Test 16: Translations export as array of slugs and import back correctly.
     */
    public function testTranslationsExportImportRoundTrip(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create two pages and link them as translations
        $enPage = new Page();
        $enPage->setSlug('translation-test-en');
        $enPage->setH1('English Page');
        $enPage->host = 'localhost.dev';
        $enPage->locale = 'en';
        $enPage->setMainContent('English content');
        $enPage->setPublishedAt(new DateTime());

        $frPage = new Page();
        $frPage->setSlug('translation-test-fr');
        $frPage->setH1('Page française');
        $frPage->host = 'localhost.dev';
        $frPage->locale = 'fr';
        $frPage->setMainContent('Contenu français');
        $frPage->setPublishedAt(new DateTime());

        $this->em->persist($enPage);
        $this->em->persist($frPage);
        $this->em->flush();

        // Link as translations (bidirectional)
        $enPage->addTranslation($frPage);
        $this->em->flush();

        $enPageId = $enPage->id;
        $frPageId = $frPage->id;

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Verify the .md files contain translations
        $enMdFilePath = $contentDir.'/translation-test-en.md';
        $frMdFilePath = $contentDir.'/translation-test-fr.md';
        self::assertFileExists($enMdFilePath);
        self::assertFileExists($frMdFilePath);

        $enMdContent = file_get_contents($enMdFilePath);
        $frMdContent = file_get_contents($frMdFilePath);

        self::assertStringContainsString('translations:', $enMdContent, 'EN page should have translations key');
        self::assertStringContainsString('translation-test-fr', $enMdContent, 'EN page should reference FR page');
        self::assertStringContainsString('translations:', $frMdContent, 'FR page should have translations key');
        self::assertStringContainsString('translation-test-en', $frMdContent, 'FR page should reference EN page');

        // Clear entity manager and remove the pages
        $this->em->clear();
        $enPageToRemove = $this->em->find(Page::class, $enPageId);
        $frPageToRemove = $this->em->find(Page::class, $frPageId);
        self::assertNotNull($enPageToRemove);
        self::assertNotNull($frPageToRemove);
        $enPageToRemove->removeTranslation($frPageToRemove);
        $this->em->remove($enPageToRemove);
        $this->em->remove($frPageToRemove);
        $this->em->flush();

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify pages were recreated with translations
        $this->em->clear();
        $importedEnPage = $this->pageRepo->findOneBy(['slug' => 'translation-test-en', 'host' => 'localhost.dev']);
        $importedFrPage = $this->pageRepo->findOneBy(['slug' => 'translation-test-fr', 'host' => 'localhost.dev']);
        self::assertNotNull($importedEnPage, 'EN page should be recreated');
        self::assertNotNull($importedFrPage, 'FR page should be recreated');

        // Verify bidirectional translations
        self::assertTrue($importedEnPage->getTranslations()->contains($importedFrPage), 'EN should have FR as translation');
        self::assertTrue($importedFrPage->getTranslations()->contains($importedEnPage), 'FR should have EN as translation');

        // Cleanup
        $importedEnPage->removeTranslation($importedFrPage);
        $this->em->remove($importedEnPage);
        $this->em->remove($importedFrPage);
        $this->em->flush();
        $this->trackFile($enMdFilePath);
        $this->trackFile($frMdFilePath);
    }

    /**
     * Test 17: Removing a translation from one page's flat file removes it from both sides.
     */
    public function testTranslationRemovalSync(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create three pages linked as translations
        $enPage = new Page();
        $enPage->setSlug('trans-removal-en');
        $enPage->setH1('English');
        $enPage->host = 'localhost.dev';
        $enPage->locale = 'en';
        $enPage->setMainContent('English');
        $enPage->setPublishedAt(new DateTime());

        $frPage = new Page();
        $frPage->setSlug('trans-removal-fr');
        $frPage->setH1('Français');
        $frPage->host = 'localhost.dev';
        $frPage->locale = 'fr';
        $frPage->setMainContent('Français');
        $frPage->setPublishedAt(new DateTime());

        $dePage = new Page();
        $dePage->setSlug('trans-removal-de');
        $dePage->setH1('Deutsch');
        $dePage->host = 'localhost.dev';
        $dePage->locale = 'de';
        $dePage->setMainContent('Deutsch');
        $dePage->setPublishedAt(new DateTime());

        $this->em->persist($enPage);
        $this->em->persist($frPage);
        $this->em->persist($dePage);
        $this->em->flush();

        // Link all three as translations
        $enPage->addTranslation($frPage);
        $enPage->addTranslation($dePage);

        $this->em->flush();

        // Verify initial state
        self::assertCount(2, $enPage->getTranslations());
        self::assertCount(2, $frPage->getTranslations());
        self::assertCount(2, $dePage->getTranslations());

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Modify EN page to remove DE translation
        $enMdFilePath = $contentDir.'/trans-removal-en.md';
        $enMdContent = file_get_contents($enMdFilePath);
        // Replace translations to only include FR
        $enMdContent = preg_replace(
            '/translations:\n(  - .+\n)+/',
            "translations:\n  - trans-removal-fr\n",
            $enMdContent
        );
        file_put_contents($enMdFilePath, $enMdContent);

        // Touch the file to ensure it's newer than updatedAt
        touch($enMdFilePath, time() + 10);

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify EN no longer has DE as translation
        $this->em->clear();
        $importedEnPage = $this->pageRepo->findOneBy(['slug' => 'trans-removal-en', 'host' => 'localhost.dev']);
        $importedFrPage = $this->pageRepo->findOneBy(['slug' => 'trans-removal-fr', 'host' => 'localhost.dev']);
        $importedDePage = $this->pageRepo->findOneBy(['slug' => 'trans-removal-de', 'host' => 'localhost.dev']);

        self::assertNotNull($importedEnPage);
        self::assertNotNull($importedFrPage);
        self::assertNotNull($importedDePage);

        // EN should only have FR
        self::assertCount(1, $importedEnPage->getTranslations(), 'EN should only have 1 translation now');
        self::assertTrue($importedEnPage->getTranslations()->contains($importedFrPage), 'EN should still have FR');
        self::assertFalse($importedEnPage->getTranslations()->contains($importedDePage), 'EN should no longer have DE');

        // DE should no longer have EN (bidirectional removal)
        self::assertFalse($importedDePage->getTranslations()->contains($importedEnPage), 'DE should no longer have EN');

        // Cleanup
        $importedEnPage->removeTranslation($importedFrPage);
        $importedFrPage->removeTranslation($importedDePage);
        $this->em->remove($importedEnPage);
        $this->em->remove($importedFrPage);
        $this->em->remove($importedDePage);
        $this->em->flush();
        $this->trackFile($contentDir.'/trans-removal-en.md');
        $this->trackFile($contentDir.'/trans-removal-fr.md');
        $this->trackFile($contentDir.'/trans-removal-de.md');
    }

    /**
     * Test 18: Removing translations key from flat file does NOT clear translations.
     * Only explicit translations: [] would clear them.
     */
    public function testTranslationsNotClearedWhenKeyRemoved(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create two pages linked as translations
        $enPage = new Page();
        $enPage->setSlug('trans-keep-en');
        $enPage->setH1('English');
        $enPage->host = 'localhost.dev';
        $enPage->locale = 'en';
        $enPage->setMainContent('English');
        $enPage->setPublishedAt(new DateTime());

        $frPage = new Page();
        $frPage->setSlug('trans-keep-fr');
        $frPage->setH1('Français');
        $frPage->host = 'localhost.dev';
        $frPage->locale = 'fr';
        $frPage->setMainContent('Français');
        $frPage->setPublishedAt(new DateTime());

        $this->em->persist($enPage);
        $this->em->persist($frPage);
        $this->em->flush();

        $enPage->addTranslation($frPage);
        $this->em->flush();

        // Verify initial state
        self::assertCount(1, $enPage->getTranslations());

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Remove translations key from EN page entirely (but FR still has it)
        $enMdFilePath = $contentDir.'/trans-keep-en.md';
        $enMdContent = file_get_contents($enMdFilePath);
        $enMdContent = preg_replace('/translations:\n(  - .+\n)+/', '', $enMdContent);
        file_put_contents($enMdFilePath, $enMdContent);
        touch($enMdFilePath, time() + 10);

        // FR file still lists EN, touch to ensure import
        $frMdFilePath = $contentDir.'/trans-keep-fr.md';
        touch($frMdFilePath, time() + 10);

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify translations are KEPT because FR still lists EN (addition takes precedence)
        $this->em->clear();
        $importedEnPage = $this->pageRepo->findOneBy(['slug' => 'trans-keep-en', 'host' => 'localhost.dev']);
        $importedFrPage = $this->pageRepo->findOneBy(['slug' => 'trans-keep-fr', 'host' => 'localhost.dev']);

        self::assertNotNull($importedEnPage);
        self::assertNotNull($importedFrPage);

        // Both should still have the translation because FR added it
        self::assertCount(1, $importedEnPage->getTranslations(), 'EN should still have FR (addition takes precedence)');
        self::assertTrue($importedFrPage->getTranslations()->contains($importedEnPage), 'FR should still have EN');

        // Cleanup
        $importedEnPage->removeTranslation($importedFrPage);
        $this->em->remove($importedEnPage);
        $this->em->remove($importedFrPage);
        $this->em->flush();
        $this->trackFile($enMdFilePath);
        $this->trackFile($frMdFilePath);
    }

    /**
     * Test 19: Addition takes precedence - if A removes B but B still lists A, they ARE linked.
     */
    public function testTranslationAdditionTakesPrecedence(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create two pages linked as translations
        // Using 'a-' and 'b-' prefixes to control alphabetical order (a comes before b)
        $aPage = new Page();
        $aPage->setSlug('a-trans-precedence');
        $aPage->setH1('Page A');
        $aPage->host = 'localhost.dev';
        $aPage->locale = 'en';
        $aPage->setMainContent('Page A content');
        $aPage->setPublishedAt(new DateTime());

        $bPage = new Page();
        $bPage->setSlug('b-trans-precedence');
        $bPage->setH1('Page B');
        $bPage->host = 'localhost.dev';
        $bPage->locale = 'fr';
        $bPage->setMainContent('Page B content');
        $bPage->setPublishedAt(new DateTime());

        $this->em->persist($aPage);
        $this->em->persist($bPage);
        $this->em->flush();

        $aPage->addTranslation($bPage);
        $this->em->flush();

        // Verify initial state
        self::assertCount(1, $aPage->getTranslations());
        self::assertCount(1, $bPage->getTranslations());

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Modify A's file to remove B from translations (A no longer lists B)
        $aMdFilePath = $contentDir.'/a-trans-precedence.md';
        $aMdContent = file_get_contents($aMdFilePath);
        $aMdContent = preg_replace('/translations:\n(  - .+\n)+/', '', $aMdContent);
        file_put_contents($aMdFilePath, $aMdContent);
        touch($aMdFilePath, time() + 10);

        // B's file still lists A, touch to ensure import
        $bMdFilePath = $contentDir.'/b-trans-precedence.md';
        touch($bMdFilePath, time() + 10);

        // Import - B still lists A, so addition takes precedence
        $this->pageSync->import('localhost.dev');

        // Verify: both should still have the translation because B added it
        // Addition takes precedence!
        $this->em->clear();
        $importedAPage = $this->pageRepo->findOneBy(['slug' => 'a-trans-precedence', 'host' => 'localhost.dev']);
        $importedBPage = $this->pageRepo->findOneBy(['slug' => 'b-trans-precedence', 'host' => 'localhost.dev']);

        self::assertNotNull($importedAPage);
        self::assertNotNull($importedBPage);

        self::assertCount(1, $importedAPage->getTranslations(), 'A should have B (addition takes precedence)');
        self::assertCount(1, $importedBPage->getTranslations(), 'B should have A');

        // Cleanup
        $importedAPage->removeTranslation($importedBPage);
        $this->em->remove($importedAPage);
        $this->em->remove($importedBPage);
        $this->em->flush();
        $this->trackFile($aMdFilePath);
        $this->trackFile($bMdFilePath);
    }

    /**
     * Test 20: Removing translations key (not setting empty) keeps existing translations.
     * To remove translations, use explicit empty array: translations: [].
     */
    public function testRemovingKeyKeepsExistingTranslations(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create two pages linked as translations
        $enPage = new Page();
        $enPage->setSlug('trans-keep-existing-en');
        $enPage->setH1('English');
        $enPage->host = 'localhost.dev';
        $enPage->locale = 'en';
        $enPage->setMainContent('English');
        $enPage->setPublishedAt(new DateTime());

        $frPage = new Page();
        $frPage->setSlug('trans-keep-existing-fr');
        $frPage->setH1('Français');
        $frPage->host = 'localhost.dev';
        $frPage->locale = 'fr';
        $frPage->setMainContent('Français');
        $frPage->setPublishedAt(new DateTime());

        $this->em->persist($enPage);
        $this->em->persist($frPage);
        $this->em->flush();

        $enPage->addTranslation($frPage);
        $this->em->flush();

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Remove translations KEY from BOTH files (not setting empty array)
        $enMdFilePath = $contentDir.'/trans-keep-existing-en.md';
        $enMdContent = file_get_contents($enMdFilePath);
        $enMdContent = preg_replace('/translations:\n(  - .+\n)+/', '', $enMdContent);
        file_put_contents($enMdFilePath, $enMdContent);
        touch($enMdFilePath, time() + 10);

        $frMdFilePath = $contentDir.'/trans-keep-existing-fr.md';
        $frMdContent = file_get_contents($frMdFilePath);
        $frMdContent = preg_replace('/translations:\n(  - .+\n)+/', '', $frMdContent);
        file_put_contents($frMdFilePath, $frMdContent);
        touch($frMdFilePath, time() + 10);

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify: translations are KEPT (no explicit translations key means "don't modify")
        $this->em->clear();
        $importedEnPage = $this->pageRepo->findOneBy(['slug' => 'trans-keep-existing-en', 'host' => 'localhost.dev']);
        $importedFrPage = $this->pageRepo->findOneBy(['slug' => 'trans-keep-existing-fr', 'host' => 'localhost.dev']);

        self::assertNotNull($importedEnPage);
        self::assertNotNull($importedFrPage);

        // Translations remain because no explicit translations key was set
        self::assertCount(1, $importedEnPage->getTranslations(), 'EN should keep FR (no translations key = dont modify)');
        self::assertCount(1, $importedFrPage->getTranslations(), 'FR should keep EN');

        // Cleanup
        $importedEnPage->removeTranslation($importedFrPage);
        $this->em->remove($importedEnPage);
        $this->em->remove($importedFrPage);
        $this->em->flush();
        $this->trackFile($enMdFilePath);
        $this->trackFile($frMdFilePath);
    }

    /**
     * Test 20b: Explicit empty translations array removes all translations.
     */
    public function testExplicitEmptyTranslationsArrayRemoves(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create two pages linked as translations
        $enPage = new Page();
        $enPage->setSlug('trans-explicit-empty-en');
        $enPage->setH1('English');
        $enPage->host = 'localhost.dev';
        $enPage->locale = 'en';
        $enPage->setMainContent('English');
        $enPage->setPublishedAt(new DateTime());

        $frPage = new Page();
        $frPage->setSlug('trans-explicit-empty-fr');
        $frPage->setH1('Français');
        $frPage->host = 'localhost.dev';
        $frPage->locale = 'fr';
        $frPage->setMainContent('Français');
        $frPage->setPublishedAt(new DateTime());

        $this->em->persist($enPage);
        $this->em->persist($frPage);
        $this->em->flush();

        $enPage->addTranslation($frPage);
        $this->em->flush();

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Set explicit empty translations array in BOTH files
        $enMdFilePath = $contentDir.'/trans-explicit-empty-en.md';
        $enMdContent = file_get_contents($enMdFilePath);
        $enMdContent = preg_replace('/translations:\n(  - .+\n)+/', "translations: []\n", $enMdContent);
        file_put_contents($enMdFilePath, $enMdContent);
        touch($enMdFilePath, time() + 10);

        $frMdFilePath = $contentDir.'/trans-explicit-empty-fr.md';
        $frMdContent = file_get_contents($frMdFilePath);
        $frMdContent = preg_replace('/translations:\n(  - .+\n)+/', "translations: []\n", $frMdContent);
        file_put_contents($frMdFilePath, $frMdContent);
        touch($frMdFilePath, time() + 10);

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify: neither should have translations (explicit empty array)
        $this->em->clear();
        $importedEnPage = $this->pageRepo->findOneBy(['slug' => 'trans-explicit-empty-en', 'host' => 'localhost.dev']);
        $importedFrPage = $this->pageRepo->findOneBy(['slug' => 'trans-explicit-empty-fr', 'host' => 'localhost.dev']);

        self::assertNotNull($importedEnPage);
        self::assertNotNull($importedFrPage);

        self::assertCount(0, $importedEnPage->getTranslations(), 'EN should have no translations');
        self::assertCount(0, $importedFrPage->getTranslations(), 'FR should have no translations');

        // Cleanup
        $this->em->remove($importedEnPage);
        $this->em->remove($importedFrPage);
        $this->em->flush();
        $this->trackFile($enMdFilePath);
        $this->trackFile($frMdFilePath);
    }

    /**
     * Test 21: Adding translation to one file creates bidirectional link.
     */
    public function testTranslationAddedFromOneFile(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create two pages NOT linked
        $enPage = new Page();
        $enPage->setSlug('trans-add-one-en');
        $enPage->setH1('English');
        $enPage->host = 'localhost.dev';
        $enPage->locale = 'en';
        $enPage->setMainContent('English');
        $enPage->setPublishedAt(new DateTime());

        $frPage = new Page();
        $frPage->setSlug('trans-add-one-fr');
        $frPage->setH1('Français');
        $frPage->host = 'localhost.dev';
        $frPage->locale = 'fr';
        $frPage->setMainContent('Français');
        $frPage->setPublishedAt(new DateTime());

        $this->em->persist($enPage);
        $this->em->persist($frPage);
        $this->em->flush();

        // Export (no translations yet)
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Add translations to EN file only (forgot to update FR)
        $enMdFilePath = $contentDir.'/trans-add-one-en.md';
        $enMdContent = file_get_contents($enMdFilePath);
        $enMdContent = str_replace("---\n\n", "translations:\n  - trans-add-one-fr\n---\n\n", $enMdContent);
        file_put_contents($enMdFilePath, $enMdContent);
        touch($enMdFilePath, time() + 10);

        // FR file not modified (forgot to add reciprocal)
        $frMdFilePath = $contentDir.'/trans-add-one-fr.md';
        touch($frMdFilePath, time() + 10);

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify: both should have the translation (EN added it, bidirectional)
        $this->em->clear();
        $importedEnPage = $this->pageRepo->findOneBy(['slug' => 'trans-add-one-en', 'host' => 'localhost.dev']);
        $importedFrPage = $this->pageRepo->findOneBy(['slug' => 'trans-add-one-fr', 'host' => 'localhost.dev']);

        self::assertNotNull($importedEnPage);
        self::assertNotNull($importedFrPage);

        self::assertCount(1, $importedEnPage->getTranslations(), 'EN should have FR');
        self::assertTrue($importedEnPage->getTranslations()->contains($importedFrPage));
        self::assertCount(1, $importedFrPage->getTranslations(), 'FR should have EN (bidirectional)');
        self::assertTrue($importedFrPage->getTranslations()->contains($importedEnPage));

        // Cleanup
        $importedEnPage->removeTranslation($importedFrPage);
        $this->em->remove($importedEnPage);
        $this->em->remove($importedFrPage);
        $this->em->flush();
        $this->trackFile($enMdFilePath);
        $this->trackFile($frMdFilePath);
    }

    /**
     * Test 22: Custom properties are exported at top level and imported back correctly.
     */
    public function testCustomPropertiesExportImportRoundTrip(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create a page with custom properties
        $page = new Page();
        $page->setSlug('custom-props-test');
        $page->setH1('Custom Properties Test');
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->setMainContent('Content');
        $page->setPublishedAt(new DateTime());
        $page->setCustomProperty('mainImageFormat', 'default');
        $page->setCustomProperty('customField', 'customValue');
        $page->setCustomProperty('numberProp', 42);

        $this->em->persist($page);
        $this->em->flush();

        $pageId = $page->id;

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Verify the .md file contains custom properties at top level (not nested)
        $mdFilePath = $contentDir.'/custom-props-test.md';
        self::assertFileExists($mdFilePath);

        $mdContent = file_get_contents($mdFilePath);

        // Custom properties should be at top level, not under customProperties:
        self::assertStringContainsString('mainImageFormat: default', $mdContent, 'mainImageFormat should be at top level');
        self::assertStringContainsString('customField: customValue', $mdContent, 'customField should be at top level');
        self::assertStringContainsString('numberProp: 42', $mdContent, 'numberProp should be at top level');
        self::assertStringNotContainsString('customProperties:', $mdContent, 'Should not have nested customProperties key');

        // Clear and remove the page
        $this->em->clear();
        $pageToRemove = $this->em->find(Page::class, $pageId);
        self::assertNotNull($pageToRemove);
        $this->em->remove($pageToRemove);
        $this->em->flush();

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify page was recreated with custom properties
        $this->em->clear();
        $importedPage = $this->pageRepo->findOneBy(['slug' => 'custom-props-test', 'host' => 'localhost.dev']);
        self::assertNotNull($importedPage, 'Page should be recreated');

        // Verify custom properties
        self::assertSame('default', $importedPage->getCustomProperty('mainImageFormat'), 'mainImageFormat should be imported');
        self::assertSame('customValue', $importedPage->getCustomProperty('customField'), 'customField should be imported');
        self::assertSame(42, $importedPage->getCustomProperty('numberProp'), 'numberProp should be imported');

        // Cleanup
        $this->em->remove($importedPage);
        $this->em->flush();
        $this->trackFile($mdFilePath);
    }

    /**
     * Test 23: mainImageFormat is exported as text label and imported back as int.
     */
    public function testMainImageFormatConverterExportImport(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create a page with mainImageFormat as integer (1 = none)
        $page = new Page();
        $page->setSlug('main-image-format-test');
        $page->setH1('Main Image Format Test');
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->setMainContent('Content');
        $page->setPublishedAt(new DateTime());
        $page->setCustomProperty('mainImageFormat', 1); // Integer value for "none"

        $this->em->persist($page);
        $this->em->flush();

        $pageId = $page->id;

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Verify the .md file contains mainImageFormat as text label
        $mdFilePath = $contentDir.'/main-image-format-test.md';
        self::assertFileExists($mdFilePath);

        $mdContent = file_get_contents($mdFilePath);

        // Should be exported as translated label '∅', not 1
        self::assertStringContainsString('mainImageFormat: ∅', $mdContent, 'mainImageFormat should be exported as translated label');
        self::assertStringNotContainsString('mainImageFormat: 1', $mdContent, 'mainImageFormat should NOT be exported as integer');

        // Clear and remove the page
        $this->em->clear();
        $pageToRemove = $this->em->find(Page::class, $pageId);
        self::assertNotNull($pageToRemove);
        $this->em->remove($pageToRemove);
        $this->em->flush();

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify page was recreated with mainImageFormat as integer
        $this->em->clear();
        $importedPage = $this->pageRepo->findOneBy(['slug' => 'main-image-format-test', 'host' => 'localhost.dev']);
        self::assertNotNull($importedPage, 'Page should be recreated');

        // Should be imported back as integer 1
        self::assertSame(1, $importedPage->getCustomProperty('mainImageFormat'), 'mainImageFormat should be imported as integer');

        // Cleanup
        $this->em->remove($importedPage);
        $this->em->flush();
        $this->trackFile($mdFilePath);
    }

    /**
     * Test 24: Legacy files with mainImageFormat as integer are imported correctly.
     */
    public function testLegacyMainImageFormatIntegerImport(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create a legacy .md file with mainImageFormat as integer
        $mdFilePath = $contentDir.'/legacy-format-test.md';
        $mdContent = <<<'YAML'
---
h1: Legacy Format Test
mainImageFormat: 2
---

Content
YAML;
        file_put_contents($mdFilePath, $mdContent);
        touch($mdFilePath, time() + 10); // Ensure it's newer than any DB entry

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify page was imported with mainImageFormat as integer
        $this->em->clear();
        $importedPage = $this->pageRepo->findOneBy(['slug' => 'legacy-format-test', 'host' => 'localhost.dev']);
        self::assertNotNull($importedPage, 'Page should be imported');

        // Should be imported as integer 2 (unchanged from legacy format)
        self::assertSame(2, $importedPage->getCustomProperty('mainImageFormat'), 'Legacy integer mainImageFormat should be preserved');

        // Cleanup
        $this->em->remove($importedPage);
        $this->em->flush();
        $this->trackFile($mdFilePath);
    }

    /**
     * Test 25: Locale with region code (e.g., fr-CA) is imported correctly from frontmatter.
     */
    public function testLocaleWithRegionCodeImport(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create directory for fr-CA locale
        $localeDir = $contentDir.'/fr-ca';
        if (! is_dir($localeDir)) {
            mkdir($localeDir, 0o755, true);
        }

        // Create a .md file with fr-CA locale in frontmatter
        $mdFilePath = $localeDir.'/locale-region-test.md';
        $mdContent = <<<'YAML'
---
h1: Test Locale Régional
locale: fr-CA
---

Contenu en français canadien.
YAML;
        file_put_contents($mdFilePath, $mdContent);
        touch($mdFilePath, time() + 10);

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify page was imported with correct locale
        $this->em->clear();
        $importedPage = $this->pageRepo->findOneBy(['slug' => 'fr-ca/locale-region-test', 'host' => 'localhost.dev']);
        self::assertNotNull($importedPage, 'Page should be imported');
        self::assertSame('fr-CA', $importedPage->locale, 'Locale with region code should be preserved');

        // Cleanup
        $this->em->remove($importedPage);
        $this->em->flush();
        $this->trackFile($mdFilePath);
        $this->trackDir($localeDir);
    }

    /**
     * Test 26: Locale export/import round trip preserves region code.
     */
    public function testLocaleWithRegionCodeRoundTrip(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create a page with fr-CA locale
        $page = new Page();
        $page->setSlug('fr-ca/round-trip-test');
        $page->setH1('Test Round Trip');
        $page->host = 'localhost.dev';
        $page->locale = 'fr-CA';
        $page->setMainContent('Contenu test');
        $page->setPublishedAt(new DateTime());

        $this->em->persist($page);
        $this->em->flush();

        $pageId = $page->id;

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Verify the .md file contains correct locale
        $mdFilePath = $contentDir.'/fr-ca/round-trip-test.md';
        self::assertFileExists($mdFilePath);
        $mdContent = file_get_contents($mdFilePath);
        self::assertStringContainsString('locale: fr-CA', $mdContent, 'Locale should be exported with region code');

        // Verify index.fr-CA.csv exists
        self::assertFileExists($contentDir.'/index.fr-CA.csv', 'Locale-specific index file should be created');

        // Clear and remove the page
        $this->em->clear();
        $pageToRemove = $this->em->find(Page::class, $pageId);
        self::assertNotNull($pageToRemove);
        $this->em->remove($pageToRemove);
        $this->em->flush();

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify page was recreated with correct locale
        $this->em->clear();
        $importedPage = $this->pageRepo->findOneBy(['slug' => 'fr-ca/round-trip-test', 'host' => 'localhost.dev']);
        self::assertNotNull($importedPage, 'Page should be recreated');
        self::assertSame('fr-CA', $importedPage->locale, 'Locale with region code should be preserved after round trip');

        // Cleanup
        $this->em->remove($importedPage);
        $this->em->flush();
        $this->trackFile($mdFilePath);
    }

    /**
     * Test 27: Duplicate IDs in markdown files are detected and ignored.
     */
    public function testDuplicateIdsAreDetectedAndIgnored(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create first markdown file with id: 999999
        $md1FilePath = $contentDir.'/duplicate-id-test-1.md';
        $md1Content = <<<'YAML'
---
id: 999999
h1: First Page With Duplicate ID
---

First page content.
YAML;
        file_put_contents($md1FilePath, $md1Content);
        touch($md1FilePath, time() + 10);

        // Create second markdown file with same id: 999999
        $md2FilePath = $contentDir.'/duplicate-id-test-2.md';
        $md2Content = <<<'YAML'
---
id: 999999
h1: Second Page With Duplicate ID
---

Second page content.
YAML;
        file_put_contents($md2FilePath, $md2Content);
        touch($md2FilePath, time() + 10);

        // Import - should warn about duplicate ID and ignore it for the second file
        $this->pageSync->import('localhost.dev');

        // Verify both pages exist in DB with different database IDs
        $this->em->clear();
        $page1 = $this->pageRepo->findOneBy(['slug' => 'duplicate-id-test-1', 'host' => 'localhost.dev']);
        $page2 = $this->pageRepo->findOneBy(['slug' => 'duplicate-id-test-2', 'host' => 'localhost.dev']);

        self::assertNotNull($page1, 'First page should be imported');
        self::assertNotNull($page2, 'Second page should be imported');
        self::assertNotSame($page1->id, $page2->id, 'Pages should have different database IDs');

        // Cleanup
        $this->em->remove($page1);
        $this->em->remove($page2);
        $this->em->flush();
        $this->trackFile($md1FilePath);
        $this->trackFile($md2FilePath);
    }

    /**
     * Test 28: Cross-host translations are exported with host/slug format.
     */
    public function testCrossHostTranslationExport(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create a page on localhost.dev
        $enPage = new Page();
        $enPage->setSlug('cross-host-en');
        $enPage->setH1('English Page');
        $enPage->host = 'localhost.dev';
        $enPage->locale = 'en';
        $enPage->setMainContent('English content');
        $enPage->setPublishedAt(new DateTime());

        // Create a page on pushword.piedweb.com
        $frPage = new Page();
        $frPage->setSlug('cross-host-fr');
        $frPage->setH1('Page française');
        $frPage->host = 'pushword.piedweb.com';
        $frPage->locale = 'fr';
        $frPage->setMainContent('Contenu français');
        $frPage->setPublishedAt(new DateTime());

        $this->em->persist($enPage);
        $this->em->persist($frPage);
        $this->em->flush();

        // Link as translations
        $enPage->addTranslation($frPage);
        $this->em->flush();

        // Export localhost.dev
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Verify the .md file uses host/slug format for the cross-host translation
        $enMdFilePath = $contentDir.'/cross-host-en.md';
        self::assertFileExists($enMdFilePath);

        $enMdContent = file_get_contents($enMdFilePath);
        self::assertStringContainsString('translations:', $enMdContent);
        self::assertStringContainsString('pushword.piedweb.com/cross-host-fr', $enMdContent, 'Cross-host translation should use host/slug format');

        // Cleanup
        $enPage->removeTranslation($frPage);
        $this->em->remove($enPage);
        $this->em->remove($frPage);
        $this->em->flush();
        $this->trackFile($enMdFilePath);
    }

    /**
     * Test 29: Cross-host translations are imported from host/slug format in flat files.
     */
    public function testCrossHostTranslationImport(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create a page on the OTHER host first (it must exist for the reference to resolve)
        $frPage = new Page();
        $frPage->setSlug('cross-import-fr');
        $frPage->setH1('Page française');
        $frPage->host = 'pushword.piedweb.com';
        $frPage->locale = 'fr';
        $frPage->setMainContent('Contenu français');
        $frPage->setPublishedAt(new DateTime());

        $this->em->persist($frPage);
        $this->em->flush();

        // Create a .md file on localhost.dev that references the other host
        $enMdFilePath = $contentDir.'/cross-import-en.md';
        $enMdContent = <<<'YAML'
---
h1: English Page
locale: en
translations:
  - pushword.piedweb.com/cross-import-fr
---

English content
YAML;
        file_put_contents($enMdFilePath, $enMdContent);
        touch($enMdFilePath, time() + 10);

        // Import localhost.dev
        $this->pageSync->import('localhost.dev');

        // Verify the cross-host translation link was created
        $this->em->clear();
        $importedEnPage = $this->pageRepo->findOneBy(['slug' => 'cross-import-en', 'host' => 'localhost.dev']);
        $importedFrPage = $this->pageRepo->findOneBy(['slug' => 'cross-import-fr', 'host' => 'pushword.piedweb.com']);

        self::assertNotNull($importedEnPage, 'EN page should be imported');
        self::assertNotNull($importedFrPage, 'FR page on other host should still exist');
        self::assertCount(1, $importedEnPage->getTranslations(), 'EN should have 1 translation');
        self::assertTrue($importedEnPage->getTranslations()->contains($importedFrPage), 'EN should link to FR on other host');
        self::assertTrue($importedFrPage->getTranslations()->contains($importedEnPage), 'FR should link back to EN (bidirectional)');

        // Cleanup
        $importedEnPage->removeTranslation($importedFrPage);
        $this->em->remove($importedEnPage);
        $this->em->remove($importedFrPage);
        $this->em->flush();
        $this->trackFile($enMdFilePath);
    }

    /**
     * Test 30: Cross-host translation round-trip (export then import preserves cross-host links).
     */
    public function testCrossHostTranslationRoundTrip(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create pages on different hosts and link them
        $enPage = new Page();
        $enPage->setSlug('cross-rt-en');
        $enPage->setH1('English');
        $enPage->host = 'localhost.dev';
        $enPage->locale = 'en';
        $enPage->setMainContent('English');
        $enPage->setPublishedAt(new DateTime());

        $frPage = new Page();
        $frPage->setSlug('cross-rt-fr');
        $frPage->setH1('Français');
        $frPage->host = 'pushword.piedweb.com';
        $frPage->locale = 'fr';
        $frPage->setMainContent('Français');
        $frPage->setPublishedAt(new DateTime());

        $this->em->persist($enPage);
        $this->em->persist($frPage);
        $this->em->flush();

        $enPage->addTranslation($frPage);
        $this->em->flush();

        $enPageId = $enPage->id;

        // Export
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Clear and remove the EN page (keep FR on other host)
        $this->em->clear();
        $enPageToRemove = $this->em->find(Page::class, $enPageId);
        self::assertNotNull($enPageToRemove);
        foreach ($enPageToRemove->getTranslations()->toArray() as $t) {
            $enPageToRemove->removeTranslation($t);
        }

        $this->em->remove($enPageToRemove);
        $this->em->flush();

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify the cross-host link was recreated
        $this->em->clear();
        $importedEnPage = $this->pageRepo->findOneBy(['slug' => 'cross-rt-en', 'host' => 'localhost.dev']);
        $importedFrPage = $this->pageRepo->findOneBy(['slug' => 'cross-rt-fr', 'host' => 'pushword.piedweb.com']);

        self::assertNotNull($importedEnPage, 'EN page should be recreated');
        self::assertNotNull($importedFrPage, 'FR page should still exist on other host');
        self::assertCount(1, $importedEnPage->getTranslations(), 'EN should have 1 translation after round-trip');
        self::assertTrue($importedEnPage->getTranslations()->contains($importedFrPage), 'Cross-host link should survive round-trip');

        // Cleanup
        $importedEnPage->removeTranslation($importedFrPage);
        $this->em->remove($importedEnPage);
        $this->em->remove($importedFrPage);
        $this->em->flush();
        $this->trackFile($contentDir.'/cross-rt-en.md');
    }

    /**
     * Test 31: Nested slugs (e.g. blog/my-article) are not mistaken for cross-host references.
     */
    public function testNestedSlugNotConfusedWithCrossHost(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create both .md files so both pages survive import
        $blogDir = $contentDir.'/blog';
        if (! is_dir($blogDir)) {
            mkdir($blogDir, 0o755, true);
        }

        $nestedMdFilePath = $blogDir.'/nested-article.md';
        $nestedMdContent = <<<'YAML'
---
h1: Nested Article
locale: fr
---

Contenu
YAML;
        file_put_contents($nestedMdFilePath, $nestedMdContent);
        touch($nestedMdFilePath, time() + 10);

        $enMdFilePath = $contentDir.'/nested-slug-test.md';
        $enMdContent = <<<'YAML'
---
h1: English Page
locale: en
translations:
  - blog/nested-article
---

English content
YAML;
        file_put_contents($enMdFilePath, $enMdContent);
        touch($enMdFilePath, time() + 10);

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify the translation resolved to the same-host nested slug (not a cross-host lookup)
        $this->em->clear();
        $importedEnPage = $this->pageRepo->findOneBy(['slug' => 'nested-slug-test', 'host' => 'localhost.dev']);
        $importedNestedPage = $this->pageRepo->findOneBy(['slug' => 'blog/nested-article', 'host' => 'localhost.dev']);

        self::assertNotNull($importedEnPage, 'EN page should be imported');
        self::assertNotNull($importedNestedPage, 'Nested slug page should exist');
        self::assertCount(1, $importedEnPage->getTranslations(), 'EN should have 1 translation');
        self::assertTrue($importedEnPage->getTranslations()->contains($importedNestedPage), 'Translation should resolve to same-host nested slug');

        // Cleanup
        $importedEnPage->removeTranslation($importedNestedPage);
        $this->em->remove($importedEnPage);
        $this->em->remove($importedNestedPage);
        $this->em->flush();
        $this->trackFile($enMdFilePath);
        $this->trackFile($nestedMdFilePath);
        $this->trackDir($blogDir);
    }

    /**
     * Test 32: Same-host translations work when both pages are created in the same import cycle.
     */
    public function testSameHostTranslationBothPagesCreatedDuringImport(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Neither page exists in DB — both created from .md files in the same import
        $enMdFilePath = $contentDir.'/same-import-en.md';
        $enMdContent = <<<'YAML'
---
h1: English Page
locale: en
translations:
  - same-import-fr
---

English content
YAML;
        file_put_contents($enMdFilePath, $enMdContent);
        touch($enMdFilePath, time() + 10);

        $frMdFilePath = $contentDir.'/same-import-fr.md';
        $frMdContent = <<<'YAML'
---
h1: Page française
locale: fr
translations:
  - same-import-en
---

Contenu français
YAML;
        file_put_contents($frMdFilePath, $frMdContent);
        touch($frMdFilePath, time() + 10);

        // Import — both pages created in the same cycle
        $this->pageSync->import('localhost.dev');

        $this->em->clear();
        $importedEnPage = $this->pageRepo->findOneBy(['slug' => 'same-import-en', 'host' => 'localhost.dev']);
        $importedFrPage = $this->pageRepo->findOneBy(['slug' => 'same-import-fr', 'host' => 'localhost.dev']);

        self::assertNotNull($importedEnPage, 'EN page should be created');
        self::assertNotNull($importedFrPage, 'FR page should be created');
        self::assertCount(1, $importedEnPage->getTranslations(), 'EN should have FR as translation');
        self::assertTrue($importedEnPage->getTranslations()->contains($importedFrPage));
        self::assertTrue($importedFrPage->getTranslations()->contains($importedEnPage), 'FR should have EN (bidirectional)');

        // Cleanup
        $importedEnPage->removeTranslation($importedFrPage);
        $this->em->remove($importedEnPage);
        $this->em->remove($importedFrPage);
        $this->em->flush();
        $this->trackFile($enMdFilePath);
        $this->trackFile($frMdFilePath);
    }

    /**
     * Test 33: Cross-host translation works when the target page doesn't exist in DB yet
     * but is created during a subsequent import of the other host.
     * After importing both hosts, a second import of the first host should resolve the link.
     */
    public function testCrossHostTranslationTargetCreatedLater(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $localhostContentDir = $contentDirFinder->get('localhost.dev');
        $piedwebContentDir = $contentDirFinder->get('pushword.piedweb.com');

        // Create .md on localhost.dev referencing a page on pushword.piedweb.com that doesn't exist yet
        $enMdFilePath = $localhostContentDir.'/cross-later-en.md';
        $enMdContent = <<<'YAML'
---
h1: English Page
locale: en
translations:
  - pushword.piedweb.com/cross-later-fr
---

English content
YAML;
        file_put_contents($enMdFilePath, $enMdContent);
        touch($enMdFilePath, time() + 10);

        // Create .md on pushword.piedweb.com
        $frMdFilePath = $piedwebContentDir.'/cross-later-fr.md';
        $frMdContent = <<<'YAML'
---
h1: Page française
locale: fr
---

Contenu français
YAML;
        file_put_contents($frMdFilePath, $frMdContent);
        touch($frMdFilePath, time() + 10);

        // First import: localhost.dev — target on other host doesn't exist yet
        $this->pageSync->import('localhost.dev');

        // Second import: pushword.piedweb.com — creates the target page
        $this->pageSync->import('pushword.piedweb.com');

        // Third import: localhost.dev again — should now resolve the cross-host reference
        touch($enMdFilePath, time() + 20);
        $this->pageSync->import('localhost.dev');

        $this->em->clear();
        $importedEnPage = $this->pageRepo->findOneBy(['slug' => 'cross-later-en', 'host' => 'localhost.dev']);
        $importedFrPage = $this->pageRepo->findOneBy(['slug' => 'cross-later-fr', 'host' => 'pushword.piedweb.com']);

        self::assertNotNull($importedEnPage, 'EN page should exist');
        self::assertNotNull($importedFrPage, 'FR page on other host should exist');
        self::assertCount(1, $importedEnPage->getTranslations(), 'EN should have FR as translation after re-import');
        self::assertTrue($importedEnPage->getTranslations()->contains($importedFrPage), 'Cross-host link should be established');

        // Cleanup
        $importedEnPage->removeTranslation($importedFrPage);
        $this->em->remove($importedEnPage);
        $this->em->remove($importedFrPage);
        $this->em->flush();
        $this->trackFile($enMdFilePath);
        $this->trackFile($frMdFilePath);
    }

    /**
     * Test: Excluded files (CLAUDE.md, README.md) are ignored during import.
     */
    public function testExcludedFilesIgnored(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Export first to ensure clean state
        $this->pageSync->export('localhost.dev', true, $contentDir);

        // Create excluded files that should be ignored
        $claudeMd = $contentDir.'/CLAUDE.md';
        file_put_contents($claudeMd, "---\nh1: Claude Instructions\n---\n\nThis is not a page.");
        $this->trackFile($claudeMd);

        $readmeMd = $contentDir.'/README.md';
        file_put_contents($readmeMd, "---\nh1: Readme File\n---\n\nThis is not a page.");
        $this->trackFile($readmeMd);

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify excluded files were NOT imported as pages
        $this->em->clear();
        $claudePage = $this->pageRepo->findOneBy(['slug' => 'CLAUDE', 'host' => 'localhost.dev']);
        self::assertNull($claudePage, 'CLAUDE.md should not be imported as a page');

        $readmePage = $this->pageRepo->findOneBy(['slug' => 'README', 'host' => 'localhost.dev']);
        self::assertNull($readmePage, 'README.md should not be imported as a page');

        // Export and verify excluded files are preserved on disk
        $this->pageSync->export('localhost.dev', true, $contentDir);

        self::assertFileExists($claudeMd, 'CLAUDE.md should survive export');
        self::assertFileExists($readmeMd, 'README.md should survive export');
    }

    /**
     * Test: Numeric slug (e.g. 404.md) imports correctly and is not confused with a page ID.
     */
    public function testNumericSlugImportExportRoundTrip(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create a .md file with a numeric filename (like 404.md)
        $mdFilePath = $contentDir.'/404.md';
        $mdContent = <<<'YAML'
---
h1: Page Not Found
---

The page you are looking for does not exist.
YAML;
        file_put_contents($mdFilePath, $mdContent);
        touch($mdFilePath, time() + 10);

        // Import
        $this->pageSync->import('localhost.dev');

        // Verify page was imported with slug "404", not matched to any page by ID
        $this->em->clear();
        $importedPage = $this->pageRepo->findOneBy(['slug' => '404', 'host' => 'localhost.dev']);
        self::assertNotNull($importedPage, 'Page with numeric slug "404" should be imported');
        self::assertSame('404', $importedPage->slug, 'Slug should be "404"');
        self::assertSame('Page Not Found', $importedPage->h1);

        $pageId = $importedPage->id;

        // Export and verify it round-trips correctly
        $this->pageSync->export('localhost.dev', true, $contentDir);

        self::assertFileExists($mdFilePath, '404.md should be exported');
        $exportedContent = file_get_contents($mdFilePath);
        self::assertStringContainsString('Page Not Found', $exportedContent);

        // Delete and re-import to verify the slug is not confused with the page ID
        $this->em->clear();
        $pageToRemove = $this->em->find(Page::class, $pageId);
        self::assertNotNull($pageToRemove);
        $this->em->remove($pageToRemove);
        $this->em->flush();

        // Re-import — the file has id in frontmatter now, but the slug should remain "404"
        $this->pageSync->import('localhost.dev');

        $this->em->clear();
        $reimportedPage = $this->pageRepo->findOneBy(['slug' => '404', 'host' => 'localhost.dev']);
        self::assertNotNull($reimportedPage, 'Page with numeric slug should be re-imported');
        self::assertSame('404', $reimportedPage->slug);

        // Cleanup
        $this->em->remove($reimportedPage);
        $this->em->flush();
        $this->trackFile($mdFilePath);
    }

    /**
     * Test: Numeric slug in YAML frontmatter (unquoted) is correctly parsed.
     */
    public function testNumericSlugInFrontmatter(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // YAML parses unquoted numbers as integers — the importer should handle this
        $mdFilePath = $contentDir.'/numeric-slug-yaml.md';
        $mdContent = <<<'YAML_WRAP'
        ---
        h1: Numeric Slug YAML Test
        slug: 404
        ---

        Content
        YAML_WRAP;
        file_put_contents($mdFilePath, $mdContent);
        touch($mdFilePath, time() + 10);

        // Import
        $this->pageSync->import('localhost.dev');

        $this->em->clear();
        // The slug from frontmatter (404 as int) should be cast to string "404"
        // and match the filename-derived slug
        $importedPage = $this->pageRepo->findOneBy(['slug' => '404', 'host' => 'localhost.dev']);

        // If not found by "404", check with the filename-derived slug
        $importedPage ??= $this->pageRepo->findOneBy(['slug' => 'numeric-slug-yaml', 'host' => 'localhost.dev']);

        self::assertNotNull($importedPage, 'Page should be imported');
        // The slug should be "404" (from frontmatter, cast to string) or "numeric-slug-yaml" (from filename)
        // With our fix, it should be "404" since we cast numeric YAML values to string
        self::assertSame('404', $importedPage->slug, 'Numeric YAML slug should be cast to string');

        // Cleanup
        $this->em->remove($importedPage);
        $this->em->flush();
        $this->trackFile($mdFilePath);
    }

    /**
     * Test: Force import resets (deletes) all host pages before importing.
     */
    public function testForceImportResetsHostPagesBeforeImport(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create an existing page in DB that has NO corresponding .md file
        $existingPage = new Page();
        $existingPage->setSlug('force-reset-test-existing');
        $existingPage->setH1('Existing Page To Be Reset');
        $existingPage->host = 'localhost.dev';
        $existingPage->locale = 'en';

        $this->em->persist($existingPage);
        $this->em->flush();

        // Verify it exists
        $this->em->clear();

        $existingPageCheck = $this->pageRepo->findOneBy(['slug' => 'force-reset-test-existing', 'host' => 'localhost.dev']);
        self::assertNotNull($existingPageCheck, 'Existing page should exist before force import');

        // Create a content file for a NEW page only
        $newPagePath = $contentDir.'/force-reset-test-new.md';
        $newPageContent = <<<'YAML'
---
h1: New Page After Reset
locale: en
---

New page content.
YAML;
        file_put_contents($newPagePath, $newPageContent);
        touch($newPagePath, time() + 10);

        // Run import with force=true
        $this->pageSync->import('localhost.dev', skipId: true, force: true);

        // Assert: existing page was deleted (reset), new page was imported
        $this->em->clear();

        $existingPageAfter = $this->pageRepo->findOneBy(['slug' => 'force-reset-test-existing', 'host' => 'localhost.dev']);
        self::assertNull($existingPageAfter, 'Existing page should be deleted on force import');

        $newPage = $this->pageRepo->findOneBy(['slug' => 'force-reset-test-new', 'host' => 'localhost.dev']);
        self::assertNotNull($newPage, 'New page should be imported after force reset');

        // Cleanup
        $this->em->remove($newPage);
        $this->em->flush();
        $this->trackFile($newPagePath);
    }
}
