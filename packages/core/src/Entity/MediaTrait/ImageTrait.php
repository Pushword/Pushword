<?php

namespace Pushword\Core\Entity\MediaTrait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvertColor\Color;
use Pushword\Core\Utils\ImageRatioLabeler;

trait ImageTrait
{
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    protected ?int $height = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    protected ?int $width = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    protected ?float $ratio = null;

    #[ORM\Column(type: Types::STRING, nullable: true, options: ['default' => ''])]
    protected ?string $ratioLabel = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    protected ?string $mainColor = null;

    abstract public function getMimeType(): ?string;

    public function isImage(): bool
    {
        return str_contains((string) $this->getMimeType(), 'image/')
            && \in_array(strtolower(str_replace('image/', '', (string) $this->getMimeType())), ['jpg', 'jpeg', 'png', 'gif'], true);
    }

    /**
     * @param array<int>|null $dimensions where 0 is width and 1 is height
     */
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

        if (isset($dimensions[0]) && isset($dimensions[1])) {
            $this->ratio = $dimensions[0] / $dimensions[1];
            $this->ratioLabel = ImageRatioLabeler::fromDimensions($dimensions[0], $dimensions[1]);
        }

        return $this;
    }

    /**
     * @return int[]|null
     */
    public function getDimensions(): ?array
    {
        if (null === $this->height) {
            return null;
        }

        if (null === $this->width) {
            return null;
        }

        return [$this->width, $this->height];
    }

    public function getRatio(): ?float
    {
        if (null === $this->height) {
            return null;
        }

        if (null === $this->width) {
            return null;
        }

        return $this->height / $this->width;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function getMainColor(): ?string
    {
        return $this->mainColor;
    }

    public function getMainColorOpposite(): ?string
    {
        if (null === $this->mainColor) {
            return null;
        }

        if (strlen($this->mainColor) > 7) {
            // fix transparency add in mainColor
            $this->mainColor = substr($this->mainColor, 0, 7);
        }

        return Color::fromHex($this->mainColor)->invert(true);
    }

    public function setMainColor(?string $mainColor): self
    {
        $this->mainColor = $mainColor;

        return $this;
    }
}
