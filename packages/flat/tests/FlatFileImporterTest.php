<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests;

use Doctrine\ORM\EntityManager;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\FlatFileImporter;
use Pushword\Flat\Importer\MediaImporter;
use Pushword\Flat\Importer\PageImporter;

use function Safe\realpath;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

class FlatFileImporterTest extends KernelTestCase
{
    public function testFlatFileContentDirFinder(): void
    {
        self::bootKernel();

        self::assertSame($this->getContentDirFinder()->get('pushword.piedweb.com'), $this->getContentDir());
    }

    public function testWithSetMediaDir(): void
    {
        $name = $this->prepare();

        $importer = $this->getImporter();
        $importer->setMediaDir(self::getContainer()->getParameter('kernel.project_dir').'/media');
        $importer->run('pushword.piedweb.com');

        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        self::assertFileExists($projectDir.'/media/logo-test.png');

        self::assertLinkToMarkdownFileIsReplacedBySlugPath();

        $this->clean($name);
    }

    private function assertLinkToMarkdownFileIsReplacedBySlugPath(): void
    {
        /** @var EntityManager */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $pageRepo = $em->getRepository(Page::class);

        $page = $pageRepo->findOneBy(['slug' => 'test-link']);
        self::assertInstanceOf(Page::class, $page);
        self::assertStringContainsString('](/test-content)', $page->getMainContent());
    }

    private function getImporter(): FlatFileImporter // @phpstan-ignore-line
    {
        return new FlatFileImporter(
            self::getContainer()->getParameter('pw.public_dir'),
            self::getContainer()->getParameter('pw.media_dir'),
            self::getContainer()->get(AppPool::class),
            $this->getContentDirFinder(),
            $this->getPageImporter(),
            $this->getMediaImporter()
        );
    }

    private function getContentDir(): string
    {
        return realpath(self::getContainer()->getParameter('kernel.project_dir').'/../docs/content');
    }

    private function prepare(): string
    {
        self::bootKernel();
        $newName = 'logo-test'.uniqid().random_int(0, mt_getrandmax()).'.png';

        (new Filesystem())->copy(__DIR__.'/content/test-content.md', $this->getContentDir().'/test-content.md');
        (new Filesystem())->copy(__DIR__.'/content/test-link.md', $this->getContentDir().'/test-link.md');
        (new Filesystem())->copy(__DIR__.'/content/media/logo-test.png', $this->getContentDir().'/media/logo-test.png');
        (new Filesystem())->copy(__DIR__.'/content/media/logo-test.png', $this->getContentDir().'/media/'.$newName);

        return $newName;
    }

    private function clean(string $name): void
    {
        @unlink(self::getContainer()->getParameter('pw.media_dir').'/logo-test.png');
        @unlink(self::getContainer()->getParameter('pw.media_dir').'/'.$name);
        @unlink($this->getContentDir().'/test-content.md');
        @unlink($this->getContentDir().'/test-link.md');
        @unlink($this->getContentDir().'/media/'.$name);
        @unlink($this->getContentDir().'/media/logo-test.png');
    }

    private function getContentDirFinder(): FlatFileContentDirFinder
    {
        return new FlatFileContentDirFinder(
            self::getContainer()->get(AppPool::class),
            self::getContainer()->getParameter('pw.public_dir')
        );
    }

    private function getMediaImporter(): MediaImporter
    {
        return new MediaImporter(
            self::getContainer()->get('doctrine.orm.default_entity_manager'),
            self::getContainer()->get(AppPool::class),
            self::getContainer()->getParameter('kernel.project_dir').'/media',
            self::getContainer()->getParameter('kernel.project_dir')
        );
    }

    private function getPageImporter(): PageImporter
    {
        $pageImporter = new PageImporter(
            self::getContainer()->get('doctrine.orm.default_entity_manager'),
            self::getContainer()->get(AppPool::class),
        );
        $pageImporter->contentDirFinder = $this->getContentDirFinder();
        $pageImporter->pageRepo = self::getContainer()->get('doctrine.orm.default_entity_manager')->getRepository(Page::class);

        return $pageImporter;
    }
}
