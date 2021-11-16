<?php

namespace Pushword\Core\Twig;

use Pushword\Core\Component\App\AppConfig;

trait GalleryTwigTrait
{
    abstract public function getApp(): AppConfig;

    /**
     * @param array<mixed> $images
     */
    public function renderGallery(
        array $images,
        ?string $gridCols = null,
        ?string $imageFilter = null,
        ?string $imageContainer = null
    ): string {
        $template = $this->getApp()->getView('/component/images_gallery.html.twig');

        return $this->twig->render($template, [
            'images' => $images,
            'grid_cols' => $gridCols,
            'image_filter' => $imageFilter,
            'image_coontainer' => $imageContainer,
        ]);
    }
}
