<?php

namespace Pushword\Flat\Tests;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Flat\Exporter\PageExporter;
use Pushword\Flat\Exporter\RedirectionExporter;
use Pushword\Flat\FlatFileContentDirFinder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class PageRedirectionExportTest extends KernelTestCase
{
    private Filesystem $filesystem;

    private string $testContentDir;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->filesystem = new Filesystem();
        $this->testContentDir = self::getContainer()->getParameter('kernel.cache_dir').'/test-content';
        $this->filesystem->mkdir($this->testContentDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->testContentDir);
        parent::tearDown();
    }

    public function testIndexCsvExcludesRedirections(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        /** @var PageExporter $exporter */
        $exporter = self::getContainer()->get(PageExporter::class);
        $exporter->exportDir = $contentDir;
        $exporter->exportPages(true);

        // Check index.csv exists and doesn't contain the redirection
        $csvPath = $contentDir.'/index.csv';
        self::assertFileExists($csvPath);

        $csvContent = file_get_contents($csvPath);
        self::assertIsString($csvContent);

        // Should not contain the redirection slug (pushword)
        self::assertStringNotContainsString(',pushword,', $csvContent);

        // Should contain regular pages
        self::assertStringContainsString('homepage', $csvContent);
    }

    public function testRedirectionCsvGenerated(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        /** @var RedirectionExporter $exporter */
        $exporter = self::getContainer()->get(RedirectionExporter::class);
        $exporter->exportDir = $contentDir;
        $exporter->exportRedirections();

        // Check redirection.csv exists
        $csvPath = $contentDir.'/redirection.csv';
        self::assertFileExists($csvPath);

        $csvContent = file_get_contents($csvPath);
        self::assertIsString($csvContent);

        // Should contain header with correct columns (no locale)
        self::assertStringContainsString('slug,target,code', $csvContent);

        // Should contain the redirection
        self::assertStringContainsString('pushword', $csvContent);
        self::assertStringContainsString('https://pushword.piedweb.com', $csvContent);
        self::assertStringContainsString('301', $csvContent);
    }

    public function testRedirectionCsvHasNoLocaleColumn(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        /** @var RedirectionExporter $exporter */
        $exporter = self::getContainer()->get(RedirectionExporter::class);
        $exporter->exportDir = $contentDir;
        $exporter->exportRedirections();

        $csvPath = $contentDir.'/redirection.csv';
        $csvContent = file_get_contents($csvPath);
        self::assertIsString($csvContent);

        // Get header line
        $lines = explode("\n", $csvContent);
        $header = $lines[0];

        // Header should NOT contain 'locale'
        self::assertStringNotContainsString('locale', $header);

        // Header should contain expected columns
        self::assertStringContainsString('slug', $header);
        self::assertStringContainsString('target', $header);
        self::assertStringContainsString('code', $header);
    }

    public function testMdFilesExcludeRedirections(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Delete any existing pushword.md
        @unlink($contentDir.'/pushword.md');

        /** @var PageExporter $exporter */
        $exporter = self::getContainer()->get(PageExporter::class);
        $exporter->exportDir = $contentDir;
        $exporter->exportPages(true);

        // pushword.md should NOT be created (it's a redirection)
        self::assertFileDoesNotExist($contentDir.'/pushword.md');

        // Regular pages should have .md files
        self::assertFileExists($contentDir.'/homepage.md');
    }

    public function testOrphanedMdFileDeletedOnFullExport(): void
    {
        /** @var PageExporter $exporter */
        $exporter = self::getContainer()->get(PageExporter::class);
        $exporter->exportDir = $this->testContentDir;

        // Simulates the file left behind by a deleted page or an old slug after a
        // rename: a .md with no matching page in the database.
        $orphan = $this->testContentDir.'/this-page-was-deleted.md';
        file_put_contents($orphan, "---\ntitle: Ghost\n---\n\nboo");

        $exporter->exportPages(true);

        self::assertFileDoesNotExist($orphan, 'Orphaned .md with no matching DB page should be removed');
        self::assertFileExists($this->testContentDir.'/homepage.md', 'Real pages are still exported');
    }

    public function testOrphanCleanupPreservesReservedAndPendingFiles(): void
    {
        /** @var PageExporter $exporter */
        $exporter = self::getContainer()->get(PageExporter::class);
        $exporter->exportDir = $this->testContentDir;

        // Snippets live in a sibling-owned dir and must never be touched.
        $snippetFile = $this->testContentDir.'/pw-snippets/foo.md';
        $this->filesystem->dumpFile($snippetFile, "---\nname: foo\n---\n\nsnippet");

        // Pending writes are mid-flight and not yet page files.
        $pendingFile = $this->testContentDir.'/draft.pending.md';
        file_put_contents($pendingFile, 'pending');

        $exporter->exportPages(true);

        self::assertFileExists($snippetFile, 'Snippet files must survive page orphan cleanup');
        self::assertFileExists($pendingFile, 'Pending writes must survive page orphan cleanup');
    }

    public function testOrphanCleanupKeepsIndexMdForHomepage(): void
    {
        /** @var PageExporter $exporter */
        $exporter = self::getContainer()->get(PageExporter::class);
        $exporter->exportDir = $this->testContentDir;

        // A homepage authored as index.md must not be treated as an orphan: the
        // index ↔ homepage equivalence maps it back to the existing homepage page.
        $indexFile = $this->testContentDir.'/index.md';
        file_put_contents($indexFile, "---\ntitle: Home\n---\n\nhome");

        $exporter->exportPages(true);

        self::assertFileExists($indexFile, 'index.md must be preserved as the homepage, not deleted as an orphan');
    }

    public function testMdFileDeletedWhenPageBecomesRedirection(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $contentDir = $contentDirFinder->get('localhost.dev');

        // Create a fake .md file for the redirection page (simulating it was previously a regular page)
        $redirectionMdFile = $contentDir.'/pushword.md';
        file_put_contents($redirectionMdFile, "---\ntitle: Old Page\n---\n\nOld content");

        self::assertFileExists($redirectionMdFile);

        /** @var PageExporter $exporter */
        $exporter = self::getContainer()->get(PageExporter::class);
        $exporter->exportDir = $contentDir;
        $exporter->exportPages(true);

        // The .md file should be deleted because the page is now a redirection
        self::assertFileDoesNotExist($redirectionMdFile, 'MD file should be deleted when page becomes a redirection');
    }
}
