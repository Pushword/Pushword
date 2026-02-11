<?php

namespace Pushword\Core\Twig;

use Exception;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Image\ExternalImageImporter;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\PageOpenGraphImageGenerator;

use function Safe\preg_match;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

class MediaExtension
{
    public function __construct(
        public Twig $twig,
        private readonly ExternalImageImporter $externalImageImporter,
        private readonly MediaRepository $mediaRepository,
        private readonly PageOpenGraphImageGenerator $pageOpenGraphImageGenerator,
        #[Autowire('%pw.public_media_dir%')]
        private readonly string $publicMediaDir,
    ) {
    }

    /**
     * Preload all media into repository cache for batch operations like static generation.
     * Call this before processing multiple pages to avoid N+1 queries.
     */
    public function preloadMediaCache(): void
    {
        $this->mediaRepository->loadMedias();
    }

    #[AsTwigFunction('isInstanceOfMedia')]
    public function isInstanceOfMedia(mixed $src): bool
    {
        if (! \is_object($src)) {
            return false;
        }

        return $src instanceof Media;
    }

    #[AsTwigFunction('open_graph_image_generated_path')]
    public function getOpenGraphImageGeneratedPath(Page $page): ?string
    {
        $this->pageOpenGraphImageGenerator->page = $page;

        if (! file_exists($this->pageOpenGraphImageGenerator->getPath())) {
            return null;
        }

        return $this->pageOpenGraphImageGenerator->getPath(true);
    }

    public function mayBeAnInternalImage(string $media): bool
    {
        return str_starts_with($media, '/'.$this->publicMediaDir.'/default/')
        || str_starts_with($media, '/'.$this->publicMediaDir.'/')
        || ! str_contains($media, '/');
    }

    private function normalizeMediaPath(string $src): string
    {
        $src = urldecode($src);

        if (1 === preg_match('/^[0-9a-z-]+$/', $src)) {
            return $src.'.jpg';
        }

        $mediaDir = $this->publicMediaDir;
        if (str_starts_with($src, '/'.$mediaDir.'/default/')) {
            $src = substr($src, \strlen('/'.$mediaDir.'/default/'));
        } elseif (str_starts_with($src, '/'.$mediaDir.'/md/')) {
            $src = substr($src, \strlen('/'.$mediaDir.'/md/'));
        } elseif (str_starts_with($src, '/'.$mediaDir.'/thumb/')) {
            $src = substr($src, \strlen('/'.$mediaDir.'/thumb/'));
        } elseif (2 === substr_count($src, '/') && str_starts_with($src, '/'.$mediaDir.'/')) {
            $src = substr($src, \strlen('/'.$mediaDir.'/'));
        }

        return $src;
    }

    /**
     * Convert the source path (often /media/default/... or just ...) in a Media Object.
     * Handles inverted format: if $src looks like a caption and $name looks like a filename, swap them.
     * Also handles Media object passed as either argument.
     */
    #[AsTwigFunction('media_from_string')]
    public function transformStringToMedia(Media|string|int|null $src, Media|string $name = ''): Media
    {
        if (null === $src || '' === $src) {
            throw new Exception('media_from_string() received an empty source. Check your template includes pass a valid `image`, `image_src` or `imageSrc` variable.');
        }

        // Handle Media object passed as either argument
        if ($src instanceof Media) {
            return $src;
        }

        if ($name instanceof Media) {
            return $name;
        }

        $src = (string) $src;

        // Handle inverted format: {'caption': 'image.jpg'} -> swap if $name looks like a filename
        if ('' !== $name && 1 === preg_match('/^[a-zA-Z0-9_-]+\.(jpg|jpeg|png|gif|webp)$/i', $name)
            && 0 === preg_match('/^[a-zA-Z0-9_-]+\.(jpg|jpeg|png|gif|webp)$/i', $src)) {
            [$src, $name] = [$name, $src];
        }

        $src = $this->normalizeMediaPath($src);

        if ($this->mayBeAnInternalImage($src)) {
            $media = $this->findMediaByFileName($src) ??
            throw new Exception("Internal - Can't handle the value submitted `".$src.'`.');

            if ('' !== $name) {
                $media->setAlt($name, true);
            }

            return $media;
        }

        if (false !== $this->externalImageImporter->cacheExternalImage($src)) {
            return $this->externalImageImporter->importExternal($src, $name);
        }

        throw new Exception("Can't handle the value submitted (".$src.')');
    }

    /**
     * Find media by filename, handling modern format extensions (.webp).
     * When browser paths use optimized formats, the original file may have a different extension.
     */
    private function findMediaByFileName(string $fileName): ?Media
    {
        // First try exact match (uses repository cache if preloaded)
        $media = $this->mediaRepository->findOneByFileName($fileName);
        if (null !== $media) {
            return $media;
        }

        // If extension is webp, try finding with common image extensions
        $extension = strtolower(pathinfo($fileName, \PATHINFO_EXTENSION));
        if ('webp' === $extension) {
            $baseName = pathinfo($fileName, \PATHINFO_FILENAME);
            foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
                $media = $this->mediaRepository->findOneByFileName($baseName.'.'.$ext);
                if (null !== $media) {
                    return $media;
                }
            }
        }

        return null;
    }
}
