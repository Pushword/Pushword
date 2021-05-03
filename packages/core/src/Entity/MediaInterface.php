<?php

namespace Pushword\Core\Entity;

use Pushword\Core\Entity\SharedTrait\CustomPropertiesInterface;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\TimestampableInterface;
use Symfony\Component\HttpFoundation\File\File;

interface MediaInterface extends IdInterface, TimestampableInterface, CustomPropertiesInterface
{
    public function getWidth();

    public function getMedia();

    public function getMediaBeforeUpdate();

    public function getPath(): string;

    public function setProjectDir(string $projectDir): self;

    public function getStoreIn();

    public function setStoreIn(string $pathToDir): self;

    public function getSlug();

    public function setMainColor(?string $mainColor);

    public function getMainColor();

    public function setSize($size): self;

    public function setSlug($slug): self;

    public function setMimeType($mimeType): self;

    public function setDimensions($dimensions): self;

    public function setMedia($media): self;

    public function setName(string $name): self;

    public function setMediaFile(?File $media = null): void;

    public function getMimeType(): ?string;

    public function getName(): string;

    public function getNameLocalized($getLocalized = null, $onlyLocalized = false): ?string;

    public function getMediaFile(): ?File;
}
