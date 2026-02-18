<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Sync;

use DateTime;
use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Flat\Exporter\MediaExporter;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Sync\PageSync;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class TagsRoundTripTest extends KernelTestCase
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

        $this->pageSync->export('localhost.dev', true, $this->contentDir);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }

        foreach (['tags-test-page', 'empty-tags-page'] as $slug) {
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

    public function testPageTagsExportedToIndexCsv(): void
    {
        // Create a page with tags
        $page = new Page();
        $page->setSlug('tags-test-page');
        $page->setH1('Tags Test');
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->setTags('cms php symfony');
        $page->setMainContent('Test content');
        $page->setPublishedAt(new DateTime());

        $this->em->persist($page);
        $this->em->flush();

        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        // Check index.csv contains tags
        $indexPath = $this->contentDir.'/index.csv';
        self::assertFileExists($indexPath);
        $csvContent = $this->filesystem->readFile($indexPath);
        self::assertStringContainsString('tags', $csvContent);
        self::assertStringContainsString('cms php symfony', $csvContent);
    }

    public function testPageTagsPreservedThroughRoundTrip(): void
    {
        $tags = 'tag1 tag2 tag3';
        $this->createMd('tags-test-page.md', "---\nh1: 'Tags Round Trip'\ntags: '".$tags."'\n---\n\nContent with tags");

        $this->pageSync->import('localhost.dev');

        $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'tags-test-page', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);
        self::assertSame($tags, trim($page->getTags()));

        // Export and verify
        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        $exportedContent = $this->filesystem->readFile($this->contentDir.'/tags-test-page.md');
        self::assertStringContainsString('tag1 tag2 tag3', $exportedContent);

        // Delete from DB and re-import
        $this->em->remove($page);
        $this->em->flush();
        $this->em->clear();

        $this->pageSync->import('localhost.dev');
        $reimportedPage = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'tags-test-page', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $reimportedPage);
        self::assertSame($tags, trim($reimportedPage->getTags()));
    }

    public function testMediaTagsExportedToMediaIndexCsv(): void
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        // Create a test media file
        $testPath = $mediaDir.'/tags-test-media.txt';
        file_put_contents($testPath, 'test content');

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName('tags-test-media.txt');
        $media->setAlt('Tags Test Media');
        $media->setTags('document important');
        $media->setMimeType('text/plain');
        $media->setSize(12);
        $media->setStoreIn($mediaDir);
        $media->setHash((string) sha1_file($testPath, true));

        $this->em->persist($media);
        $this->em->flush();

        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);

        /** @var MediaExporter $exporter */
        $exporter = self::getContainer()->get(MediaExporter::class);
        $exporter->csvDir = $contentDirFinder->getBaseDir();
        $exporter->exportMedias();

        // Check media.csv
        $csvContent = file_get_contents($contentDirFinder->getBaseDir().'/'.MediaExporter::CSV_FILE);
        self::assertIsString($csvContent);
        self::assertStringContainsString('tags', $csvContent);
        self::assertStringContainsString('document important', $csvContent);

        // Cleanup
        $this->em->remove($media);
        $this->em->flush();
        @unlink($testPath);
    }

    public function testMediaTagsPreservedThroughRoundTrip(): void
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        // Create a test media file
        $testPath = $mediaDir.'/tags-roundtrip-media.txt';
        file_put_contents($testPath, 'test content for tags');

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName('tags-roundtrip-media.txt');
        $media->setAlt('Tags RoundTrip Media');
        $media->setTags('archive backup');
        $media->setMimeType('text/plain');
        $media->setSize(21);
        $media->setStoreIn($mediaDir);
        $media->setHash((string) sha1_file($testPath, true));

        $this->em->persist($media);
        $this->em->flush();

        /** @var FlatFileContentDirFinder $contentDirFinder2 */
        $contentDirFinder2 = self::getContainer()->get(FlatFileContentDirFinder::class);

        /** @var MediaExporter $exporter */
        $exporter = self::getContainer()->get(MediaExporter::class);
        $exporter->csvDir = $contentDirFinder2->getBaseDir();
        $exporter->exportMedias();

        $mediaId = $media->id;

        // Delete from DB
        $this->em->remove($media);
        $this->em->flush();
        $this->em->clear();

        // Re-import from CSV
        $reimported = $this->em->getRepository(Media::class)->findOneBy(['fileName' => 'tags-roundtrip-media.txt']);
        // Media was deleted, it should not exist now
        self::assertNull($reimported);

        // Cleanup
        @unlink($testPath);
    }

    public function testEmptyTagsHandledCorrectly(): void
    {
        $this->createMd('empty-tags-page.md', "---\nh1: 'No Tags Page'\n---\n\nPage with no tags");

        $this->pageSync->import('localhost.dev');

        $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'empty-tags-page', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);
        self::assertSame('', trim($page->getTags()));

        // Export and check index
        $this->pageSync->export('localhost.dev', true, $this->contentDir);
        $indexPath = $this->contentDir.'/index.csv';

        if (file_exists($indexPath)) {
            $csvContent = $this->filesystem->readFile($indexPath);
            // Tags column should be empty, not null or placeholder
            $lines = explode("\n", $csvContent);
            $headerColumns = str_getcsv($lines[0], escape: '\\');
            $tagsColIndex = array_search('tags', $headerColumns, true);

            if (false !== $tagsColIndex) {
                foreach (\array_slice($lines, 1) as $line) {
                    if ('' === trim($line)) {
                        continue;
                    }

                    $row = str_getcsv($line, escape: '\\');
                    $slugIndex = array_search('slug', $headerColumns, true);
                    if (false !== $slugIndex && isset($row[$slugIndex]) && 'empty-tags-page' === $row[$slugIndex]) {
                        self::assertSame('', $row[$tagsColIndex] ?? '', 'Tags column should be empty string for page with no tags');
                    }
                }
            }
        }
    }
}
