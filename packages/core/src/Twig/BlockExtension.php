<?php

namespace Pushword\Core\Twig;

use Pushword\Core\Component\App\AppPool;
use stdClass;
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

    /**
     * @param array<mixed> $block
     */
    #[AsTwigFunction('attaches', isSafe: ['html'], needsEnvironment: false)]
    public function renderAttaches(
        array|stdClass $block,
    ): string {
        $template = $this->apps->get()->getView('/component/attaches.html.twig');

        return $this->twig->render($template, [
            'block' => $block,
        ]);
    }

    /**
     * @param array<mixed> $images
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
            // 'image_coontainer' => $imageContainer,
            'clickable' => $clickable,
        ]);
    }
}
