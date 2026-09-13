<?php

declare(strict_types=1);

namespace Pushword\Core\Service\Markdown;

use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;
use Throwable;

final readonly class TempestImageRestorer
{
    public function __construct(private ?MediaExtension $mediaExtension, private ?SiteRegistry $apps)
    {
    }

    /** @param list<array{src: string, alt: string, linked: bool}> $images */
    public function restore(string $html, array $images): ?string
    {
        foreach ($images as $index => $image) {
            if (null === $this->mediaExtension || null === $this->apps) {
                return null;
            }

            $marker = "\u{E034}".$index."\u{E035}";
            if (! str_contains($html, $marker)) {
                return null;
            }

            try {
                $imageHtml = $this->mediaExtension->renderImage($image['src'], htmlspecialchars($image['alt']), link: ! $image['linked'], sizes: $this->apps->get()->bodyImageSizes());
            } catch (Throwable) {
                $imageHtml = BrokenImageComment::for($image['src']);
            }

            $html = str_replace($marker, $imageHtml, $html);
        }

        return $html;
    }
}
