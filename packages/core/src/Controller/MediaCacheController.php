<?php

namespace Pushword\Core\Controller;

use League\Flysystem\FilesystemException;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\MediaCacheStorageAdapter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves an image filter variant (e.g. /media/md/photo.webp), generating it on the
 * fly when the static file is missing.
 *
 * Variants normally live under public/{mediaDir}/{filter}/ — written by the synchronous
 * quick preview and the background pw:image:cache job — and are served directly by the
 * web server, so this controller never runs for them. It is the fallback for the window
 * before the background job lands (fresh upload, deploy, cache clear, CDN miss): it builds
 * the requested variant, persists it next to the others (later requests are then served
 * statically) and returns it. Without it, a <picture> referencing a not-yet-generated
 * variant shows a broken image.
 */
final class MediaCacheController extends AbstractController
{
    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly ImageCacheManager $imageCacheManager,
        private readonly ImageCacheGenerator $imageCacheGenerator,
        private readonly MediaCacheStorageAdapter $mediaCacheStorage,
    ) {
    }

    #[Route(
        '/%pw.public_media_dir%/{filter}/{fileName}',
        name: 'pushword_media_cache',
        requirements: ['filter' => '[a-zA-Z0-9_-]+', 'fileName' => '[a-zA-Z0-9\-\.]+'],
        methods: ['GET', 'HEAD'],
        priority: 10,
    )]
    public function generate(string $filter, string $fileName): BinaryFileResponse|RedirectResponse|StreamedResponse
    {
        if (! \array_key_exists($filter, $this->imageCacheManager->getFilterSets())) {
            throw $this->createNotFoundException();
        }

        $media = $this->resolveMedia($fileName);
        if (! $media instanceof Media || ! $media->isImage()) {
            throw $this->createNotFoundException();
        }

        $extension = strtolower(pathinfo($fileName, \PATHINFO_EXTENSION));
        $format = 'webp' === $extension ? 'webp' : null;
        $key = $this->imageCacheManager->getFilterKey($media, $filter, $format);
        $filePath = $this->imageCacheManager->getFilterPath($media, $filter, $format);

        // Already produced by the background job (or a concurrent request): serve it —
        // but only when it is a real, non-empty file. A 0-byte variant (a poisoned cache
        // entry left by a failed encode/optimize) must be treated as missing and rebuilt,
        // never served as HTTP 200 with Content-Length: 0 (a broken <img> the CDN caches).
        if ($this->imageCacheManager->isFilterFileUsable($media, $filter, $format)) {
            return $this->serve($key, $filePath);
        }

        // Build it now and persist it next to the other variants, so later requests
        // are served statically by the web server.
        $this->imageCacheGenerator->generateFilteredCache($media, $filter);

        if (! $this->imageCacheManager->isFilterFileUsable($media, $filter, $format)) {
            throw $this->createNotFoundException();
        }

        return $this->serve($key, $filePath);
    }

    private function serve(string $key, string $localPath): BinaryFileResponse|RedirectResponse|StreamedResponse
    {
        if ($this->mediaCacheStorage->isLocal()) {
            return new BinaryFileResponse($localPath);
        }

        $publicUrl = $this->mediaCacheStorage->getPublicUrl($key);
        if (null !== $publicUrl) {
            return new RedirectResponse($publicUrl);
        }

        try {
            $mimeType = $this->mediaCacheStorage->mimeType($key);
        } catch (FilesystemException) {
            $mimeType = 'application/octet-stream';
        }

        $storage = $this->mediaCacheStorage;

        return new StreamedResponse(
            static function () use ($storage, $key): void {
                $stream = $storage->readStream($key);
                fpassthru($stream);
                fclose($stream);
            },
            Response::HTTP_OK,
            ['Content-Type' => $mimeType],
        );
    }

    /**
     * Resolves the original Media from a variant fileName, mapping a .webp request
     * back to the source file's real extension (jpg/png/…).
     */
    private function resolveMedia(string $fileName): ?Media
    {
        $media = $this->mediaRepository->findOneByFileNameOrHistory($fileName);
        if (null !== $media) {
            return $media;
        }

        if ('webp' !== strtolower(pathinfo($fileName, \PATHINFO_EXTENSION))) {
            return null;
        }

        $baseName = pathinfo($fileName, \PATHINFO_FILENAME);
        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
            $media = $this->mediaRepository->findOneByFileNameOrHistory($baseName.'.'.$ext);
            if (null !== $media) {
                return $media;
            }
        }

        return null;
    }
}
