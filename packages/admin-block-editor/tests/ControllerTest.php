<?php

namespace Pushword\AdminBlockEditor\Tests;

use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;

use function Safe\file_get_contents;
use function Safe\json_decode;
use function Safe\json_encode;

use Symfony\Component\HttpFoundation\Response;

class ControllerTest extends AbstractAdminTestClass
{
    public function testBasics(): void
    {
        $client = $this->loginUser(
            // static::createPantherClient([            'webServerDir' => __DIR__.'/../../skeleton/public'        ])
        );

        $id = $this->createNewPage();

        $client->request('GET', '/admin/page/'.$id.'/edit');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        // does'nt throw error = good start, can do better ?

        $client->request('GET', '/admin-block-editor.test/test');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        // does'nt throw error = every filters are working (well ?)
        // if bug encouter, test them via BlockEditorFilterTest
    }

    private function createNewPage(): ?int
    {
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

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

    public function testPageController(): void
    {
        $client = $this->loginUser(
            // static::createPantherClient([            'webServerDir' => __DIR__.'/../../skeleton/public'        ])
        );
        $client->request(
            'POST',
            '/admin/page/block/',
            [],
            [],
            [],
            json_encode(['kw' => 'content:fun', 'display' => 'list', 'order' => 'priority ↓', 'max' => '', 'maxPages' => ''])
        );

        self::assertSame(
            Response::HTTP_OK,
            $client->getResponse()->getStatusCode(),
            (string) $client->getResponse()->getContent()
        );

        self::assertStringStartsWith('{"success":1,"content":"<ul><li><ahref=\"', str_replace(
            [' ', '\n'],
            '',
            (string) $client->getResponse()->getContent()
        ));

        $client->request(
            'POST',
            '/admin/page/block/1',
            [],
            [],
            [],
            json_encode(['kw' => 'fun', 'display' => 'list', 'order' => 'priority ↓,publishedAt ↓', 'max' => '', 'maxPages' => ''])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    public function testMediaController(): void
    {
        $client = $this->loginUser(
            // static::createPantherClient([            'webServerDir' => __DIR__.'/../../skeleton/public'        ])
        );
        $client->request(
            'POST',
            '/admin/media/block',
            [],
            [],
            [],
            json_encode(['url' => 'https://github.com/fluidicon.png'])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        self::assertSame('image/png', json_decode((string) $client->getResponse()->getContent())->file->mimeType); // @phpstan-ignore-line
    }
}
