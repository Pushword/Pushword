<?php

namespace Pushword\Core\Entity;

use Pushword\Core\Entity\SharedTrait\CustomPropertiesInterface;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\TimestampableInterface;

interface MediaInterface extends IdInterface, TimestampableInterface, CustomPropertiesInterface
{
    public function getWidth();

    public function getMedia();

    public function getMediaBeforeUpdate();

    public function getRelativeDir();

    public function setRelativeDir($relativeDir): self;

    public function getSlug();

    public function getPath();

    public function setMainColor(?string $mainColor);

    public function setSize($size): self;

    public function setSlug($slug): self;

    public function setMimeType($mimeType): self;

    public function setDimensions($dimensions): self;

    public function setMedia($media): self;

    public function setName(?string $name): self;
}
