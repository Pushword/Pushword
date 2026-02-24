<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests;

use DateTime;
use Doctrine\ORM\EntityManager;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ThumbnailGenerator;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\Exporter\MediaExporter;
use Pushword\Flat\Importer\MediaImporter;
use RuntimeException;

use function Safe\sha1_file;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

#[Group('integration')]
final class MediaExportImportTest extends KernelTestCase
{
    private Filesystem $filesystem;

    private string $testMediaDir;

    /** @var string[] temp file paths to clean up */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->filesystem = new Filesystem();
        $this->testMediaDir = self::getContainer()->getParameter('kernel.cache_dir').'/test-media';
        $this->filesystem->mkdir($this->testMediaDir);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->filesystem->remove($this->testMediaDir);
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function testExportMediaWithLocalizedAlts(): void
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        // Create the image file first
        $this->createTestImage($mediaDir.'/test-export.png');

        // Create a media with localized alts
        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName('test-export.png');
        $media->setAlt('Test Image');
        $media->setAlts(Yaml::dump(['fr' => 'Image de test', 'en' => 'Test Image EN']));
        $media->setMimeType('image/png');
        $media->setSize(1024);
        $media->setStoreIn($mediaDir);

        $em->persist($media);
        $em->flush();

        /** @var MediaExporter $exporter */
        $exporter = self::getContainer()->get(MediaExporter::class);
        $exporter->csvDir = $this->testMediaDir;
        $exporter->exportMedias();

        // Check CSV was created with alt_* columns
        $csvPath = $this->testMediaDir.'/'.MediaExporter::CSV_FILE;
        self::assertFileExists($csvPath);

        $csvContent = file_get_contents($csvPath);
        self::assertIsString($csvContent);
        self::assertStringContainsString('alt_fr', $csvContent);
        self::assertStringContainsString('alt_en', $csvContent);
        self::assertStringContainsString('Image de test', $csvContent);
        self::assertStringContainsString('Test Image EN', $csvContent);

        // Cleanup
        $em->remove($media);
        $em->flush();
        @unlink($mediaDir.'/test-export.png');
        @unlink($csvPath);
    }

    public function testImportEmptyAltLocaleDoesNotCreateAltsEntry(): void
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        // Create a CSV with empty alt_en column
        $csvContent = <<<'CSV'
fileName,alt,width,height,ratio,alt_en,alt_fr
test-import.png,"Test Import Image",,,,"","Image FR"
CSV;

        $this->filesystem->dumpFile($this->testMediaDir.'/'.MediaExporter::CSV_FILE, $csvContent);

        // Create a dummy image file
        $this->createTestImage($this->testMediaDir.'/test-import.png');

        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->mediaDir = $this->testMediaDir;
        $importer->projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $importer->resetIndex();
        $importer->loadIndex($this->testMediaDir.'/'.MediaExporter::CSV_FILE);

        $lastEdit = new DateTime();
        $importer->import($this->testMediaDir.'/test-import.png', $lastEdit);
        $importer->finishImport();

        // Find the imported media
        $media = $em->getRepository(Media::class)->findOneBy(['fileName' => 'test-import.png']);
        self::assertInstanceOf(Media::class, $media);

        // Check that alts contains 'fr' but not 'en'
        $alts = $media->getAltsParsed();
        self::assertArrayHasKey('fr', $alts);
        self::assertSame('Image FR', $alts['fr']);
        self::assertArrayNotHasKey('en', $alts, 'Empty alt_en should not create an entry in alts');

        // Cleanup
        $em->remove($media);
        $em->flush();
    }

    public function testImportOnlyNonEmptyAltLocales(): void
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        // Create a CSV with mixed empty and non-empty alt columns
        $csvContent = <<<'CSV'
fileName,alt,width,height,ratio,alt_de,alt_en,alt_es,alt_fr
test-mixed.png,"Mixed Test","","","","German Alt","","Spanish Alt",""
CSV;

        $this->filesystem->dumpFile($this->testMediaDir.'/'.MediaExporter::CSV_FILE, $csvContent);

        // Create a dummy image file
        $this->createTestImage($this->testMediaDir.'/test-mixed.png');

        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->mediaDir = $this->testMediaDir;
        $importer->projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $importer->resetIndex();
        $importer->loadIndex($this->testMediaDir.'/'.MediaExporter::CSV_FILE);

        $lastEdit = new DateTime();
        $importer->import($this->testMediaDir.'/test-mixed.png', $lastEdit);
        $importer->finishImport();

        // Find the imported media
        $media = $em->getRepository(Media::class)->findOneBy(['fileName' => 'test-mixed.png']);
        self::assertInstanceOf(Media::class, $media);

        // Check alts - only 'de' and 'es' should exist
        $alts = $media->getAltsParsed();
        self::assertArrayHasKey('de', $alts);
        self::assertSame('German Alt', $alts['de']);
        self::assertArrayHasKey('es', $alts);
        self::assertSame('Spanish Alt', $alts['es']);
        self::assertArrayNotHasKey('en', $alts, 'Empty alt_en should not be in alts');
        self::assertArrayNotHasKey('fr', $alts, 'Empty alt_fr should not be in alts');

        // Cleanup
        $em->remove($media);
        $em->flush();
    }

    public function testHashComparisonForNonImageMedia(): void
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        // Create a test PDF file
        $pdfPath = $mediaDir.'/test-hash.pdf';
        $pdfContent = '%PDF-1.4 test content v1';
        file_put_contents($pdfPath, $pdfContent);

        // Create media in DB with hash
        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName('test-hash.pdf');
        $media->setAlt('Test PDF');
        $media->setMimeType('application/pdf');
        $media->setSize(\strlen($pdfContent));
        $media->setStoreIn($mediaDir);
        $media->setHash(sha1_file($pdfPath, true));

        $em->persist($media);
        $em->flush();

        $originalHash = $media->getHash();
        self::assertNotNull($originalHash);

        // Create index.csv with the media's ID so importer can find it
        $indexCsv = "id,fileName,alt\n{$media->id},test-hash.pdf,Test PDF\n";
        file_put_contents($this->testMediaDir.'/'.MediaExporter::CSV_FILE, $indexCsv);

        // Import same file - should be skipped (hash matches)
        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->mediaDir = $mediaDir;
        $importer->projectDir = $projectDir;
        $importer->resetIndex();
        $importer->loadIndex($this->testMediaDir.'/'.MediaExporter::CSV_FILE);

        $imported = $importer->importMedia($pdfPath, new DateTime());
        self::assertFalse($imported, 'Same file content should be skipped');
        self::assertSame(1, $importer->getSkippedCount());

        // Modify file content
        $newContent = '%PDF-1.4 test content v2 - modified';
        file_put_contents($pdfPath, $newContent);

        // Import modified file - should be imported (hash differs)
        $importer->resetIndex();
        $importer->loadIndex($this->testMediaDir.'/'.MediaExporter::CSV_FILE);

        $imported = $importer->importMedia($pdfPath, new DateTime());
        self::assertTrue($imported, 'Modified file should be imported');
        self::assertSame(1, $importer->getImportedCount());

        // Cleanup
        $em->remove($media);
        $em->flush();
        @unlink($pdfPath);
        @unlink($this->testMediaDir.'/'.MediaExporter::CSV_FILE);
    }

    public function testHashComparisonPreventsReimportOnSubsequentSync(): void
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        // Create test file
        $docPath = $mediaDir.'/test-no-reimport.txt';
        $content = 'Test document content';
        file_put_contents($docPath, $content);

        // Create media with matching hash
        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName('test-no-reimport.txt');
        $media->setAlt('Test Doc');
        $media->setMimeType('text/plain');
        $media->setSize(\strlen($content));
        $media->setStoreIn($mediaDir);
        $media->setHash(sha1_file($docPath, true));

        $em->persist($media);
        $em->flush();

        // Create index.csv with the media's ID
        $indexCsv = "id,fileName,alt\n{$media->id},test-no-reimport.txt,Test Doc\n";
        file_put_contents($this->testMediaDir.'/'.MediaExporter::CSV_FILE, $indexCsv);

        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->mediaDir = $mediaDir;
        $importer->projectDir = $projectDir;

        // First sync - should skip (hash matches)
        $importer->resetIndex();
        $importer->loadIndex($this->testMediaDir.'/'.MediaExporter::CSV_FILE);

        $imported1 = $importer->importMedia($docPath, new DateTime());
        self::assertFalse($imported1, 'First sync should skip unchanged file');
        self::assertSame(1, $importer->getSkippedCount(), 'First sync skipped count');

        // Second sync - should still skip
        $importer->resetIndex();
        $importer->loadIndex($this->testMediaDir.'/'.MediaExporter::CSV_FILE);

        $imported2 = $importer->importMedia($docPath, new DateTime());
        self::assertFalse($imported2, 'Second sync should also skip unchanged file');
        self::assertSame(1, $importer->getSkippedCount(), 'Second sync skipped count');

        // Third sync - should still skip
        $importer->resetIndex();
        $importer->loadIndex($this->testMediaDir.'/'.MediaExporter::CSV_FILE);

        $imported3 = $importer->importMedia($docPath, new DateTime());
        self::assertFalse($imported3, 'Third sync should also skip unchanged file');
        self::assertSame(1, $importer->getSkippedCount(), 'Third sync skipped count');
        self::assertSame(0, $importer->getImportedCount(), 'No imports should occur');

        // Cleanup
        $em->remove($media);
        $em->flush();
        @unlink($docPath);
        @unlink($this->testMediaDir.'/'.MediaExporter::CSV_FILE);
    }

    public function testLoadIndexFromLocalCsvFile(): void
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        /** @var MediaStorageAdapter $mediaStorage */
        $mediaStorage = self::getContainer()->get(MediaStorageAdapter::class);

        // Write media.csv to local filesystem
        $csvContent = "fileName,alt,alt_fr\ntest-storage.png,\"Storage Test\",\"Test FR\"\n";
        $csvPath = $this->testMediaDir.'/'.MediaExporter::CSV_FILE;
        $this->filesystem->dumpFile($csvPath, $csvContent);

        // Create the image file in storage
        $this->createTestImage($mediaDir.'/test-storage.png');

        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->resetIndex();
        $importer->loadIndex($csvPath);

        self::assertTrue($importer->hasIndexData(), 'Index should be loaded from local CSV');

        // Import the file using importFromStorage
        $lastModified = $mediaStorage->lastModified('test-storage.png');
        $lastEdit = new DateTime()->setTimestamp($lastModified);
        $imported = $importer->importFromStorage('test-storage.png', $lastEdit);
        $importer->finishImport();

        self::assertTrue($imported, 'File should be imported from storage');

        // Verify media was created with correct data
        $media = $em->getRepository(Media::class)->findOneBy(['fileName' => 'test-storage.png']);
        self::assertInstanceOf(Media::class, $media);
        self::assertSame('Storage Test', $media->getAlt());

        $alts = $media->getAltsParsed();
        self::assertArrayHasKey('fr', $alts);
        self::assertSame('Test FR', $alts['fr']);

        // Cleanup
        $em->remove($media);
        $em->flush();
        @unlink($mediaDir.'/test-storage.png');
    }

    // --- In-memory Flysystem tests ---

    public function testExportWritesIndexCsvThroughInMemoryFlysystem(): void
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        $memStorage = $this->createInMemoryStorage($mediaDir);

        // Create the image file on disk (needed for MediaHashListener on persist)
        $this->createTestImage($mediaDir.'/test-mem-export.png');

        // Write the image into in-memory storage too (needed for exporter's fileExists check)
        $memStorage->write('test-mem-export.png', (string) file_get_contents($mediaDir.'/test-mem-export.png'));

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName('test-mem-export.png');
        $media->setAlt('Memory Export Test');
        $media->setMimeType('image/png');
        $media->setSize(1024);
        $media->setStoreIn($mediaDir);

        $em->persist($media);
        $em->flush();

        $exporter = $this->createExporterWithStorage($memStorage);
        $exporter->csvDir = $this->testMediaDir;
        $exporter->exportMedias();

        // Verify media.csv was written to local filesystem
        $csvPath = $this->testMediaDir.'/'.MediaExporter::CSV_FILE;
        self::assertFileExists($csvPath);
        $csvContent = (string) file_get_contents($csvPath);
        self::assertStringContainsString('test-mem-export.png', $csvContent);
        self::assertStringContainsString('Memory Export Test', $csvContent);

        // Cleanup
        $em->remove($media);
        $em->flush();
        @unlink($mediaDir.'/test-mem-export.png');
    }

    public function testImportFromStorageWithInMemoryFlysystem(): void
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        $this->filesystem->mkdir($mediaDir);

        // Use isLocal=false to test remote storage path (temp file download)
        $memStorage = $this->createInMemoryStorage($mediaDir, false);

        // Create a PNG and write it into in-memory storage
        $this->createTestImage($this->testMediaDir.'/test-mem-import.png');
        $pngBytes = (string) file_get_contents($this->testMediaDir.'/test-mem-import.png');
        $memStorage->write('test-mem-import.png', $pngBytes);

        // Write media.csv to local filesystem
        $csvContent = "fileName,alt\ntest-mem-import.png,\"In-Memory Import\"\n";
        $csvPath = $this->testMediaDir.'/'.MediaExporter::CSV_FILE;
        $this->filesystem->dumpFile($csvPath, $csvContent);

        $importer = $this->createImporterWithStorage($memStorage, $mediaDir, $projectDir);
        $importer->resetIndex();
        $importer->loadIndex($csvPath);

        self::assertTrue($importer->hasIndexData());

        // Create file at container's mediaDir so MediaHashListener can hash it on persist()
        $this->createTestImage($mediaDir.'/test-mem-import.png');

        $imported = $importer->importFromStorage('test-mem-import.png', new DateTime());
        $importer->finishImport();

        self::assertTrue($imported, 'File should be imported from in-memory storage');

        $media = $em->getRepository(Media::class)->findOneBy(['fileName' => 'test-mem-import.png']);
        self::assertInstanceOf(Media::class, $media);
        self::assertSame('In-Memory Import', $media->getAlt());

        // Track temp file for cleanup
        $tempPath = sys_get_temp_dir().'/'.sha1('test-mem-import.png').'_test-mem-import.png';
        $this->tempFiles[] = $tempPath;

        // Cleanup (preRemove listener deletes file from container storage)
        $em->remove($media);
        $em->flush();
    }

    public function testExportImportRoundTripThroughInMemoryStorage(): void
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        $memStorage = $this->createInMemoryStorage($mediaDir);

        // Create image file on disk (needed for MediaHashListener) and in-memory storage
        $this->createTestImage($mediaDir.'/test-roundtrip.png');
        $memStorage->write('test-roundtrip.png', (string) file_get_contents($mediaDir.'/test-roundtrip.png'));

        // Create media entity
        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName('test-roundtrip.png');
        $media->setAlt('Roundtrip Test');
        $media->setAlts(Yaml::dump(['fr' => 'Test aller-retour']));
        $media->setMimeType('image/png');
        $media->setSize(1024);
        $media->setStoreIn($mediaDir);

        $em->persist($media);
        $em->flush();

        $mediaId = $media->id;

        // Export to local filesystem
        $exporter = $this->createExporterWithStorage($memStorage);
        $exporter->csvDir = $this->testMediaDir;
        $exporter->exportMedias();

        $csvPath = $this->testMediaDir.'/'.MediaExporter::CSV_FILE;
        self::assertFileExists($csvPath);

        // Remove from DB (preRemove listener deletes the file from container storage)
        $em->remove($media);
        $em->flush();
        $em->clear();

        // Verify it's gone
        self::assertNull($em->getRepository(Media::class)->find($mediaId));

        // Recreate file on disk (preRemove deleted it) for MediaHashListener + metadata reads
        $this->createTestImage($mediaDir.'/test-roundtrip.png');

        // Import back from local CSV
        $importer = $this->createImporterWithStorage($memStorage, $mediaDir, $projectDir);
        $importer->resetIndex();
        $importer->loadIndex($csvPath);

        self::assertTrue($importer->hasIndexData());

        $imported = $importer->importFromStorage('test-roundtrip.png', new DateTime());
        $importer->finishImport();

        self::assertTrue($imported);

        // Verify media was recreated
        $reimported = $em->getRepository(Media::class)->findOneBy(['fileName' => 'test-roundtrip.png']);
        self::assertInstanceOf(Media::class, $reimported);
        self::assertSame('Roundtrip Test', $reimported->getAlt());

        $alts = $reimported->getAltsParsed();
        self::assertArrayHasKey('fr', $alts);
        self::assertSame('Test aller-retour', $alts['fr']);

        // Cleanup
        $em->remove($reimported);
        $em->flush();
        @unlink($mediaDir.'/test-roundtrip.png');
    }

    public function testPrepareFileRenamesViaInMemoryStorage(): void
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        $memStorage = $this->createInMemoryStorage($mediaDir);

        // Create file on disk so MediaHashListener can hash it on persist
        file_put_contents($mediaDir.'/old-name.pdf', 'PDF content');

        // Create media entity with old filename
        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName('old-name.pdf');
        $media->setAlt('Rename Test');
        $media->setMimeType('application/pdf');
        $media->setSize(11);
        $media->setStoreIn($mediaDir);

        $em->persist($media);
        $em->flush();

        $mediaId = $media->id;

        // Write file with old name into in-memory storage
        $memStorage->write('old-name.pdf', 'PDF content');

        // Load index CSV with new name for the same ID
        $csvContent = "id,fileName,alt\n{$mediaId},new-name.pdf,Rename Test\n";
        $csvPath = $this->testMediaDir.'/'.MediaExporter::CSV_FILE;
        $this->filesystem->dumpFile($csvPath, $csvContent);

        $importer = $this->createImporterWithStorage($memStorage, $mediaDir, $projectDir);
        $importer->resetIndex();
        $importer->loadIndex($csvPath);

        // Execute rename via Flysystem move()
        $importer->prepareFileRenames($this->testMediaDir);

        // Verify: old file gone, new file exists in in-memory storage
        self::assertFalse($memStorage->fileExists('old-name.pdf'));
        self::assertTrue($memStorage->fileExists('new-name.pdf'));
        self::assertSame('PDF content', $memStorage->read('new-name.pdf'));

        // Cleanup
        $em->remove($media);
        $em->flush();
        @unlink($mediaDir.'/old-name.pdf');
    }

    public function testPrepareFileRenamesCollisionThrowsException(): void
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        $memStorage = $this->createInMemoryStorage($mediaDir);

        // Create file on disk so MediaHashListener can hash it on persist
        file_put_contents($mediaDir.'/source.pdf', 'Source content');

        // Create media entity
        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName('source.pdf');
        $media->setAlt('Collision Test');
        $media->setMimeType('application/pdf');
        $media->setSize(14);
        $media->setStoreIn($mediaDir);

        $em->persist($media);
        $em->flush();

        $mediaId = $media->id;

        // Write both source and target files into in-memory storage
        $memStorage->write('source.pdf', 'Source content');
        $memStorage->write('target.pdf', 'Existing target content');

        // Index CSV renames source -> target (collision)
        $csvContent = "id,fileName,alt\n{$mediaId},target.pdf,Collision Test\n";
        $csvPath = $this->testMediaDir.'/'.MediaExporter::CSV_FILE;
        $this->filesystem->dumpFile($csvPath, $csvContent);

        $importer = $this->createImporterWithStorage($memStorage, $mediaDir, $projectDir);
        $importer->resetIndex();
        $importer->loadIndex($csvPath);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/target file already exists/');

            $importer->prepareFileRenames($this->testMediaDir);
        } finally {
            // Cleanup even if exception is thrown
            $em->remove($media);
            $em->flush();
            @unlink($mediaDir.'/source.pdf');
        }
    }

    public function testValidateFilesExistChecksInMemoryStorage(): void
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        $memStorage = $this->createInMemoryStorage($mediaDir);

        // Create files on disk so MediaHashListener can hash them on persist
        file_put_contents($mediaDir.'/exists-in-storage.pdf', 'PDF content');
        file_put_contents($mediaDir.'/missing-everywhere.pdf', 'PDF content');

        // Create two media entities
        $media1 = new Media();
        $media1->setProjectDir($projectDir);
        $media1->setFileName('exists-in-storage.pdf');
        $media1->setAlt('Exists');
        $media1->setMimeType('application/pdf');
        $media1->setSize(11);
        $media1->setStoreIn($mediaDir);

        $media2 = new Media();
        $media2->setProjectDir($projectDir);
        $media2->setFileName('missing-everywhere.pdf');
        $media2->setAlt('Missing');
        $media2->setMimeType('application/pdf');
        $media2->setSize(11);
        $media2->setStoreIn($mediaDir);

        $em->persist($media1);
        $em->persist($media2);
        $em->flush();

        // Put file in in-memory storage for media1 only
        $memStorage->write('exists-in-storage.pdf', 'PDF content');

        // Index CSV references both files
        $csvContent = "id,fileName,alt\n{$media1->id},exists-in-storage.pdf,Exists\n{$media2->id},missing-everywhere.pdf,Missing\n";
        $csvPath = $this->testMediaDir.'/'.MediaExporter::CSV_FILE;
        $this->filesystem->dumpFile($csvPath, $csvContent);

        // Use a non-existent flat dir so local filesystem check fails for both
        $nonExistentDir = sys_get_temp_dir().'/pushword-no-flat-'.uniqid();

        $importer = $this->createImporterWithStorage($memStorage, $mediaDir, $projectDir);
        $importer->resetIndex();
        $importer->loadIndex($csvPath);

        $importer->validateFilesExist($nonExistentDir);

        // media1 should be valid (found in in-memory storage), media2 should be missing
        $missing = $importer->getMissingFiles();
        self::assertNotContains('exists-in-storage.pdf', $missing);
        self::assertContains('missing-everywhere.pdf', $missing);

        // Cleanup
        $em->remove($media1);
        $em->remove($media2);
        $em->flush();
        @unlink($mediaDir.'/exists-in-storage.pdf');
        @unlink($mediaDir.'/missing-everywhere.pdf');
    }

    public function testCopyToMediaDirWritesStreamToInMemoryStorage(): void
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        // Use a separate directory as mediaDir (isLocal=true) so realpath() differs from source
        $storageTargetDir = $this->testMediaDir.'/storage-target';
        $this->filesystem->mkdir($storageTargetDir);
        $memStorage = $this->createInMemoryStorage($storageTargetDir);

        // Create a local test file in source dir (different from storageTargetDir)
        $sourceDir = $this->testMediaDir.'/source';
        $this->filesystem->mkdir($sourceDir);
        $localFile = $sourceDir.'/test-stream.txt';
        file_put_contents($localFile, 'Stream test content');

        // Also place the file at storageTargetDir (getLocalPath returns this path for metadata reads)
        file_put_contents($storageTargetDir.'/test-stream.txt', 'Stream test content');

        // Place at container's mediaDir so MediaHashListener can hash it on persist()
        file_put_contents($mediaDir.'/test-stream.txt', 'Stream test content');

        // Write media.csv in source dir so import can proceed
        $csvContent = "fileName,alt\ntest-stream.txt,\"Stream Test\"\n";
        $csvPath = $sourceDir.'/'.MediaExporter::CSV_FILE;
        $this->filesystem->dumpFile($csvPath, $csvContent);

        $importer = $this->createImporterWithStorage($memStorage, $sourceDir, $projectDir);
        $importer->resetIndex();
        $importer->loadIndex($csvPath);

        // importMedia() calls copyToMediaDir() internally which calls writeStream()
        $imported = $importer->importMedia($localFile, new DateTime());
        $importer->finishImport();

        self::assertTrue($imported);

        // Verify the file was written to in-memory storage via writeStream
        self::assertTrue($memStorage->fileExists('test-stream.txt'));
        self::assertSame('Stream test content', $memStorage->read('test-stream.txt'));

        // Cleanup DB
        $media = $em->getRepository(Media::class)->findOneBy(['fileName' => 'test-stream.txt']);
        if ($media instanceof Media) {
            $em->remove($media);
            $em->flush();
        }

        @unlink($mediaDir.'/test-stream.txt');
    }

    public function testHashComparisonWithInMemoryRemoteStorage(): void
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        // isLocal=false to exercise temp file download path in getLocalPath()
        $memStorage = $this->createInMemoryStorage($mediaDir, false);

        // Create a file in in-memory storage
        $originalContent = 'Original file content for hash test';
        $memStorage->write('test-hash-remote.txt', $originalContent);

        // Create file on disk so MediaHashListener can hash it on persist
        file_put_contents($mediaDir.'/test-hash-remote.txt', $originalContent);

        // Create media in DB with matching hash
        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName('test-hash-remote.txt');
        $media->setAlt('Hash Remote Test');
        $media->setMimeType('text/plain');
        $media->setSize(\strlen($originalContent));
        $media->setStoreIn($mediaDir);

        $em->persist($media);
        $em->flush();

        $mediaId = $media->id;

        // Index CSV with the media's ID
        $csvContent = "id,fileName,alt\n{$mediaId},test-hash-remote.txt,Hash Remote Test\n";
        $csvPath = $this->testMediaDir.'/'.MediaExporter::CSV_FILE;
        $this->filesystem->dumpFile($csvPath, $csvContent);

        $importer = $this->createImporterWithStorage($memStorage, $mediaDir, $projectDir);
        $importer->resetIndex();
        $importer->loadIndex($csvPath);

        // Import same content - should be skipped (hash matches)
        $imported = $importer->importFromStorage('test-hash-remote.txt', new DateTime());
        self::assertFalse($imported, 'Same content should be skipped');
        self::assertSame(1, $importer->getSkippedCount());

        // Clean temp file so getLocalPath() re-downloads from in-memory storage
        $tempPath = sys_get_temp_dir().'/'.sha1('test-hash-remote.txt').'_test-hash-remote.txt';
        $this->tempFiles[] = $tempPath;
        @unlink($tempPath);

        // Modify content in in-memory storage
        $modifiedContent = 'Modified file content for hash test';
        $memStorage->write('test-hash-remote.txt', $modifiedContent);

        // Import again - should detect change via hash comparison
        $importer->resetIndex();
        $importer->loadIndex($csvPath);

        $imported = $importer->importFromStorage('test-hash-remote.txt', new DateTime());
        self::assertTrue($imported, 'Modified content should be imported');
        self::assertSame(1, $importer->getImportedCount());

        // Cleanup
        $em->remove($media);
        $em->flush();
        @unlink($mediaDir.'/test-hash-remote.txt');
    }

    public function testImportMediaCreatesPublicSymlinkForNonImageFiles(): void
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        $publicMediaDir = $projectDir.'/public/media';
        $this->filesystem->mkdir($publicMediaDir);

        // Create a test PDF file
        $pdfPath = $this->testMediaDir.'/test-symlink.pdf';
        file_put_contents($pdfPath, '%PDF-1.4 symlink test content');

        // Create index.csv
        $csvContent = "fileName,alt\ntest-symlink.pdf,\"Symlink Test PDF\"\n";
        $this->filesystem->dumpFile($this->testMediaDir.'/'.MediaExporter::CSV_FILE, $csvContent);

        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->mediaDir = $this->testMediaDir;
        $importer->projectDir = $projectDir;
        $importer->resetIndex();
        $importer->loadIndex($this->testMediaDir.'/'.MediaExporter::CSV_FILE);

        $imported = $importer->importMedia($pdfPath, new DateTime());
        $importer->finishImport();

        self::assertTrue($imported, 'PDF should be imported');

        // Verify symlink was created in public/media/
        $publicPath = $publicMediaDir.'/test-symlink.pdf';
        self::assertTrue(is_link($publicPath) || file_exists($publicPath), 'Public symlink should exist for non-image media after flat import');

        // Cleanup
        $media = $em->getRepository(Media::class)->findOneBy(['fileName' => 'test-symlink.pdf']);
        if ($media instanceof Media) {
            $em->remove($media);
            $em->flush();
        }

        @unlink($publicPath);
        @unlink($mediaDir.'/test-symlink.pdf');
    }

    // --- Helper methods ---

    private function createTestImage(string $path): void
    {
        // Create a minimal valid PNG with unique pixel color to avoid hash collisions
        $img = imagecreatetruecolor(1, 1);
        if (false !== $img) {
            $color = imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagesetpixel($img, 0, 0, (int) $color);
            imagepng($img, $path);
            imagedestroy($img);
        }
    }

    private function createInMemoryStorage(string $mediaDir, bool $isLocal = true): MediaStorageAdapter
    {
        $flysystem = new Flysystem(new InMemoryFilesystemAdapter());

        return new MediaStorageAdapter($flysystem, $mediaDir, $isLocal);
    }

    private function createImporterWithStorage(MediaStorageAdapter $storage, string $mediaDir, string $projectDir): MediaImporter
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        /** @var SiteRegistry $apps */
        $apps = self::getContainer()->get(SiteRegistry::class);

        /** @var ThumbnailGenerator $thumbnailGenerator */
        $thumbnailGenerator = self::getContainer()->get(ThumbnailGenerator::class);

        /** @var ImageCacheManager $imageCacheManager */
        $imageCacheManager = self::getContainer()->get(ImageCacheManager::class);

        return new MediaImporter($em, $apps, $mediaDir, $projectDir, $storage, $thumbnailGenerator, $imageCacheManager);
    }

    private function createExporterWithStorage(MediaStorageAdapter $storage): MediaExporter
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return new MediaExporter($em->getRepository(Media::class), $storage);
    }
}
