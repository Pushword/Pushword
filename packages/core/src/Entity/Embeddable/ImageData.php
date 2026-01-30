<?php

namespace Pushword\Core\Entity\Embeddable;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvertColor\Color;
use Pushword\Core\Entity\Dimensions;
use Pushword\Core\Utils\ImageRatioLabeler;

#[ORM\Embeddable]
class ImageData
{
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $height = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $width = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    public ?float $ratio = null;

    #[ORM\Column(name: 'ratio_label', type: Types::STRING, nullable: true, options: ['default' => ''])]
    public ?string $ratioLabel = null;

    #[ORM\Column(name: 'main_color', type: Types::STRING, nullable: true)]
    public ?string $mainColor = null;

    public function isImage(string $mimeType): bool
    {
        return str_contains($mimeType, 'image/')
            && \in_array(strtolower(str_replace('image/', '', $mimeType)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    /** @param array<int>|null $dimensions where 0 is width and 1 is height */
    public function setDimensions(?array $dimensions): self
    {
        if (null === $dimensions) {
            return $this;
        }

        if (isset($dimensions[0])) {
            $this->width = $dimensions[0];
        }

        if (isset($dimensions[1])) {
            $this->height = $dimensions[1];
        }

        if (isset($dimensions[0], $dimensions[1])) {
            $this->ratio = round($dimensions[0] / $dimensions[1], 2);
            $this->ratioLabel = ImageRatioLabeler::fromDimensions($dimensions[0], $dimensions[1]);
        }

        return $this;
    }

    public function getDimensions(): ?Dimensions
    {
        if (null === $this->height || null === $this->width) {
            return null;
        }

        return new Dimensions($this->width, $this->height);
    }

    public function getMainColorOpposite(): ?string
    {
        if (null === $this->mainColor) {
            return null;
        }

        if (\strlen($this->mainColor) > 7) {
            $this->mainColor = substr($this->mainColor, 0, 7);
        }

        return Color::fromHex($this->mainColor)->invert(true);
    }
}
