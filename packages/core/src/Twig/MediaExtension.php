<?php

namespace Pushword\Core\Twig;

use Exception;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\ImageManager;
use Pushword\Core\Service\PageOpenGraphImageGenerator;

use function Safe\preg_match;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

class MediaExtension
{
    public function __construct(
        public Twig $twig,
        private readonly ImageManager $imageManager,
        private readonly MediaRepository $mediaRepository,
        private readonly PageOpenGraphImageGenerator $pageOpenGraphImageGenerator,
        #[Autowire('%pw.public_media_dir%')]
        private readonly string $publicMediaDir,
    ) {
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

        if ($this->mayBeAnInternalImage($src)) {
            $media = $this->mediaRepository->findOneBy(['fileName' => $src]) ??
            throw new Exception("Internal - Can't handle the value submitted `".$src.'`.');

            if ('' !== $name) { // to check if useful
                $media->setAlt($name, true);
            }

            return $media;
        }

        if (false !== $this->imageManager->cacheExternalImage($src)) {
            return $this->imageManager->importExternal($src, $name);
        }

        throw new Exception("Can't handle the value submitted (".$src.')');
    }
}
