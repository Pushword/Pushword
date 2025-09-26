<?php

namespace Pushword\Core\Twig;

use Override;
use Pushword\Core\Component\App\AppPool;
use Twig\Environment as Twig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @template T of object
 */
class BlockExtension extends AbstractExtension
{
    public function __construct(
        private readonly AppPool $apps,
        public Twig $twig,
    ) {
    }

    /**
     * @return TwigFunction[]
     */
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('gallery', $this->renderGallery(...), AppExtension::options()),
            new TwigFunction('attaches', $this->renderAttaches(...), AppExtension::options()),
        ];
    }

    /**
     * @param array<mixed> $block
     */
    public function renderAttaches(
        array $block,
    ): string {
        $template = $this->apps->get()->getView('/component/attaches.html.twig');

        return $this->twig->render($template, [
            'block' => $block,
        ]);
    }

    /**
     * @param array<mixed> $images
     */
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
