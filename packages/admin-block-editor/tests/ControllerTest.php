<?php

namespace Pushword\AdminBlockEditor\Tests;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;

use function Safe\file_get_contents;
use function Safe\json_decode;
use function Safe\json_encode;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
class ControllerTest extends AbstractAdminTestClass
{
    public function testBasics(): void
    {
        $client = $this->loginUser(
            // static::createPantherClient([            'webServerDir' => __DIR__.'/../../skeleton/public'        ])
        );

        $id = $this->createNewPage();

        $client->request(Request::METHOD_GET, '/admin/page/'.$id.'/edit');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        // does'nt throw error = good start, can do better ?

        $client->request(Request::METHOD_GET, '/admin-block-editor.test/test');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        // does'nt throw error = markdown rendering is working
    }

    private function createNewPage(): ?int
    {
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $page = new Page();
        $page->setH1('Test editorJsPage');
        $page->setSlug('test');
        $page->host = 'admin-block-editor.test';
        $page->locale = 'en';
        $page->setMainContent(file_get_contents(__DIR__.'/content/content.json'));

        $em->persist($page);
        $em->flush();

        return $page->id;
    }

    public function testPageController(): void
    {
        $client = $this->loginUser(
            // static::createPantherClient([            'webServerDir' => __DIR__.'/../../skeleton/public'        ])
        );
        $client->request(
            Request::METHOD_POST,
            '/admin/page/block/',
            [],
            [],
            [],
            json_encode(['kw' => 'content:fun', 'display' => 'list', 'order' => 'weight ↓', 'max' => '', 'maxPages' => ''])
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
            Request::METHOD_POST,
            '/admin/page/block/1',
            [],
            [],
            [],
            json_encode(['kw' => 'fun', 'display' => 'list', 'order' => 'weight ↓,publishedAt ↓', 'max' => '', 'maxPages' => ''])
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    public function testMediaController(): void
    {
        $client = $this->loginUser(
            // static::createPantherClient([            'webServerDir' => __DIR__.'/../../skeleton/public'        ])
        );

        $pngFile = sys_get_temp_dir().'/test-media-block.png';
        $img = imagecreatetruecolor(1, 1);
        \assert(false !== $img);
        imagepng($img, $pngFile);
        imagedestroy($img);

        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile($pngFile, 'test.png', 'image/png', null, true);

        $client->request(
            Request::METHOD_POST,
            '/admin/media/block',
            [],
            ['image' => $uploadedFile],
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        self::assertSame('image/png', json_decode((string) $client->getResponse()->getContent())->file->mimeType); // @phpstan-ignore-line
    }

    public function testMediaResolveController(): void
    {
        $client = $this->loginUser();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        // Create a dummy file so the hash listener can compute the hash
        $dummyFile = $mediaDir.'/test-resolve.jpg';
        file_put_contents($dummyFile, 'dummy');

        $media = new Media();
        $media->setProjectDir(self::getContainer()->getParameter('kernel.project_dir'));
        $media->setStoreIn($mediaDir);
        $media->setSlugForce('test-resolve');
        $media->setFileName('test-resolve.jpg');
        $media->setMimeType('image/jpeg');
        $media->setSize(1);
        $media->addFileNameToHistory('Old Name With Spaces.jpg');

        $em->persist($media);
        $em->flush();

        // Resolve via fileNameHistory
        $client->request(Request::METHOD_GET, '/admin/media/resolve/Old%20Name%20With%20Spaces.jpg');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        /** @var array{fileName: string} $responseData */
        $responseData = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('test-resolve.jpg', $responseData['fileName']);

        // Resolve via current fileName
        $client->request(Request::METHOD_GET, '/admin/media/resolve/test-resolve.jpg');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        /** @var array{fileName: string} $responseData */
        $responseData = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('test-resolve.jpg', $responseData['fileName']);

        // Not found
        $client->request(Request::METHOD_GET, '/admin/media/resolve/nonexistent-file.jpg');
        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());

        // Re-fetch to avoid detached entity issue
        $mediaToRemove = $em->getRepository(Media::class)->findOneBy(['fileName' => 'test-resolve.jpg']);
        if (null !== $mediaToRemove) {
            $em->remove($mediaToRemove);
            $em->flush();
        }

        if (file_exists($dummyFile)) {
            unlink($dummyFile);
        }
    }
}
