<?php

namespace Pushword\Core\Twig;

use Pushword\Core\Component\App\AppPool;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

/**
 * @template T of object
 */
class BlockExtension
{
    public function __construct(
        private readonly AppPool $apps,
        public Twig $twig,
    ) {
    }

    #[AsTwigFunction('attaches', isSafe: ['html'], needsEnvironment: false)]
    public function renderAttaches(
        string $title,
        string $url,
        int $size, // bytes
        string $id = '',
    ): string {
        $template = $this->apps->get()->getView('/component/attaches.html.twig');

        $html = $this->twig->render($template, [
            'id' => $id,
            'title' => $title,
            'url' => $url,
            'size' => $size,
        ]);

        return $html;
    }

    /**
     * @param array<mixed> $images is very tolerant, most of the time it's an array of string corresponding to the mediaName (eg: ['filename.jpg', 'filename2.jpg'])
     * @param int          $pos    set to < 3 permit to disable lazy loading on first image
     */
    #[AsTwigFunction('gallery', isSafe: ['html'], needsEnvironment: false)]
    public function renderGallery(
        array $images,
        ?string $gridCols = null,
        ?string $imageFilter = null,
        bool $clickable = true,
        int $pos = 100
    ): string {
        $template = $this->apps->get()->getView('/component/images_gallery.html.twig');

        return $this->twig->render($template, [
            'images' => $images,
            'grid_cols' => $gridCols,
            'image_filter' => $imageFilter,
            'pos' => $pos,
            'clickable' => $clickable,
        ]);
    }
}
