<?php

namespace Pushword\Core\Twig;

use Exception;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\ImageManager;
use Pushword\Core\Service\PageOpenGraphImageGenerator;

use function Safe\preg_match;

use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

class MediaExtension
{
    public function __construct(
        public Twig $twig,
        private readonly ImageManager $imageManager,
        private readonly MediaRepository $mediaRepository,
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

    public static function mayBeAnInternalImage(string $media): bool
    {
        return str_starts_with($media, '/media/default/')
        || str_starts_with($media, '/media/')
        || ! str_contains($media, '/');
    }

    private function normalizeMediaPath(string $src): string
    {
        if (1 === preg_match('/^[0-9a-z-]+$/', $src)) {
            return $src.'.jpg';
        }

        $src = str_starts_with($src, '/media/default/') ? substr($src, \strlen('/media/default/')) : $src;
        $src = str_starts_with($src, '/media/md/') ? substr($src, \strlen('/media/md/')) : $src;
        $src = 2 === substr_count($src, '/') && str_starts_with($src, '/media/') ? substr($src, \strlen('/media/')) : $src;

        return $src;
    }

    /**
     * Convert the source path (often /media/default/... or just ...) in a Media Object
     * /!\ No search in db.
     */
    #[AsTwigFunction('media_from_string')]
    public function transformStringToMedia(Media|string $src, string $name = ''): Media
    {
        if ($src instanceof Media) {
            return $src;
        }

        $src = $this->normalizeMediaPath($src);

        if (self::mayBeAnInternalImage($src)) {
            $media = $this->mediaRepository->findOneBy(['media' => $src]) ??
            throw new Exception("Internal - Can't handle the value submitted `".$src.'`.');

            if ('' !== $name) { // to check if useful
                $media->setName($name, true);
            }

            return $media;
        }

        if (false !== $this->imageManager->cacheExternalImage($src)) {
            return $this->imageManager->importExternal($src, $name);
        }

        throw new Exception("Can't handle the value submitted (".$src.')');
    }
}
