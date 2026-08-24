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
 * Re-importing a file the database already holds must not UPDATE the page.
 * The import used to reassign publishedAt (and holdPublicationAt) with a fresh
 * DateTime carrying the same instant; Doctrine's changeset compares objects by
 * identity, so every page went dirty, preUpdate bumped updatedAt, and the next
 * export rewrote the revision of every .md on the host — churning git on syncs
 * that changed nothing.
 */
#[Group('integration')]
final class PageImporterUnchangedReimportTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private const string LAST_EDIT = '2026-01-02 03:04:05';

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

    public function testReimportingAnUnchangedFileDoesNotUpdateThePage(): void
    {
        self::bootKernel();

        $this->write("publishedAt: '2024-09-27 14:29'", "holdPublicationAt: '2025-01-01 08:00'");
        $this->import();

        $page = $this->page();
        self::assertEquals(new DateTime('2024-09-27 14:29'), $page->publishedAt);
        self::assertEquals(new DateTime(self::LAST_EDIT), $page->updatedAt);

        // Same mtime as updatedAt (how the exporter leaves files): the early skip
        // does not apply, the page is re-hydrated from the file.
        $importer = $this->import();

        self::assertSame(0, $importer->getImportedCount());
        self::assertSame(1, $importer->getSkippedCount());
        self::assertEquals(new DateTime(self::LAST_EDIT), $this->page()->updatedAt, 're-importing an unchanged file must not touch the page row');
    }

    public function testAChangedPublishedAtStillLands(): void
    {
        self::bootKernel();

        $this->write("publishedAt: '2024-09-27 14:29'");
        $this->import();

        $this->write("publishedAt: '2024-10-01 09:00'");
        $importer = $this->import();

        self::assertSame(1, $importer->getImportedCount());
        self::assertEquals(new DateTime('2024-10-01 09:00'), $this->page()->publishedAt);
    }

    public function testAChangedHoldPublicationAtStillLands(): void
    {
        self::bootKernel();

        $this->write("publishedAt: '2024-09-27 14:29'", "holdPublicationAt: '2025-01-01 08:00'");
        $this->import();

        $this->write("publishedAt: '2024-09-27 14:29'", "holdPublicationAt: '2025-02-02 09:30'");
        $importer = $this->import();

        self::assertSame(1, $importer->getImportedCount());
        self::assertEquals(new DateTime('2025-02-02 09:30'), $this->page()->holdPublicationAt);
    }

    public function testRemovingMetaRobotsResetsIt(): void
    {
        self::bootKernel();

        $this->write('metaRobots: noindex');
        $this->import();

        self::assertSame('noindex', $this->page()->metaRobots);

        $this->write();
        $importer = $this->import();

        self::assertSame(1, $importer->getImportedCount());
        self::assertSame('', $this->page()->metaRobots);
    }

    public function testSnakeCasePropertyIsNotTreatedAsMissing(): void
    {
        self::bootKernel();

        $this->write('metaRobots: noindex');
        $this->import();

        $this->write('meta_robots: nofollow');
        $importer = $this->import();

        self::assertSame(1, $importer->getImportedCount());
        self::assertSame('nofollow', $this->page()->metaRobots);
    }

    public function testAFileTurnedDraftUnpublishesThePage(): void
    {
        self::bootKernel();

        $this->write("publishedAt: '2024-09-27 14:29'");
        $this->import();

        $this->write('publishedAt: draft');
        $importer = $this->import();

        self::assertSame(1, $importer->getImportedCount());
        self::assertNull($this->page()->publishedAt);
    }

    public function testADraftFileGainingADateIsPublished(): void
    {
        self::bootKernel();

        $this->write('publishedAt: draft');
        $this->import();

        $this->write("publishedAt: '2024-09-27 14:29'");
        $importer = $this->import();

        self::assertSame(1, $importer->getImportedCount());
        self::assertEquals(new DateTime('2024-09-27 14:29'), $this->page()->publishedAt);
    }

    public function testReimportingADraftPageDoesNotUpdateIt(): void
    {
        self::bootKernel();

        $this->write('publishedAt: draft');
        $this->import();

        self::assertNull($this->page()->publishedAt, 'a page created from a draft file is a draft');

        $importer = $this->import();

        self::assertSame(1, $importer->getSkippedCount());
        self::assertEquals(new DateTime(self::LAST_EDIT), $this->page()->updatedAt);
    }

    private function write(string ...$extraMatter): void
    {
        if ('' === $this->slug) {
            $this->slug = 'unchanged-reimport-'.uniqid();
            $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
            $this->file = $contentDirFinder->get(self::HOST).'/'.$this->slug.'.md';
        }

        $matter = implode("\n", ['h1: Unchanged reimport fixture', 'locale: en', ...$extraMatter]);

        file_put_contents($this->file, "---\n".$matter."\n---\nBody.\n");
    }

    private function import(): PageImporter
    {
        $importer = self::getContainer()->get(PageImporter::class);
        $importer->resetImport();
        $importer->import($this->file, new DateTime(self::LAST_EDIT));
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
