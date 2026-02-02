<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests;

use DateTime;
use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Flat\Exporter\MediaExporter;
use Pushword\Flat\Importer\MediaImporter;

use function Safe\sha1_file;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

#[Group('integration')]
final class MediaExportImportTest extends KernelTestCase
{
    private Filesystem $filesystem;

    private string $testMediaDir;

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
        $exporter->exportMedias();

        // Check CSV was created with alt_* columns
        $csvPath = $mediaDir.'/index.csv';
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

        $this->filesystem->dumpFile($this->testMediaDir.'/index.csv', $csvContent);

        // Create a dummy image file
        $this->createTestImage($this->testMediaDir.'/test-import.png');

        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->mediaDir = $this->testMediaDir;
        $importer->projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $importer->resetIndex();
        $importer->loadIndex($this->testMediaDir);

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

        $this->filesystem->dumpFile($this->testMediaDir.'/index.csv', $csvContent);

        // Create a dummy image file
        $this->createTestImage($this->testMediaDir.'/test-mixed.png');

        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->mediaDir = $this->testMediaDir;
        $importer->projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $importer->resetIndex();
        $importer->loadIndex($this->testMediaDir);

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
        file_put_contents($mediaDir.'/index.csv', $indexCsv);

        // Import same file - should be skipped (hash matches)
        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->mediaDir = $mediaDir;
        $importer->projectDir = $projectDir;
        $importer->resetIndex();
        $importer->loadIndex($mediaDir);

        $imported = $importer->importMedia($pdfPath, new DateTime());
        self::assertFalse($imported, 'Same file content should be skipped');
        self::assertSame(1, $importer->getSkippedCount());

        // Modify file content
        $newContent = '%PDF-1.4 test content v2 - modified';
        file_put_contents($pdfPath, $newContent);

        // Import modified file - should be imported (hash differs)
        $importer->resetIndex();
        $importer->loadIndex($mediaDir);

        $imported = $importer->importMedia($pdfPath, new DateTime());
        self::assertTrue($imported, 'Modified file should be imported');
        self::assertSame(1, $importer->getImportedCount());

        // Cleanup
        $em->remove($media);
        $em->flush();
        @unlink($pdfPath);
        @unlink($mediaDir.'/index.csv');
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
        file_put_contents($mediaDir.'/index.csv', $indexCsv);

        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->mediaDir = $mediaDir;
        $importer->projectDir = $projectDir;

        // First sync - should skip (hash matches)
        $importer->resetIndex();
        $importer->loadIndex($mediaDir);

        $imported1 = $importer->importMedia($docPath, new DateTime());
        self::assertFalse($imported1, 'First sync should skip unchanged file');
        self::assertSame(1, $importer->getSkippedCount(), 'First sync skipped count');

        // Second sync - should still skip
        $importer->resetIndex();
        $importer->loadIndex($mediaDir);

        $imported2 = $importer->importMedia($docPath, new DateTime());
        self::assertFalse($imported2, 'Second sync should also skip unchanged file');
        self::assertSame(1, $importer->getSkippedCount(), 'Second sync skipped count');

        // Third sync - should still skip
        $importer->resetIndex();
        $importer->loadIndex($mediaDir);

        $imported3 = $importer->importMedia($docPath, new DateTime());
        self::assertFalse($imported3, 'Third sync should also skip unchanged file');
        self::assertSame(1, $importer->getSkippedCount(), 'Third sync skipped count');
        self::assertSame(0, $importer->getImportedCount(), 'No imports should occur');

        // Cleanup
        $em->remove($media);
        $em->flush();
        @unlink($docPath);
        @unlink($mediaDir.'/index.csv');
    }

    private function createTestImage(string $path): void
    {
        // Create a minimal valid PNG image (1x1 pixel)
        $img = imagecreatetruecolor(1, 1);
        if (false !== $img) {
            imagepng($img, $path);
            unset($img);
        }
    }
}
