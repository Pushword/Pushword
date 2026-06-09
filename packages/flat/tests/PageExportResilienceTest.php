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
use Symfony\Component\Yaml\Yaml;

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

    /**
     * Regression: a typographic apostrophe (U+2019) in a front-matter value
     * must produce valid YAML. Yaml::dump() wraps the value in a single-quoted
     * scalar where the curly apostrophe is harmless; the old normalizeQuotes()
     * ran over the *dumped* YAML and straightened it to "'", which prematurely
     * terminated the scalar (e.g. 'l’Albanie' -> 'l'Albanie') — invalid YAML
     * that broke flat import for every page with such a title.
     */
    public function testTypographicQuotesInFrontMatterStayValidYaml(): void
    {
        $page = new Page(false);
        $page->setSlug('apostrophe-page');
        $page->host = 'localhost.dev';
        $page->setTitle("Tour de l\u{2019}Albanie : \u{201C}joyaux\u{201D} de l\u{2019}\u{00CE}le");
        $page->setH1("Les \u{00EE}les d\u{2019}\u{00C5}land");

        /** @var PageExporter $exporter */
        $exporter = self::getContainer()->get(PageExporter::class);
        $content = $exporter->generatePageContent($page);

        self::assertSame(1, preg_match('/^---\n(.*?)\n---\n/s', $content, $matches), 'front matter delimiters present');
        $parsed = Yaml::parse($matches[1]); // throws on invalid YAML — the regression
        self::assertIsArray($parsed);

        // Curly quotes are straightened *before* the dump, so they round-trip as ASCII.
        self::assertSame('Tour de l\'Albanie : "joyaux" de l\'Île', $parsed['title']);
        self::assertSame('Les îles d\'Åland', $parsed['h1']);
    }
}
