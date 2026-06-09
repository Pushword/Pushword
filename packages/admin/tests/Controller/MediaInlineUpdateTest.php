<?php

namespace Pushword\Admin\Tests\Controller;

use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Covers the inline-update and multi-delete endpoints that power the editable
 * media list (?view=table) and the multi-upload queue.
 */
final class MediaInlineUpdateTest extends AbstractAdminTestClass
{
    public function testInlineUpdatePersistsTagsAndRenamesSlug(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);
        [$id, $csrf] = $this->createMedia($client, 'inline-tags', 14, 9);

        // Tags update should persist on the entity.
        $client->request(Request::METHOD_POST, '/admin/media/'.$id.'/inline-update', [
            '_token' => $csrf,
            'field' => 'tags',
            'value' => 'alpha beta',
        ]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $media = $this->findMedia($id);
        self::assertInstanceOf(Media::class, $media);
        self::assertStringContainsString('alpha', $media->getTags());

        // Slug update should return the new slug + filename.
        $client->request(Request::METHOD_POST, '/admin/media/'.$id.'/inline-update', [
            '_token' => $csrf,
            'field' => 'slug',
            'value' => 'renamed-inline',
        ]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('slug', $data);
        self::assertArrayHasKey('fileName', $data);
        $renamed = $this->findMedia($id);
        self::assertInstanceOf(Media::class, $renamed);
        self::assertSame('renamed-inline', $renamed->getSlug());

        $this->deleteMedia($client, $id, $csrf);
    }

    public function testInlineUpdateRejectsUnknownField(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);
        [$id, $csrf] = $this->createMedia($client, 'inline-unknown', 15, 10);

        $client->request(Request::METHOD_POST, '/admin/media/'.$id.'/inline-update', [
            '_token' => $csrf,
            'field' => 'doesNotExist',
            'value' => 'x',
        ]);
        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $this->deleteMedia($client, $id, $csrf);
    }

    public function testInlineUpdateRejectsInvalidCsrf(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);
        [$id, $csrf] = $this->createMedia($client, 'inline-csrf', 16, 11);

        $client->request(Request::METHOD_POST, '/admin/media/'.$id.'/inline-update', [
            '_token' => 'invalid-token',
            'field' => 'alt',
            'value' => 'should not apply',
        ]);
        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());

        $this->deleteMedia($client, $id, $csrf);
    }

    public function testMultiDeleteRemovesMedia(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);
        [$id, $csrf] = $this->createMedia($client, 'inline-delete', 17, 12);

        $client->request(Request::METHOD_POST, '/admin/media/'.$id.'/multi-delete', ['_token' => $csrf]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertNull($this->findMedia($id), 'Media should be removed from the database');
    }

    /**
     * Uploads a uniquely-sized image and returns [mediaId, csrfToken].
     *
     * @param int<1, max> $width
     * @param int<1, max> $height
     *
     * @return array{int, string}
     */
    private function createMedia(KernelBrowser $client, string $name, int $width, int $height): array
    {
        $crawler = $client->request(Request::METHOD_GET, '/admin/multi-upload');
        $csrf = (string) $crawler->filter('#pw-multi-upload')->attr('data-csrf-token');

        $tempFile = sys_get_temp_dir().'/'.$name.'.jpg';
        $img = imagecreatetruecolor($width, $height);
        \assert(false !== $img);
        imagejpeg($img, $tempFile);

        $client->request(Request::METHOD_POST, '/admin/multi-upload/upload', [
            '_token' => $csrf,
            'originalHash' => sha1_file($tempFile),
        ], ['file' => new UploadedFile($tempFile, $name.'.jpg', 'image/jpeg', null, true)]);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('id', $data);
        self::assertIsInt($data['id']);

        return [$data['id'], $csrf];
    }

    private function deleteMedia(KernelBrowser $client, int $id, string $csrf): void
    {
        $client->request(Request::METHOD_POST, '/admin/media/'.$id.'/multi-delete', ['_token' => $csrf]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    private function findMedia(int $id): ?Media
    {
        /** @var MediaRepository $mediaRepo */
        $mediaRepo = self::getContainer()->get(MediaRepository::class);

        return $mediaRepo->findOneBy(['id' => $id]);
    }
}
