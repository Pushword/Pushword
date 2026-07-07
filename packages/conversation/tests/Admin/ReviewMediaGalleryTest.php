<?php

namespace Pushword\Conversation\Tests\Admin;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Conversation\Entity\Review;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Covers the inline media gallery shown on the review index and its one-click
 * unlink endpoint (pushword_conversation_media_unlink).
 */
final class ReviewMediaGalleryTest extends AbstractAdminTestClass
{
    public function testGalleryRendersAndUnlinkRemovesMediaFromReview(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $mediaId = $this->createMedia($client, 'review-gallery', 21, 13);
        $reviewId = $this->createReviewWithMedia($mediaId);

        // The review index renders the gallery with the media thumbnail + an unlink button.
        $token = $this->fetchUnlinkToken($client, $reviewId, $mediaId);

        // One-click unlink removes the media from the review.
        $client->request(Request::METHOD_POST, '/admin/conversation/'.$reviewId.'/media/'.$mediaId.'/unlink', [
            '_token' => $token,
        ]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertFalse($this->reviewHasMedia($reviewId, $mediaId), 'The media should be unlinked from the review.');

        // The re-rendered gallery returned by the endpoint no longer exposes that thumbnail.
        self::assertStringNotContainsString(
            '/media/'.$mediaId.'/unlink',
            (string) $client->getResponse()->getContent(),
            'The re-rendered gallery must drop the unlinked media.',
        );

        // Only the association is removed; the underlying media is preserved.
        self::assertNotNull($this->findMedia($mediaId), 'The media itself must not be deleted.');
    }

    public function testUnlinkIgnoresMediaNotLinkedToReview(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $mediaId = $this->createMedia($client, 'review-gallery-foreign', 23, 15);
        $reviewId = $this->createReviewWithMedia($mediaId);

        // The CSRF token is bound to the review, not to a specific media.
        $token = $this->fetchUnlinkToken($client, $reviewId, $mediaId);

        // Unlinking a media that is not attached to the review is a harmless no-op.
        $client->request(Request::METHOD_POST, '/admin/conversation/'.$reviewId.'/media/'.($mediaId + 100000).'/unlink', [
            '_token' => $token,
        ]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertTrue($this->reviewHasMedia($reviewId, $mediaId), 'The originally linked media must stay attached.');
    }

    public function testUnlinkRejectsInvalidCsrf(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $mediaId = $this->createMedia($client, 'review-gallery-csrf', 22, 14);
        $reviewId = $this->createReviewWithMedia($mediaId);

        $client->request(Request::METHOD_POST, '/admin/conversation/'.$reviewId.'/media/'.$mediaId.'/unlink', [
            '_token' => 'invalid-token',
        ]);
        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
        self::assertTrue($this->reviewHasMedia($reviewId, $mediaId), 'An invalid token must not unlink the media.');
    }

    /**
     * The media picker on the review edit form is built by cloning the current
     * AdminUrlGenerator, which carries the Review's entityId. If it leaked into the
     * Media "new"/"index" URLs, the picker would try to load a Media with the
     * Review's id and throw EntityNotFoundException.
     */
    public function testEditFormMediaPickerUrlsDoNotLeakReviewEntityId(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $mediaId = $this->createMedia($client, 'review-picker-entityid', 24, 16);
        $reviewId = $this->createReviewWithMedia($mediaId);

        $crawler = $client->request(Request::METHOD_GET, '/admin/review/'.$reviewId.'/edit');
        self::assertResponseIsSuccessful();

        $pickers = $crawler->filter('[data-pw-media-picker-upload-url]');
        self::assertGreaterThan(0, $pickers->count(), 'The review edit form must render at least one media picker.');

        /** @var array<array{upload: string, modal: string}> $urls */
        $urls = $pickers->each(static fn (Crawler $picker): array => [
            'upload' => $picker->attr('data-pw-media-picker-upload-url') ?? '',
            'modal' => $picker->attr('data-pw-media-picker-modal-url') ?? '',
        ]);

        foreach ($urls as $url) {
            self::assertStringNotContainsString('entityId', $url['upload'], 'Upload URL must not carry the Review entityId: '.$url['upload']);
            self::assertStringNotContainsString('entityId', $url['modal'], 'Modal URL must not carry the Review entityId: '.$url['modal']);
        }
    }

    /**
     * Renders the review index and returns the CSRF token from the gallery's unlink button.
     */
    private function fetchUnlinkToken(KernelBrowser $client, int $reviewId, int $linkedMediaId): string
    {
        $crawler = $client->request(Request::METHOD_GET, '/admin/review');
        self::assertResponseIsSuccessful();

        $button = $crawler->filter('#pw-message-media-'.$reviewId.' button[hx-post$="/media/'.$linkedMediaId.'/unlink"]');
        self::assertCount(1, $button, 'The gallery should expose a one-click unlink button for the linked media.');

        return $this->extractToken((string) $button->attr('hx-vals'));
    }

    /**
     * Uploads a uniquely-sized image and returns its media id.
     *
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function createMedia(KernelBrowser $client, string $name, int $width, int $height): int
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

        return $data['id'];
    }

    private function createReviewWithMedia(int $mediaId): int
    {
        $media = $this->findMedia($mediaId);
        self::assertInstanceOf(Media::class, $media);

        $review = new Review();
        $review->setContent('Gallery test review');
        $review->setRating(5);
        $review->addMedia($media);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($review);
        $entityManager->flush();

        self::assertNotNull($review->id);

        return $review->id;
    }

    private function reviewHasMedia(int $reviewId, int $mediaId): bool
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        $review = $entityManager->getRepository(Review::class)->find($reviewId);
        if (! $review instanceof Review) {
            return false;
        }

        foreach ($review->getMediaList() as $media) {
            if ($media->id === $mediaId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reverse Twig's `e('js')` escaping (e.g. `-`) applied to the token in hx-vals.
     */
    private function extractToken(string $hxVals): string
    {
        self::assertSame(1, preg_match('/_token:\s*"([^"]*)"/', $hxVals, $matches));

        $token = preg_replace_callback(
            '/\\\\u([0-9A-Fa-f]{4})/',
            static fn (array $match): string => mb_chr((int) hexdec($match[1]), 'UTF-8'),
            $matches[1],
        );

        self::assertNotNull($token);

        return $token;
    }

    private function findMedia(int $id): ?Media
    {
        /** @var MediaRepository $mediaRepo */
        $mediaRepo = self::getContainer()->get(MediaRepository::class);

        return $mediaRepo->findOneBy(['id' => $id]);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManager $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $entityManager;
    }
}
