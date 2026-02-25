<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Sync;

use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Flat\Exporter\MediaExporter;
use Pushword\Flat\FlatFileContentDirFinder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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
