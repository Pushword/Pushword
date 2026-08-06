<?php

namespace Pushword\Flat\Tests\Importer;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Importer\PageImporter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * filemtime() resolves to the second and the import stamps updatedAt with it, so
 * an edit landing in that same second ties instead of looking newer. The tie must
 * be decided on content, not handed to the database — otherwise the edit is
 * invisible for good, and the counters report a clean sync.
 */
#[Group('integration')]
final class PageImporterSameSecondEditTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private string $slug = '';

    private string $file = '';

    protected function tearDown(): void
    {
        if ('' !== $this->file) {
            @unlink($this->file);
        }

        if ('' !== $this->slug && self::$booted) {
            $entityManager = self::getContainer()->get(EntityManagerInterface::class);
            $page = $entityManager->getRepository(Page::class)->findOneBy(['slug' => $this->slug, 'host' => self::HOST]);
            if ($page instanceof Page) {
                $entityManager->remove($page);
                $entityManager->flush();
            }
        }

        parent::tearDown();
    }

    public function testAnEditMadeInTheSameSecondAsTheImportIsNotLost(): void
    {
        self::bootKernel();

        $lastEditDateTime = new DateTime('2026-01-02 03:04:05');
        $this->write('First body.');
        $this->import($lastEditDateTime);

        self::assertSame('First body.', $this->page()->mainContent);

        // Same mtime, different content: the file was edited within the second.
        $this->write('Second body.');
        $importer = $this->import($lastEditDateTime);

        self::assertSame('Second body.', $this->page()->mainContent, 'the same-second edit reaches the database');
        self::assertSame(1, $importer->getImportedCount());
        self::assertSame(0, $importer->getSkippedCount());
    }

    public function testAnUntouchedFileStillCountsAsSkipped(): void
    {
        self::bootKernel();

        $lastEditDateTime = new DateTime('2026-01-02 03:04:05');
        $this->write('Only body.');
        $this->import($lastEditDateTime);

        $importer = $this->import($lastEditDateTime);

        self::assertSame(0, $importer->getImportedCount(), 're-reading an unchanged file is not an import');
        self::assertSame(1, $importer->getSkippedCount());
    }

    /** The tie is broken on content; a database genuinely ahead of the file still wins. */
    public function testAPageEditedAfterItsFileIsNotOverwritten(): void
    {
        self::bootKernel();

        $this->write('File body.');
        $this->import(new DateTime('2026-01-02 03:04:05'));

        $importer = $this->import(new DateTime('2026-01-02 03:04:04'));

        self::assertSame('File body.', $this->page()->mainContent);
        self::assertSame(0, $importer->getImportedCount());
        self::assertSame(1, $importer->getSkippedCount());
    }

    /**
     * Associations are set after the whole run, from the front matter the hydration
     * queued, so a same-second edit touching only one of them lands — the counters
     * call it skipped, because the columns the revision covers did not move.
     */
    public function testASameSecondAssociationEditLandsThoughItCountsAsSkipped(): void
    {
        self::bootKernel();

        $lastEditDateTime = new DateTime('2026-01-02 03:04:05');
        $this->write('Body.');
        $this->import($lastEditDateTime);

        self::assertNull($this->page()->parentPage);

        $this->write('Body.', 'parent: homepage');
        $importer = $this->import($lastEditDateTime);

        self::assertSame('homepage', $this->page()->parentPage?->slug);
        self::assertSame(1, $importer->getSkippedCount());
    }

    private function write(string $body, string ...$extraMatter): void
    {
        if ('' === $this->slug) {
            $this->slug = 'same-second-'.uniqid();
            $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
            $this->file = $contentDirFinder->get(self::HOST).'/'.$this->slug.'.md';
        }

        $matter = implode("\n", ['h1: Same second fixture', 'locale: en', ...$extraMatter]);

        file_put_contents($this->file, "---\n".$matter."\n---\n".$body."\n");
    }

    private function import(DateTime $lastEditDateTime): PageImporter
    {
        $importer = self::getContainer()->get(PageImporter::class);
        $importer->resetImport();
        $importer->import($this->file, $lastEditDateTime);
        $importer->finishImport();

        return $importer;
    }

    private function page(): Page
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $page = $entityManager->getRepository(Page::class)->findOneBy(['slug' => $this->slug, 'host' => self::HOST]);
        self::assertInstanceOf(Page::class, $page);

        return $page;
    }
}
