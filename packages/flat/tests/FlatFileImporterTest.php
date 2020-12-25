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

class FlatFileImporterTest extends KernelTestCase
{
    public function testFlatFileContentDirFinder(): void
    {
        self::bootKernel();

        $this->assertSame(
            $this->getContentDirFinder()->get('pushword.piedweb.com'),
            self::$kernel->getContainer()->getParameter('pw.dir').'/../'
                .self::$kernel->getContainer()->get('pushword.apps')->get('pushword.piedweb.com')->get('flat_content_dir')
        );
    }

    public function testIt(): void
    {
        self::bootKernel();

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

        $this->assertSame(
            '../docs/content',
            self::$kernel->getContainer()->get('pushword.apps')->get('pushword.piedweb.com')->get('flat_content_dir')
        );
    }

    private function getContentDirFinder()
    {
        return new FlatFileContentDirFinder(
            self::$kernel->getContainer()->get('pushword.apps'),
            self::$kernel->getContainer()->getParameter('pw.dir')
        );
    }

    private function getMediaImporter()
    {
        return new MediaImporter(
            self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager'),
            self::$kernel->getContainer()->get('pushword.apps'),
            Media::class
        );
    }

    private function getPageImporter()
    {
        $pageImporter = new PageImporter(
            self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager'),
            self::$kernel->getContainer()->get('pushword.apps'),
            Page::class
        );
        $pageImporter->setContentDirFinder($this->getContentDirFinder());

        return $pageImporter;
    }
}
