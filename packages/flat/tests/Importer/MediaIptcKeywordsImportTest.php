<?php

namespace Pushword\Flat\Tests\Importer;

use DateTime;
use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Tests\Image\License\ImageMetadataFixture;
use Pushword\Flat\Exporter\MediaExporter;
use Pushword\Flat\Importer\MediaImporter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Lightroom and darktable export their keywords as IPTC-IIM 2#025 datasets, one per
 * keyword. The importer reads them into the media tags — but only for a file media.csv
 * says nothing about, since a row a human edited is the more explicit instruction.
 */
#[Group('integration')]
final class MediaIptcKeywordsImportTest extends KernelTestCase
{
    private const string FILE_NAME = 'iptc-keywords.jpg';

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
        $this->csvPath = $cacheDir.'/test-media-iptc/'.MediaExporter::CSV_FILE;

        $this->filePath = $this->getMediaDir().'/'.self::FILE_NAME;

        // The photo is already known: a plain JPEG whose hash is in database. Each test
        // then re-exports it carrying metadata, and that is the change to import.
        $this->filesystem->mkdir(\dirname($this->filePath));
        ImageMetadataFixture::write($this->filePath);

        $this->media = new Media();
        $this->media
            ->setProjectDir($this->getProjectDir())
            ->setStoreIn($this->getMediaDir())
            ->setFileName(self::FILE_NAME)
            ->setAlt('Base alt')
            ->setMimeType('image/jpeg')
            ->setSize((int) filesize($this->filePath));

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

    /**
     * @param array<string, string|list<string>> $iptcIim
     */
    private function reExportWith(array $iptcIim): void
    {
        ImageMetadataFixture::write($this->filePath, iptcIim: $iptcIim);
        clearstatcache(true, $this->filePath);
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

    public function testKeywordsEmbeddedByThePhotoManagerBecomeTags(): void
    {
        $this->reExportWith(['2#025' => ['montagne', 'randonnée', 'automne']]);

        // No row for this file: media.csv has nothing to say about it.
        $importer = $this->importerFor("fileName,alt,tags\nsomething-else.jpg,Autre,\n");

        self::assertTrue($importer->import($this->filePath, new DateTime('+1 minute')));
        $importer->finishImport();

        // One dataset per keyword, joined then stored sorted — accents kept, since the
        // tag is the word the photographer typed, not a slug.
        self::assertSame('automne montagne randonnée', trim($this->media->getTags()));
    }

    public function testASingleKeywordIsImportedToo(): void
    {
        $this->reExportWith(['2#025' => 'montagne']);

        $importer = $this->importerFor("fileName,alt,tags\n");

        self::assertTrue($importer->import($this->filePath, new DateTime('+1 minute')));
        $importer->finishImport();

        self::assertSame('montagne', trim($this->media->getTags()));
    }

    public function testMediaCsvWinsOverTheEmbeddedKeywords(): void
    {
        $this->reExportWith(['2#025' => ['montagne', 'randonnée']]);

        // A human wrote this row; the file's own keywords must not overwrite it.
        $importer = $this->importerFor("fileName,alt,tags\n".self::FILE_NAME.",Base alt,plage\n");

        self::assertTrue($importer->import($this->filePath, new DateTime('+1 minute')));
        $importer->finishImport();

        self::assertSame('plage', trim($this->media->getTags()));
    }

    public function testAFileCarryingNoKeywordsGetsNoTags(): void
    {
        $this->reExportWith(['2#080' => 'Un photographe']); // by-line, not keywords

        $importer = $this->importerFor("fileName,alt,tags\n");

        self::assertTrue($importer->import($this->filePath, new DateTime('+1 minute')));
        $importer->finishImport();

        self::assertSame('', trim($this->media->getTags()));
    }
}
