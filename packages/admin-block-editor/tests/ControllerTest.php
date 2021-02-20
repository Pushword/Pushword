<?php

namespace Pushword\AdminBlockEditor\Tests;

use App\Entity\Page;
use Pushword\Admin\Tests\AbstractAdminTest;

class ControllerTest extends AbstractAdminTest
{
    public function testBasics()
    {
        $client = $this->loginUser(
            //static::createPantherClient([            'webServerDir' => __DIR__.'/../../skeleton/public'        ])
        );

        $id = $this->createNewPage();

        $client->request('GET', '/admin/app/page/'.$id.'/edit');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        // does'nt throw error = good start, can do better ?

        $client->request('GET', '/admin-block-editor.test/test');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        // does'nt throw error = every filters are working (well ?)
        // if bug encouter, test them via BlockEditorFilterTest
    }

    private function createNewPage()
    {
        $em = self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager');

        $page = (new Page())
            ->setH1('Test editorJsPage')
            ->setSlug('test')
            ->setHost('admin-block-editor.test')
            ->setLocale('en')
            ->setMainContent(file_get_contents(__DIR__.'/content/content.json'));

        $em->persist($page);
        $em->flush();

        return $page->getId();
    }

    public function testPageController()
    {
        $client = $this->loginUser(
            //static::createPantherClient([            'webServerDir' => __DIR__.'/../../skeleton/public'        ])
        );
        $client->request(
            'POST',
            '/admin/page/block/',
            [],
            [],
            [],
            json_encode(['kw' => 'fun', 'display' => 'list', 'order' => 'priority,publishedAt DESC', 'max' => '', 'maxPages' => ''])
        );
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        //dd(str_replace([' ', '\n'], '', $client->getResponse()->getContent()));
        $this->assertStringStartsWith(
            '{"success":1,"content":"<ul><li><ahref=\"',
            str_replace([' ', '\n'], '', $client->getResponse()->getContent())
        );

        $client->request(
            'POST',
            '/admin/page/block/1',
            [],
            [],
            [],
            json_encode(['kw' => 'fun', 'display' => 'list', 'order' => 'priority,publishedAt DESC', 'max' => '', 'maxPages' => ''])
        );
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    public function testMediaController()
    {
        $client = $this->loginUser(
            //static::createPantherClient([            'webServerDir' => __DIR__.'/../../skeleton/public'        ])
        );
        $client->request(
            'POST',
            '/admin/media/block',
            [],
            [],
            [],
            json_encode(['url' => 'https://pushword.piedweb.com/assets/favicons/android-chrome-512x512.png'])
        );
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertSame('image/png', json_decode($client->getResponse()->getContent())->file->mimeType);
    }
}
