<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests;

use App\Entity\Media;
use App\Entity\Page;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\FlatFileImporter;
use Pushword\Flat\Importer\MediaImporter;
use Pushword\Flat\Importer\PageImporter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

class FlatFileImporterTest extends KernelTestCase
{
    public function testFlatFileContentDirFinder(): void
    {
        self::bootKernel();

        $this->assertSame(
            $this->getContentDirFinder()->get('pushword.piedweb.com'),
            $this->getContentDir()
        );
    }

    public function testIt(): void
    {
        self::bootKernel();

        $this->prepare();

        $importer = new FlatFileImporter(
            self::$kernel->getContainer()->getParameter('pw.dir'),
            self::$kernel->getContainer()->get('pushword.apps'),
            $this->getContentDirFinder(),
            $this->getPageImporter(),
            $this->getMediaImporter()
        );

        $importer->run(
            'pushword.piedweb.com'
        );

        // todo make test
        $this->assertFileExists(self::$kernel->getContainer()->getParameter('kernel.project_dir').'/media/logo-test.png');

        $this->clean();
    }

    private function getContentDir()
    {
        return self::$kernel->getContainer()->getParameter('kernel.project_dir').'/../docs/content';
    }

    private function prepare()
    {
        (new FileSystem())->mirror(__DIR__.'/content/media', $this->getContentDir().'/media');
        (new FileSystem())->copy(__DIR__.'/content/test-content.md', $this->getContentDir().'/test-content.md');
    }

    private function clean()
    {
        unlink(self::$kernel->getContainer()->getParameter('kernel.project_dir').'/media/logo-test.png');
        unlink($this->getContentDir().'/test-content.md');
        (new FileSystem())->remove($this->getContentDir().'/media');
    }

    private function getContentDirFinder()
    {
        return new FlatFileContentDirFinder(
            self::$kernel->getContainer()->get('pushword.apps'),
            self::$kernel->getContainer()->getParameter('pw.dir')
        );
    }

    private function getMediaImporter(): MediaImporter
    {
        return (new MediaImporter(
            self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager'),
            self::$kernel->getContainer()->get('pushword.apps'),
            Media::class
        ))->setMediaDir(self::$kernel->getContainer()->getParameter('kernel.project_dir').'/media');
    }

    private function getPageImporter()
    {
        $pageImporter = new PageImporter(
            self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager'),
            self::$kernel->getContainer()->get('pushword.apps'),
            Page::class
        );
        $pageImporter->setContentDirFinder($this->getContentDirFinder());
        $pageImporter->setMediaClass(self::$kernel->getContainer()->getParameter('pw.entity_media'));
        $pageImporter->setPageHasMediaClass(self::$kernel->getContainer()->getParameter('pw.entity_pagehasmedia'));

        return $pageImporter;
    }
}
