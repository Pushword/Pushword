<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Sync;

use DateTime;
use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Flat\Exporter\MediaExporter;
use Pushword\Flat\Importer\MediaImporter;
use Pushword\Flat\Sync\MediaSync;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class MediaEdgeCasesTest extends KernelTestCase
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

    private function getMediaDir(): string
    {
        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        return $mediaDir;
    }

    private function getProjectDir(): string
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        return $projectDir;
    }

    public function testNewFileInMediaDirWithoutCsvEntry(): void
    {
        $mediaDir = $this->getMediaDir();

        // Create a new file in media dir
        $newFile = $mediaDir.'/edge-new-no-csv.txt';
        file_put_contents($newFile, 'brand new file');
        $this->tempFiles[] = $newFile;

        // Create CSV without the new file
        /** @var MediaStorageAdapter $mediaStorage */
        $mediaStorage = self::getContainer()->get(MediaStorageAdapter::class);
        $mediaStorage->write(MediaExporter::INDEX_FILE, "id,fileName,alt,tags\n");

        /** @var MediaSync $mediaSync */
        $mediaSync = self::getContainer()->get(MediaSync::class);
        $mediaSync->import('localhost.dev');

        // File should be imported as new media
        $imported = $this->em->getRepository(Media::class)->findOneBy(['fileName' => 'edge-new-no-csv.txt']);
        self::assertInstanceOf(Media::class, $imported, 'New file without CSV entry should be imported');

        // Cleanup
        $this->em->remove($imported);
        $this->em->flush();
    }

    public function testModifiedFileContentDetectedViaHash(): void
    {
        $mediaDir = $this->getMediaDir();
        $projectDir = $this->getProjectDir();

        // Create media with known hash
        $filePath = $mediaDir.'/edge-hash-change.txt';
        file_put_contents($filePath, 'original content');
        $this->tempFiles[] = $filePath;

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName('edge-hash-change.txt');
        $media->setAlt('Hash Change Test');
        $media->setMimeType('text/plain');
        $media->setSize(16);
        $media->setStoreIn($mediaDir);
        $media->setHash((string) sha1_file($filePath, true));

        $this->em->persist($media);
        $this->em->flush();

        $mediaId = $media->id;

        // Modify file content
        file_put_contents($filePath, 'modified content that differs');

        // Create CSV with the media
        /** @var MediaStorageAdapter $mediaStorage */
        $mediaStorage = self::getContainer()->get(MediaStorageAdapter::class);
        $csvContent = "id,fileName,alt,tags\n{$mediaId},edge-hash-change.txt,Hash Change Test,\n";
        $mediaStorage->write(MediaExporter::INDEX_FILE, $csvContent);

        /** @var MediaSync $mediaSync */
        $mediaSync = self::getContainer()->get(MediaSync::class);
        $mediaSync->import('localhost.dev');

        // Should have been re-imported
        self::assertGreaterThanOrEqual(1, $mediaSync->getImportedCount(), 'Modified file should be re-imported');

        // Cleanup
        $this->em->clear();
        $media = $this->em->getRepository(Media::class)->find($mediaId);
        if ($media instanceof Media) {
            $this->em->remove($media);
            $this->em->flush();
        }
    }

    public function testDuplicateFileNameInCsv(): void
    {
        $mediaDir = $this->getMediaDir();

        // Create file
        $filePath = $mediaDir.'/edge-duplicate.txt';
        file_put_contents($filePath, 'duplicate test');
        $this->tempFiles[] = $filePath;

        // Create CSV with duplicate fileName
        /** @var MediaStorageAdapter $mediaStorage */
        $mediaStorage = self::getContainer()->get(MediaStorageAdapter::class);
        $csvContent = "id,fileName,alt,tags\n,edge-duplicate.txt,First Alt,\n,edge-duplicate.txt,Second Alt,\n";
        $mediaStorage->write(MediaExporter::INDEX_FILE, $csvContent);

        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->resetIndex();
        $importer->loadIndexFromStorage();

        // Should handle gracefully — first entry wins
        self::assertTrue($importer->hasIndexData(), 'Index should have data despite duplicates');

        // Import should not crash
        $importer->import($filePath, new DateTime());
        $importer->finishImport();

        // Cleanup
        $media = $this->em->getRepository(Media::class)->findOneBy(['fileName' => 'edge-duplicate.txt']);
        if ($media instanceof Media) {
            $this->em->remove($media);
            $this->em->flush();
        }
    }

    public function testMediaImportWithEmptyAltUsesFileName(): void
    {
        $mediaDir = $this->getMediaDir();

        $filePath = $mediaDir.'/edge-empty-alt.txt';
        file_put_contents($filePath, 'content with empty alt');
        $this->tempFiles[] = $filePath;

        // CSV with empty alt
        /** @var MediaStorageAdapter $mediaStorage */
        $mediaStorage = self::getContainer()->get(MediaStorageAdapter::class);
        $csvContent = "id,fileName,alt,tags\n,edge-empty-alt.txt,,\n";
        $mediaStorage->write(MediaExporter::INDEX_FILE, $csvContent);

        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->mediaDir = $mediaDir;
        $importer->projectDir = $this->getProjectDir();
        $importer->resetIndex();
        $importer->loadIndexFromStorage();

        $importer->import($filePath, new DateTime());
        $importer->finishImport();

        $media = $this->em->getRepository(Media::class)->findOneBy(['fileName' => 'edge-empty-alt.txt']);
        self::assertInstanceOf(Media::class, $media);
        // Alt should contain the filename since CSV alt was empty
        self::assertStringContainsString('edge-empty-alt', $media->getAlt(true));

        // Cleanup
        $this->em->remove($media);
        $this->em->flush();
    }

    public function testCsvWithExtraUnknownColumns(): void
    {
        $mediaDir = $this->getMediaDir();

        $filePath = $mediaDir.'/edge-extra-cols.txt';
        file_put_contents($filePath, 'content with extra columns');
        $this->tempFiles[] = $filePath;

        // CSV with extra unknown columns
        /** @var MediaStorageAdapter $mediaStorage */
        $mediaStorage = self::getContainer()->get(MediaStorageAdapter::class);
        $csvContent = "id,fileName,alt,tags,customField1,customField2\n,edge-extra-cols.txt,Extra Cols Test,,value1,value2\n";
        $mediaStorage->write(MediaExporter::INDEX_FILE, $csvContent);

        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->mediaDir = $mediaDir;
        $importer->projectDir = $this->getProjectDir();
        $importer->resetIndex();
        $importer->loadIndexFromStorage();

        $importer->import($filePath, new DateTime());
        $importer->finishImport();

        $media = $this->em->getRepository(Media::class)->findOneBy(['fileName' => 'edge-extra-cols.txt']);
        self::assertInstanceOf(Media::class, $media);

        // Extra columns should be stored as custom properties
        $customProps = $media->getCustomProperties();
        self::assertArrayHasKey('customField1', $customProps);
        self::assertSame('value1', $customProps['customField1']);
        self::assertArrayHasKey('customField2', $customProps);
        self::assertSame('value2', $customProps['customField2']);

        // Cleanup
        $this->em->remove($media);
        $this->em->flush();
    }

    public function testLockAndTempFilesSkippedDuringImport(): void
    {
        $mediaDir = $this->getMediaDir();

        // Create lock/temp files
        $lockFile = $mediaDir.'/.~lock.index.csv#';
        $tempFile = $mediaDir.'/~$document.xlsx';
        file_put_contents($lockFile, 'lock content');
        file_put_contents($tempFile, 'temp content');
        $this->tempFiles[] = $lockFile;
        $this->tempFiles[] = $tempFile;

        /** @var MediaSync $mediaSync */
        $mediaSync = self::getContainer()->get(MediaSync::class);
        $mediaSync->import('localhost.dev');

        // Lock/temp files should NOT be imported
        $lock = $this->em->getRepository(Media::class)->findOneBy(['fileName' => '.~lock.index.csv#']);
        self::assertNull($lock, 'Lock file should not be imported');

        $temp = $this->em->getRepository(Media::class)->findOneBy(['fileName' => '~$document.xlsx']);
        self::assertNull($temp, 'Temp file should not be imported');
    }

    public function testDuplicateFileByHashUpdatesHistory(): void
    {
        $mediaDir = $this->getMediaDir();
        $projectDir = $this->getProjectDir();

        // Create original media
        $originalContent = 'unique content for dedup test '.uniqid();
        $originalPath = $mediaDir.'/edge-dedup-original.txt';
        file_put_contents($originalPath, $originalContent);
        $this->tempFiles[] = $originalPath;

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName('edge-dedup-original.txt');
        $media->setAlt('Dedup Original');
        $media->setMimeType('text/plain');
        $media->setSize(\strlen($originalContent));
        $media->setStoreIn($mediaDir);
        $media->setHash((string) sha1_file($originalPath, true));

        $this->em->persist($media);
        $this->em->flush();

        $mediaId = $media->id;

        // Create a duplicate file with same content but different name
        $dupePath = $mediaDir.'/edge-dedup-copy.txt';
        file_put_contents($dupePath, $originalContent);
        $this->tempFiles[] = $dupePath;

        // Import the duplicate — should detect via hash match and not create new entity
        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->mediaDir = $mediaDir;
        $importer->projectDir = $projectDir;
        $importer->resetIndex();

        $imported = $importer->importMedia($dupePath, new DateTime());
        self::assertFalse($imported, 'Duplicate file should be skipped (hash match detected)');

        // Flush the fileNameHistory update made by findMediaByHash
        $this->em->flush();

        // Existing media should have the new filename in history
        $this->em->clear();

        $updatedMedia = $this->em->getRepository(Media::class)->find($mediaId);
        self::assertInstanceOf(Media::class, $updatedMedia);
        self::assertContains('edge-dedup-copy.txt', $updatedMedia->getFileNameHistory(), 'Duplicate filename should be added to fileNameHistory');

        // Cleanup
        $this->em->remove($updatedMedia);
        $this->em->flush();
    }

    public function testOversizedImageResizedOnImport(): void
    {
        $mediaDir = $this->getMediaDir();
        $projectDir = $this->getProjectDir();

        // Create an oversized test image (3000x2000)
        $imgPath = $mediaDir.'/edge-oversized.png';
        $img = imagecreatetruecolor(3000, 2000);
        if (false === $img) {
            self::markTestSkipped('GD extension not available');
        }

        imagepng($img, $imgPath);
        imagedestroy($img);
        $this->tempFiles[] = $imgPath;

        // Verify it's oversized
        $sizeBefore = getimagesize($imgPath);
        self::assertNotFalse($sizeBefore);
        self::assertSame(3000, $sizeBefore[0]);
        self::assertSame(2000, $sizeBefore[1]);

        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->mediaDir = $mediaDir;
        $importer->projectDir = $projectDir;
        $importer->resetIndex();

        $imported = $importer->import($imgPath, new DateTime());
        $importer->finishImport();
        self::assertTrue($imported);

        // Image should be resized to max 1980x1280
        $localPath = $imgPath;
        // Also check the media dir copy
        /** @var MediaStorageAdapter $mediaStorage */
        $mediaStorage = self::getContainer()->get(MediaStorageAdapter::class);
        $storagePath = $mediaStorage->getLocalPath('edge-oversized.png');

        $sizeAfter = file_exists($storagePath) ? getimagesize($storagePath) : getimagesize($imgPath);

        self::assertNotFalse($sizeAfter);
        self::assertLessThanOrEqual(1980, $sizeAfter[0], 'Width should be <= 1980');
        self::assertLessThanOrEqual(1280, $sizeAfter[1], 'Height should be <= 1280');

        // Cleanup
        $media = $this->em->getRepository(Media::class)->findOneBy(['fileName' => 'edge-oversized.png']);
        if ($media instanceof Media) {
            $this->em->remove($media);
            $this->em->flush();
        }

        @unlink($storagePath);
    }

    public function testRenamedFileInMediaDirDetectedByHash(): void
    {
        $mediaDir = $this->getMediaDir();
        $projectDir = $this->getProjectDir();

        // Create original media
        $content = 'content for rename detection test '.uniqid();
        $originalPath = $mediaDir.'/edge-rename-original.txt';
        file_put_contents($originalPath, $content);
        $this->tempFiles[] = $originalPath;

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName('edge-rename-original.txt');
        $media->setAlt('Rename Detection');
        $media->setMimeType('text/plain');
        $media->setSize(\strlen($content));
        $media->setStoreIn($mediaDir);
        $media->setHash((string) sha1_file($originalPath, true));

        $this->em->persist($media);
        $this->em->flush();

        $mediaId = $media->id;

        // Export to create CSV
        /** @var MediaExporter $exporter */
        $exporter = self::getContainer()->get(MediaExporter::class);
        $exporter->exportMedias();

        // Simulate rename on disk: delete original, create with new name
        $renamedPath = $mediaDir.'/edge-rename-new.txt';
        copy($originalPath, $renamedPath);
        unlink($originalPath);
        $this->tempFiles[] = $renamedPath;

        // Import — hash-based detection should match the renamed file to existing entity
        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->mediaDir = $mediaDir;
        $importer->projectDir = $projectDir;
        $importer->resetIndex();
        $importer->loadIndexFromStorage();

        // Validate files exist — this will flag original as missing
        $importer->validateFilesExist($mediaDir);

        $missingFiles = $importer->getMissingFiles();
        self::assertContains('edge-rename-original.txt', $missingFiles, 'Original file should be flagged as missing');

        // Import the renamed file — hash match should update existing entity
        $result = $importer->importMedia($renamedPath, new DateTime());
        $importer->finishImport();

        // The existing entity should have been updated (not a new one created)
        $this->em->clear();
        $existingMedia = $this->em->getRepository(Media::class)->find($mediaId);

        // Either: the entity was updated with new filename, or a new entity was created
        // With hash-based detection, it should be the former
        if ($existingMedia instanceof Media) {
            // Hash-based detection worked: same entity, updated filename
            self::assertSame('edge-rename-new.txt', $existingMedia->getFileName(), 'Existing entity should have updated filename via hash detection');
        } else {
            // Fallback: check that a media with new name exists
            $newMedia = $this->em->getRepository(Media::class)->findOneBy(['fileName' => 'edge-rename-new.txt']);
            self::assertInstanceOf(Media::class, $newMedia, 'At minimum, a media with the new filename should exist');
        }

        // Cleanup
        $allTestMedia = $this->em->getRepository(Media::class)->findBy([]);
        foreach ($allTestMedia as $m) {
            if (str_starts_with($m->getFileName(), 'edge-rename-')) {
                $this->em->remove($m);
            }
        }

        $this->em->flush();
    }
}
