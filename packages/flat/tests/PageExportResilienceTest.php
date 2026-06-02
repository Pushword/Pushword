<?php

namespace Pushword\Flat\Tests;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Flat\Exporter\PageExporter;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A single page that throws during export must not abort the whole snapshot —
 * mirroring the per-file resilience of the import side (PageSync::doImport).
 */
#[Group('integration')]
final class PageExportResilienceTest extends KernelTestCase
{
    private Filesystem $filesystem;

    private string $testContentDir;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->filesystem = new Filesystem();
        $this->testContentDir = self::getContainer()->getParameter('kernel.cache_dir').'/export-resilience';
        $this->filesystem->remove($this->testContentDir);
        $this->filesystem->mkdir($this->testContentDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->testContentDir);
        parent::tearDown();
    }

    public function testOneThrowingPageDoesNotAbortExport(): void
    {
        /** @var PageRepository $pageRepo */
        $pageRepo = self::getContainer()->get(PageRepository::class);
        $realPages = $pageRepo->findByHost('localhost.dev');

        $badPage = new class extends Page {
            #[Override]
            public function getMainContent(): string
            {
                throw new RuntimeException('boom: this page cannot be exported');
            }
        };
        $badPage->setSlug('broken-export-page');
        $badPage->host = 'localhost.dev';

        /** @var PageExporter $exporter */
        $exporter = self::getContainer()->get(PageExporter::class);
        $exporter->exportDir = $this->testContentDir;

        // Must not throw, even though one page raises mid-export.
        $exporter->exportPagesSubset(
            ['homepage', 'broken-export-page'],
            [...$realPages, $badPage],
        );

        // The healthy page was still exported; the bad one was skipped.
        self::assertFileExists($this->testContentDir.'/homepage.md');
        self::assertFileDoesNotExist($this->testContentDir.'/broken-export-page.md');
    }
}
