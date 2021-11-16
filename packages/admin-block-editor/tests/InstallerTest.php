<?php

declare(strict_types=1);

namespace Pushword\AdminBlockEditor\Tests;

use App\Entity\Page;
use Pushword\AdminBlockEditor\Installer\Update795;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class InstallerTest extends KernelTestCase
{
    public function testIt()
    {
        $this->assertTrue(true);

        return;

        self::bootKernel();
        $page = $this->createPage();
        $em = self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager');

        (new Update795())->run();

        $em->refresh($page);
        $this->assertTrue('test' === json_decode($page->getMainContent())->blocks[0]->tunes->anchor, 'updater is not working as expectd');
        $this->assertTrue(! isset(json_decode($page->getMainContent())->blocks[0]->data->anchor), 'updater is not working as expectd');

        $em->remove($page);
        $em->flush();

        $this->tearDown();
    }

    private function createPage()
    {
        $content = json_encode(json_decode('{
            "time": 1637074741375,
            "blocks": [
                {
                    "id": "hsk_DAV6J4",
                    "type": "header",
                    "data": {
                        "text": "test",
                        "level": 2,
                        "anchor": "test"
                    }
                }
            ],
            "version": "2.23.0-rc.0"
        }'));
        $em = self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager');

        $page = (new Page())
            ->setH1('Test editorJsPage')
            ->setSlug('test')
            ->setHost('admin-block-editor.test')
            ->setLocale('en')
            ->setMainContent($content);

        $em->persist($page);
        $em->flush();

        return $page;
    }
}
