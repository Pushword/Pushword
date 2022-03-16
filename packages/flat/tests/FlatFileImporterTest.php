<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests;

use App\Entity\Media;
use App\Entity\Page;
use Pushword\Core\Repository\Repository;
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

    public function testWithSetMediaDir()
    {
        $name = $this->prepare();

        $importer = $this->getImporter();

        $importer->setMediaDir(self::$kernel->getContainer()->getParameter('kernel.project_dir').'/media');

        $importer->run(
            'pushword.piedweb.com'
        );

        $this->assertFileExists(self::$kernel->getContainer()->getParameter('kernel.project_dir').'/media/logo-test.png');

        $this->clean($name);
    }

    public function testIt(): void
    {
        $name = $this->prepare();

        $importer = $this->getImporter();

        $importer->run(
            'pushword.piedweb.com'
        );

        $repo = Repository::getMediaRepository(self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager'), Media::class);

        $this->assertStringContainsString('/docs/content/media', $repo->findOneBy(['media' => $name])->getStoreIn());
        $this->clean($name);
    }

    private function getImporter(): FlatFileImporter
    {
        return new FlatFileImporter(
            self::$kernel->getContainer()->getParameter('pw.public_dir'),
            self::$kernel->getContainer()->getParameter('pw.media_dir'),
            self::$kernel->getContainer()->get('pushword.apps'),
            $this->getContentDirFinder(),
            $this->getPageImporter(),
            $this->getMediaImporter()
        );
    }

    private function getContentDir()
    {
        return self::$kernel->getContainer()->getParameter('kernel.project_dir').'/../docs/content';
    }

    private function prepare()
    {
        self::bootKernel();
        $newName = 'logo-test'.uniqid().rand().'.png';

        (new FileSystem())->copy(__DIR__.'/content/test-content.md', $this->getContentDir().'/test-content.md');
        (new FileSystem())->copy(__DIR__.'/content/media/logo-test.png', $this->getContentDir().'/media/logo-test.png');
        (new FileSystem())->copy(__DIR__.'/content/media/logo-test.png', $this->getContentDir().'/media/'.$newName);

        return $newName;
    }

    private function clean($name)
    {
        @unlink(self::$kernel->getContainer()->getParameter('pw.media_dir').'/logo-test.png');
        @unlink(self::$kernel->getContainer()->getParameter('pw.media_dir').'/'.$name);
        @unlink($this->getContentDir().'/test-content.md');
        @unlink($this->getContentDir().'/media/'.$name);
        @unlink($this->getContentDir().'/media/logo-test.png');
    }

    private function getContentDirFinder()
    {
        return new FlatFileContentDirFinder(
            self::$kernel->getContainer()->get('pushword.apps'),
            self::$kernel->getContainer()->getParameter('pw.public_dir')
        );
    }

    private function getMediaImporter(): MediaImporter
    {
        return (new MediaImporter(
            self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager'),
            self::$kernel->getContainer()->get('pushword.apps'),
            Media::class
        ))->setProjectDir(self::$kernel->getContainer()->getParameter('kernel.project_dir'));
        // ->setMediaDir(self::$kernel->getContainer()->getParameter('kernel.project_dir').'/media');
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

        return $pageImporter;
    }
}
