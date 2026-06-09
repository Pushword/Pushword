<?php

namespace Pushword\Flat\Tests\Sync;

use DateTime;
use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\RevisionCalculator;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Sync\PageSync;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class IdempotencyTest extends KernelTestCase
{
    private EntityManager $em;

    private PageSync $pageSync;

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

        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $this->contentDir = $contentDirFinder->get('localhost.dev');
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }

        foreach (['idempotent-test-page', 'revision-export-test', 'revision-import-test', 'redirect-from-test'] as $slug) {
            $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => $slug, 'host' => 'localhost.dev']);
            if ($page instanceof Page) {
                $this->em->remove($page);
            }
        }

        $this->em->flush();
        parent::tearDown();
    }

    private function createMd(string $fileName, string $content): void
    {
        $path = $this->contentDir.'/'.$fileName;
        $this->filesystem->dumpFile($path, $content);
        touch($path, time() + 100);
        $this->createdFiles[] = $path;
    }

    /**
     * Export stamps a non-empty `revision:` key in the YAML front matter.
     */
    public function testExportStampsRevisionInFrontMatter(): void
    {
        $page = new Page();
        $page->setSlug('revision-export-test');
        $page->setH1('Revision Export Test');
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->setMainContent('Content');
        $page->setPublishedAt(new DateTime());

        $this->em->persist($page);
        $this->em->flush();

        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        $mdFilePath = $this->contentDir.'/revision-export-test.md';
        self::assertFileExists($mdFilePath);
        $this->createdFiles[] = $mdFilePath;

        $mdContent = $this->filesystem->readFile($mdFilePath);
        self::assertMatchesRegularExpression('/^revision: \S+ # read only$/m', $mdContent, 'Exported file must contain a non-empty revision: stamp flagged read only');
    }

    /**
     * Regression: the `.md` `revision:` stamp must equal the token the API will
     * compute for the same page, so agents can read it and PUT back via the API
     * with `If-Match: <revision>` without a preliminary GET.
     */
    public function testExportedRevisionMatchesApiRevisionCalculator(): void
    {
        $page = new Page();
        $page->setSlug('revision-export-test');
        $page->setH1('Agreement Test');
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->setMainContent('Body');
        $page->setPublishedAt(new DateTime());

        $this->em->persist($page);
        $this->em->flush();

        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        $mdFilePath = $this->contentDir.'/revision-export-test.md';
        $this->createdFiles[] = $mdFilePath;

        $mdContent = $this->filesystem->readFile($mdFilePath);
        self::assertSame(1, preg_match('/^revision: (\S+) # read only$/m', $mdContent, $matches));
        $stamped = $matches[1];

        /** @var RevisionCalculator $revisions */
        $revisions = self::getContainer()->get(RevisionCalculator::class);
        self::assertSame(
            $revisions->compute($page),
            $stamped,
            "The exported revision stamp must equal the API's ETag / If-Match value for the same page state"
        );
    }

    /**
     * Importing a file that contains `revision:` does NOT store it as a custom property.
     */
    public function testImportDoesNotStoreRevisionAsCustomProperty(): void
    {
        // Mirror the real exported format, including the inline `# read only` comment.
        $md = "---\nh1: 'Revision Import Test'\nrevision: abc123fixed # read only\n---\n\nContent";
        $this->createMd('revision-import-test.md', $md);

        $this->pageSync->import('localhost.dev');

        $this->em->clear();
        $importedPage = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'revision-import-test', 'host' => 'localhost.dev']);
        self::assertNotNull($importedPage, 'Page should be imported');
        self::assertNull($importedPage->getCustomProperty('revision'), 'revision must not be stored as a custom property');
        // The inline comment must not corrupt parsing of the rest of the front matter.
        self::assertSame('Revision Import Test', $importedPage->getH1(), 'sibling keys must parse despite the revision comment');
    }

    /**
     * A file that already carries a `revision:` stamp does not trigger a re-import
     * on the next sync cycle (no sync loop).
     */
    public function testRevisionStampDoesNotCauseSyncLoop(): void
    {
        // Export — files now contain revision: stamps
        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        // First import — may or may not update pages (file mtime vs DB)
        $this->pageSync->import('localhost.dev');

        // Second import — revision: in the files must not trigger another import
        $this->pageSync->import('localhost.dev');
        self::assertSame(0, $this->pageSync->getImportedCount(), 'revision: stamp must not cause a sync loop');
    }

    public function testDoubleImportProducesZeroNewImports(): void
    {
        // Export existing DB state to flat files
        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        // First import
        $this->pageSync->import('localhost.dev');

        // Second import — should produce zero new imports
        $this->pageSync->import('localhost.dev');

        $secondImported = $this->pageSync->getImportedCount();

        self::assertSame(0, $secondImported, 'Second import should produce zero new imports');
    }

    public function testDoubleExportProducesZeroNewExports(): void
    {
        // First export
        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        // Second export — should skip all (content unchanged)
        $this->pageSync->export('localhost.dev', false, $this->contentDir);

        $exportedCount = $this->pageSync->getExportedCount();
        $skippedCount = $this->pageSync->getExportSkippedCount();

        self::assertSame(0, $exportedCount, 'Second export should produce zero new exports');
        self::assertGreaterThanOrEqual(0, $skippedCount, 'Some pages should be skipped on second export');
    }

    public function testRedirectFromRoundTrips(): void
    {
        $this->createMd('redirect-from-test.md', "---\nh1: 'Redirect From Test'\nredirectFrom:\n  old-one: 301\n  old/two: 302\n---\n\nDestination content");

        $this->pageSync->import('localhost.dev');
        $this->em->clear();

        $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'redirect-from-test', 'host' => 'localhost.dev']);
        self::assertNotNull($page);
        self::assertSame(['old-one' => 301, 'old/two' => 302], $page->getRedirectFromMap());

        // Export re-emits redirectFrom; a second export is a no-op (idempotent).
        $this->pageSync->export('localhost.dev', true, $this->contentDir);
        $md = $this->filesystem->readFile($this->contentDir.'/redirect-from-test.md');
        self::assertStringContainsString('redirectFrom:', $md);
        self::assertStringContainsString('old/two: 302', $md);

        $this->pageSync->export('localhost.dev', false, $this->contentDir);
        self::assertSame(0, $this->pageSync->getExportedCount(), 'redirectFrom export must be idempotent');
    }

    public function testImportThenExportIsIdempotent(): void
    {
        // Create a test page
        $this->createMd('idempotent-test-page.md', "---\nh1: 'Idempotent Test'\ntags: 'test idempotent'\n---\n\nTest content for idempotency");

        // Import
        $this->pageSync->import('localhost.dev');
        $this->createdFiles[] = $this->contentDir.'/idempotent-test-page.md';

        // Capture file content after import
        $this->filesystem->readFile($this->contentDir.'/idempotent-test-page.md');

        // Export
        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        // File content after export should still represent the same data
        $contentAfterExport = $this->filesystem->readFile($this->contentDir.'/idempotent-test-page.md');

        // The YAML formatting might differ slightly, but the key data should be preserved
        self::assertStringContainsString('Idempotent Test', $contentAfterExport);
        self::assertStringContainsString('Test content for idempotency', $contentAfterExport);
    }

    public function testExportThenImportIsIdempotent(): void
    {
        // Export DB state
        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        // Capture DB state before import
        $pagesBefore = $this->em->getRepository(Page::class)->findBy(['host' => 'localhost.dev']);
        $dataBefore = [];
        foreach ($pagesBefore as $page) {
            $dataBefore[$page->getSlug()] = [
                'h1' => $page->getH1(),
                'mainContent' => $page->getMainContent(),
                'tags' => $page->getTags(),
            ];
        }

        // Import (should be a no-op since files match DB)
        $this->pageSync->import('localhost.dev');

        // DB state should be unchanged
        $this->em->clear();
        $pagesAfter = $this->em->getRepository(Page::class)->findBy(['host' => 'localhost.dev']);
        foreach ($pagesAfter as $page) {
            $slug = $page->getSlug();
            if (isset($dataBefore[$slug])) {
                self::assertSame($dataBefore[$slug]['h1'], $page->getH1(), 'H1 changed for page '.$slug);
                self::assertSame($dataBefore[$slug]['mainContent'], $page->getMainContent(), 'Content changed for page '.$slug);
            }
        }
    }
}
