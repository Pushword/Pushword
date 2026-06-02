<?php

namespace Pushword\Core\Controller;

use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
    ) {
    }

    #[Route(
        '/%pw.public_media_dir%/{filter}/{fileName}',
        name: 'pushword_media_cache',
        requirements: ['filter' => '[a-zA-Z0-9_-]+', 'fileName' => '[a-zA-Z0-9\-\.]+'],
        methods: ['GET', 'HEAD'],
        priority: 10,
    )]
    public function generate(string $filter, string $fileName): BinaryFileResponse
    {
        if (! \array_key_exists($filter, $this->imageCacheManager->getFilterSets())) {
            throw $this->createNotFoundException();
        }

        $media = $this->resolveMedia($fileName);
        if (! $media instanceof Media || ! $media->isImage()) {
            throw $this->createNotFoundException();
        }

        $extension = strtolower(pathinfo($fileName, \PATHINFO_EXTENSION));
        $filePath = $this->imageCacheManager->getFilterPath($media, $filter, 'webp' === $extension ? 'webp' : null);

        // Already produced by the background job (or a concurrent request): serve it.
        if (is_file($filePath)) {
            return new BinaryFileResponse($filePath);
        }

        // Build it now and persist it next to the other variants, so later requests
        // are served statically by the web server.
        $this->imageCacheGenerator->generateFilteredCache($media, $filter);

        if (! is_file($filePath)) {
            throw $this->createNotFoundException();
        }

        return new BinaryFileResponse($filePath);
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
