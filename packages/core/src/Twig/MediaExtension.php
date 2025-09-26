<?php

namespace Pushword\Core\Twig;

use Exception;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\ImageManager;
use Pushword\Core\Service\PageOpenGraphImageGenerator;

use function Safe\preg_match;

use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

/**
 * @template T of object
 *
 *
 *  */
class MediaExtension
{
    public function __construct(
        public Twig $twig,
        private readonly ImageManager $imageManager,
        private readonly PageOpenGraphImageGenerator $pageOpenGraphImageGenerator,
    ) {
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

    public static function isInternalImage(string $media): bool
    {
        return str_starts_with($media, '/media/default/') || ! str_contains($media, '/');
    }

    private function normalizeMediaPath(string $src): string
    {
        if (1 === preg_match('/^[0-9a-z-]+$/', $src)) {
            return '/media/default/'.$src.'.jpg';
        }

        return $src;
    }

    /**
     * Convert the source path (often /media/default/... or just ...) in a Media Object
     * /!\ No search in db.
     */
    #[AsTwigFunction('media_from_string')]
    public function transformStringToMedia(Media|string $src, string $name = ''): Media
    {
        $src = \is_string($src) ? $this->normalizeMediaPath($src) : $src;

        if (\is_string($src) && self::isInternalImage($src)) {
            $media = Media::loadFromSrc($src);
            $media->setName($name);

            return $media;
        }

        if (\is_string($src) && false !== $this->imageManager->cacheExternalImage($src)) {
            return $this->imageManager->importExternal($src, $name);
        }

        if (! $src instanceof Media) {
            throw new Exception("Can't handle the value submitted (".$src.')');
        }

        return $src;
    }
}
