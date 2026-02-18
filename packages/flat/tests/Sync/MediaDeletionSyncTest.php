<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Sync;

use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Flat\Exporter\MediaExporter;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Importer\MediaImporter;
use Pushword\Flat\Sync\MediaSync;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class MediaDeletionSyncTest extends KernelTestCase
{
    private EntityManager $em;

    /** @var string[] */
    private array $tempFiles = [];

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->em = $em;
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function getMediaCsvPath(): string
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);

        return $contentDirFinder->getBaseDir().'/'.MediaExporter::CSV_FILE;
    }

    private function getContentBaseDir(): string
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);

        return $contentDirFinder->getBaseDir();
    }

    private function createMediaEntity(string $fileName, string $alt, string $mediaDir, string $projectDir): Media
    {
        // Create physical file
        $filePath = $mediaDir.'/'.$fileName;
        file_put_contents($filePath, 'test content for '.$fileName);
        $this->tempFiles[] = $filePath;

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName($fileName);
        $media->setAlt($alt);
        $media->setMimeType('text/plain');
        $media->setSize(\strlen('test content for '.$fileName));
        $media->setStoreIn($mediaDir);
        $media->setHash((string) sha1_file($filePath, true));

        $this->em->persist($media);

        return $media;
    }

    public function testMediaDeletedFromDbWhenRemovedFromCsv(): void
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        // Create 2 media entities
        $media1 = $this->createMediaEntity('del-test-1.txt', 'Delete Test 1', $mediaDir, $projectDir);
        $media2 = $this->createMediaEntity('del-test-2.txt', 'Delete Test 2', $mediaDir, $projectDir);
        $this->em->flush();

        $media1Id = $media1->id;
        $media2Id = $media2->id;

        // Export to generate CSV with both media
        /** @var MediaExporter $exporter */
        $exporter = self::getContainer()->get(MediaExporter::class);
        $exporter->csvDir = $this->getContentBaseDir();
        $exporter->exportMedias();

        // Remove media2 row from CSV (keep only media1)
        $csvContent = "id,fileName,alt,tags\n{$media1Id},del-test-1.txt,Delete Test 1,\n";
        new Filesystem()->dumpFile($this->getMediaCsvPath(), $csvContent);

        // Import - should delete media2
        /** @var MediaSync $mediaSync */
        $mediaSync = self::getContainer()->get(MediaSync::class);
        $mediaSync->import('localhost.dev');

        // Verify media2 was deleted
        $this->em->clear();
        $remainingMedia1 = $this->em->getRepository(Media::class)->find($media1Id);
        $remainingMedia2 = $this->em->getRepository(Media::class)->find($media2Id);

        self::assertInstanceOf(Media::class, $remainingMedia1, 'Media 1 should still exist');
        self::assertNull($remainingMedia2, 'Media 2 should be deleted');

        // Cleanup
        $this->em->remove($remainingMedia1);
        $this->em->flush();
    }

    public function testMediaNotDeletedWhenCsvHasNoIds(): void
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        $media = $this->createMediaEntity('no-id-test.txt', 'No ID Test', $mediaDir, $projectDir);
        $this->em->flush();
        $mediaId = $media->id;

        // Create CSV without ID column
        new Filesystem()->dumpFile($this->getMediaCsvPath(), "fileName,alt\nno-id-test.txt,No ID Test\n");

        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->resetIndex();
        $importer->loadIndex($this->getMediaCsvPath());

        // getImportedIds should be empty (no IDs in CSV)
        self::assertSame([], $importer->getImportedIds(), 'No IDs should be tracked when CSV has no ID column');

        // Cleanup
        $this->em->remove($media);
        $this->em->flush();
    }

    public function testMediaNotDeletedOnEmptyImportedIds(): void
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        $media = $this->createMediaEntity('header-only-test.txt', 'Header Only', $mediaDir, $projectDir);
        $this->em->flush();
        $mediaId = $media->id;

        // Create CSV with header only (no data rows)
        new Filesystem()->dumpFile($this->getMediaCsvPath(), "id,fileName,alt,tags\n");

        /** @var MediaSync $mediaSync */
        $mediaSync = self::getContainer()->get(MediaSync::class);
        $mediaSync->import('localhost.dev');

        // Media should NOT be deleted (empty IDs = no deletion)
        $this->em->clear();
        $remaining = $this->em->getRepository(Media::class)->find($mediaId);
        self::assertInstanceOf(Media::class, $remaining, 'Media should not be deleted when CSV has header only');

        // Cleanup
        $this->em->remove($remaining);
        $this->em->flush();
    }

    public function testMultipleMediaDeletionInOneCycle(): void
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        // Create 5 media entities
        $mediaEntities = [];
        for ($i = 1; $i <= 5; ++$i) {
            $mediaEntities[$i] = $this->createMediaEntity(sprintf('multi-del-%d.txt', $i), 'Multi Delete '.$i, $mediaDir, $projectDir);
        }

        $this->em->flush();

        $ids = [];
        foreach ($mediaEntities as $i => $media) {
            $ids[$i] = $media->id;
        }

        // Export all
        /** @var MediaExporter $exporter */
        $exporter = self::getContainer()->get(MediaExporter::class);
        $exporter->csvDir = $this->getContentBaseDir();
        $exporter->exportMedias();

        // Remove 3 of 5 from CSV (keep only 1 and 4)
        $csvContent = "id,fileName,alt,tags\n{$ids[1]},multi-del-1.txt,Multi Delete 1,\n{$ids[4]},multi-del-4.txt,Multi Delete 4,\n";
        new Filesystem()->dumpFile($this->getMediaCsvPath(), $csvContent);

        /** @var MediaSync $mediaSync */
        $mediaSync = self::getContainer()->get(MediaSync::class);
        $mediaSync->import('localhost.dev');

        self::assertGreaterThanOrEqual(3, $mediaSync->getDeletedCount(), 'At least 3 media should be deleted');

        // Verify correct ones survived
        $this->em->clear();
        self::assertInstanceOf(Media::class, $this->em->getRepository(Media::class)->find($ids[1]));
        self::assertNull($this->em->getRepository(Media::class)->find($ids[2]));
        self::assertNull($this->em->getRepository(Media::class)->find($ids[3]));
        self::assertInstanceOf(Media::class, $this->em->getRepository(Media::class)->find($ids[4]));
        self::assertNull($this->em->getRepository(Media::class)->find($ids[5]));

        // Cleanup survivors
        foreach ([1, 4] as $i) {
            $m = $this->em->getRepository(Media::class)->find($ids[$i]);
            if ($m instanceof Media) {
                $this->em->remove($m);
            }
        }

        $this->em->flush();
    }
}
