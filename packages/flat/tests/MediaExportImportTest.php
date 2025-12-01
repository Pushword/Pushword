<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests;

use DateTime;
use Doctrine\ORM\EntityManager;
use Override;
use Pushword\Core\Entity\Media;
use Pushword\Flat\Exporter\MediaExporter;
use Pushword\Flat\Importer\MediaImporter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

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

    private function createTestImage(string $path): void
    {
        // Create a minimal valid PNG image (1x1 pixel)
        $img = imagecreatetruecolor(1, 1);
        if (false !== $img) {
            imagepng($img, $path);
            imagedestroy($img);
        }
    }
}
