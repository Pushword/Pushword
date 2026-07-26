<?php

namespace Pushword\Flat\Tests\Importer;

use DateTime;
use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Flat\Exporter\MediaExporter;
use Pushword\Flat\Importer\MediaImporter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * media.csv is the only place a human edits media metadata in a flat-file setup, and
 * the file it describes stays byte-identical while they do it. The content hash alone
 * therefore cannot decide whether there is something to import.
 */
#[Group('integration')]
final class MediaMetadataImportTest extends KernelTestCase
{
    private const string FILE_NAME = 'metadata-untouched.txt';

    private EntityManager $em;

    private Filesystem $filesystem;

    private string $csvPath;

    private string $filePath;

    private Media $media;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->filesystem = new Filesystem();

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        /** @var string $cacheDir */
        $cacheDir = self::getContainer()->getParameter('kernel.cache_dir');
        $this->csvPath = $cacheDir.'/test-media-metadata/'.MediaExporter::CSV_FILE;

        $this->filePath = $this->getMediaDir().'/'.self::FILE_NAME;
        $this->filesystem->dumpFile($this->filePath, 'content that never changes');

        $this->media = new Media();
        $this->media
            ->setProjectDir($this->getProjectDir())
            ->setStoreIn($this->getMediaDir())
            ->setFileName(self::FILE_NAME)
            ->setAlt('Base alt')
            ->setMimeType('text/plain')
            ->setSize((int) filesize($this->filePath))
            ->setHash((string) sha1_file($this->filePath, true));

        $this->em->persist($this->media);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $media = $this->em->getRepository(Media::class)->findOneBy(['fileName' => self::FILE_NAME]);
        if ($media instanceof Media) {
            $this->em->remove($media);
            $this->em->flush();
        }

        $this->filesystem->remove(\dirname($this->csvPath));
        @unlink($this->filePath);
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

    private function importerFor(string $csvContent): MediaImporter
    {
        $this->filesystem->dumpFile($this->csvPath, $csvContent);

        /** @var MediaImporter $importer */
        $importer = self::getContainer()->get(MediaImporter::class);
        $importer->mediaDir = $this->getMediaDir();
        $importer->projectDir = $this->getProjectDir();
        $importer->resetIndex();
        $importer->loadIndex($this->csvPath);

        return $importer;
    }

    public function testAltEditedInCsvReachesAnUnchangedFile(): void
    {
        $importer = $this->importerFor("fileName,alt,tags\n".self::FILE_NAME.",Nouvelle alt,\n");

        self::assertTrue($importer->importMedia($this->filePath, new DateTime()), 'the CSV row differs from the database');
        $importer->finishImport();

        self::assertSame('Nouvelle alt', $this->media->getAlt(true));
        self::assertSame(1, $importer->getImportedCount());
        self::assertSame(0, $importer->getSkippedCount());
    }

    public function testLocalizedAltEditedInCsvReachesAnUnchangedFile(): void
    {
        $this->media->setAlts(Yaml::dump(['fr' => 'Ancienne alt FR']));
        $this->em->flush();

        // Only alt_fr differs: the base alt is the one already in database.
        $importer = $this->importerFor("fileName,alt,alt_fr\n".self::FILE_NAME.",Base alt,Nouvelle alt FR\n");

        self::assertTrue($importer->importMedia($this->filePath, new DateTime()));
        $importer->finishImport();

        self::assertSame(['fr' => 'Nouvelle alt FR'], $this->media->getAltsParsed());
    }

    public function testTagsEditedInCsvReachAnUnchangedFile(): void
    {
        $importer = $this->importerFor("fileName,alt,tags\n".self::FILE_NAME.",Base alt,photo montagne\n");

        self::assertTrue($importer->importMedia($this->filePath, new DateTime()));
        $importer->finishImport();

        // Tags are stored normalized and sorted, which is also what the exporter writes back.
        self::assertSame('montagne photo', trim($this->media->getTags()));
    }

    public function testCustomPropertyEditedInCsvReachesAnUnchangedFile(): void
    {
        $this->media->setCustomProperty('credit', 'Ancien credit');
        $this->em->flush();

        $importer = $this->importerFor("fileName,alt,credit\n".self::FILE_NAME.",Base alt,Nouveau credit\n");

        self::assertTrue($importer->importMedia($this->filePath, new DateTime()));
        $importer->finishImport();

        self::assertSame('Nouveau credit', $this->media->getCustomProperty('credit'));
    }

    public function testUnchangedCsvRowIsStillSkipped(): void
    {
        $importer = $this->importerFor("fileName,alt,tags\n".self::FILE_NAME.",Base alt,\n");

        self::assertFalse($importer->importMedia($this->filePath, new DateTime()), 'nothing changed, nothing to write');
        self::assertSame(0, $importer->getImportedCount());
        self::assertSame(1, $importer->getSkippedCount());
    }

    public function testLocaleOrderIsNotAChange(): void
    {
        // Stored in an order the exporter would never write: it sorts the alt_* columns.
        $this->media->setAlts(Yaml::dump(['fr' => 'Alt FR', 'en' => 'Alt EN']));
        $this->em->flush();

        $importer = $this->importerFor("fileName,alt,alt_en,alt_fr\n".self::FILE_NAME.",Base alt,Alt EN,Alt FR\n");

        self::assertFalse($importer->importMedia($this->filePath, new DateTime()), 'same locales, different order — nothing to import');
        self::assertSame(1, $importer->getSkippedCount());
    }

    public function testColumnBackedByARealSetterIsComparedThroughItsGetter(): void
    {
        // mimeType has a setter, so setData() writes it there and never as a custom
        // property: comparing it as one would report a change on every single sync.
        $importer = $this->importerFor("fileName,alt,mimeType\n".self::FILE_NAME.",Base alt,text/plain\n");

        self::assertFalse($importer->importMedia($this->filePath, new DateTime()), 'the database already holds that mime type');
        self::assertSame(0, $importer->getImportedCount());
    }

    public function testMediaAbsentFromCsvKeepsItsMetadata(): void
    {
        $this->media->setCustomProperty('credit', 'A garder');
        $this->em->flush();

        $importer = $this->importerFor("fileName,alt,tags\nsomething-else.txt,Autre,\n");

        self::assertFalse($importer->importMedia($this->filePath, new DateTime()));
        $importer->finishImport();

        // No row means no instruction — never an instruction to erase.
        self::assertSame('Base alt', $this->media->getAlt(true));
        self::assertSame('A garder', $this->media->getCustomProperty('credit'));
    }
}
