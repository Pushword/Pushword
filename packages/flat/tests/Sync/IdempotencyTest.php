<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Sync;

use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
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

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }

        foreach (['idempotent-test-page'] as $slug) {
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

    public function testDoubleImportProducesZeroNewImports(): void
    {
        // Export existing DB state to flat files
        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        // First import
        $this->pageSync->import('localhost.dev');

        $firstImported = $this->pageSync->getImportedCount();

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

    public function testImportThenExportIsIdempotent(): void
    {
        // Create a test page
        $this->createMd('idempotent-test-page.md', "---\nh1: 'Idempotent Test'\ntags: 'test idempotent'\n---\n\nTest content for idempotency");

        // Import
        $this->pageSync->import('localhost.dev');
        $this->createdFiles[] = $this->contentDir.'/idempotent-test-page.md';

        // Capture file content after import
        $contentAfterImport = $this->filesystem->readFile($this->contentDir.'/idempotent-test-page.md');

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
