<?php

namespace Pushword\Core\Twig;

use Exception;
use Override;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\ImageManager;
use Pushword\Core\Service\PageOpenGraphImageGenerator;

use function Safe\preg_match;

use Twig\Environment as Twig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * @template T of object
 *
 *
 *  */
class MediaExtension extends AbstractExtension
{
    public function __construct(
        private readonly AppPool $apps,
        public Twig $twig,
        private readonly ImageManager $imageManager,
        private readonly PageOpenGraphImageGenerator $pageOpenGraphImageGenerator,
    ) {
    }

    /**
     * @return TwigFilter[]
     */
    #[Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('image', $this->imageManager->getBrowserPath(...)),
        ];
    }

    /**
     * @return TwigFunction[]
     */
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('gallery', $this->renderGallery(...), AppExtension::options()),
            new TwigFunction('media_from_string', $this->transformStringToMedia(...)),
            new TwigFunction('image_dimensions', $this->imageManager->getDimensions(...)),
            new TwigFunction('open_graph_image_generated_path', $this->getOpenGraphImageGeneratedPath(...)),
        ];
    }

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

    /**
     * @param array<mixed> $images
     */
    public function renderGallery(
        array $images,
        ?string $gridCols = null,
        ?string $imageFilter = null,
        ?string $imageContainer = null
    ): string {
        $template = $this->apps->get()->getView('/component/images_gallery.html.twig');

        return $this->twig->render($template, [
            'images' => $images,
            'grid_cols' => $gridCols,
            'image_filter' => $imageFilter,
            'image_coontainer' => $imageContainer,
        ]);
    }
}
