<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Sync;

use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Flat\Exporter\MediaExporter;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Importer\MediaImporter;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class MediaRenameRoundTripTest extends KernelTestCase
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

    public function testMediaRenameViaCsvRoundTrip(): void
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        // Create media file and entity
        $oldPath = $mediaDir.'/rename-old.txt';
        file_put_contents($oldPath, 'content to rename');
        $this->tempFiles[] = $oldPath;

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName('rename-old.txt');
        $media->setAlt('Rename Test');
        $media->setMimeType('text/plain');
        $media->setSize(17);
        $media->setStoreIn($mediaDir);
        $media->setHash((string) sha1_file($oldPath, true));

        $this->em->persist($media);
        $this->em->flush();

        $mediaId = $media->id;

        // Change fileName in CSV (same ID) → simulate user renaming in CSV
        $csvContent = "id,fileName,alt,tags\n{$mediaId},rename-new.txt,Rename Test,\n";
        new Filesystem()->dumpFile($this->getMediaCsvPath(), $csvContent);

        // Import: should rename the file
        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->resetIndex();
        $importer->loadIndex($this->getMediaCsvPath());
        $importer->prepareFileRenames($mediaDir);

        // Verify file was renamed in storage
        /** @var MediaStorageAdapter $mediaStorage */
        $mediaStorage = self::getContainer()->get(MediaStorageAdapter::class);
        self::assertTrue($mediaStorage->fileExists('rename-new.txt'), 'File should be renamed in storage');
        $this->tempFiles[] = $mediaDir.'/rename-new.txt';

        // Cleanup
        $this->em->clear();
        $media = $this->em->getRepository(Media::class)->find($mediaId);
        if ($media instanceof Media) {
            $this->em->remove($media);
            $this->em->flush();
        }
    }

    public function testMediaRenameCollisionThrowsException(): void
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        // Create two files
        $path1 = $mediaDir.'/collision-source.txt';
        $path2 = $mediaDir.'/collision-target.txt';
        file_put_contents($path1, 'source');
        file_put_contents($path2, 'target');
        $this->tempFiles[] = $path1;
        $this->tempFiles[] = $path2;

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName('collision-source.txt');
        $media->setAlt('Collision Source');
        $media->setMimeType('text/plain');
        $media->setSize(6);
        $media->setStoreIn($mediaDir);
        $media->setHash((string) sha1_file($path1, true));

        $this->em->persist($media);
        $this->em->flush();

        $mediaId = $media->id;

        // Try to rename to existing filename
        $csvContent = "id,fileName,alt,tags\n{$mediaId},collision-target.txt,Collision Source,\n";
        new Filesystem()->dumpFile($this->getMediaCsvPath(), $csvContent);

        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->resetIndex();
        $importer->loadIndex($this->getMediaCsvPath());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('target file already exists');
        $importer->prepareFileRenames($mediaDir);

        // Cleanup (won't reach here on success, but just in case)
        $this->em->remove($media);
        $this->em->flush();
    }

    public function testMediaRenamePreservesFileNameHistory(): void
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        // Create media with a history
        $path = $mediaDir.'/history-current.txt';
        file_put_contents($path, 'content with history');
        $this->tempFiles[] = $path;

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName('history-current.txt');
        $media->setAlt('History Test');
        $media->setMimeType('text/plain');
        $media->setSize(20);
        $media->setStoreIn($mediaDir);
        $media->setHash((string) sha1_file($path, true));
        $media->setFileNameHistory(['history-original.txt']);

        $this->em->persist($media);
        $this->em->flush();

        $mediaId = $media->id;

        // Export to get CSV with fileNameHistory
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);

        /** @var MediaExporter $exporter */
        $exporter = self::getContainer()->get(MediaExporter::class);
        $exporter->csvDir = $contentDirFinder->getBaseDir();
        $exporter->exportMedias();

        // Read CSV and verify fileNameHistory is present
        $csvContent = (string) file_get_contents($this->getMediaCsvPath());

        self::assertStringContainsString('fileNameHistory', $csvContent);
        self::assertStringContainsString('history-original.txt', $csvContent);

        // Cleanup
        $this->em->remove($media);
        $this->em->flush();
    }
}
