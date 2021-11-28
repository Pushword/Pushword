<?php

namespace Pushword\Core\Entity;

use Doctrine\Common\Collections\Collection;
use Pushword\Core\Entity\SharedTrait\CustomPropertiesInterface;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\TimestampableInterface;
use Symfony\Component\HttpFoundation\File\File;

interface MediaInterface extends IdInterface, TimestampableInterface, CustomPropertiesInterface
{
    public function getWidth(): ?int;

    public function getHeight(): ?int;

    public function getMedia(): ?string;

    public function getMediaBeforeUpdate(): ?string;

    public function getPath(): string;

    public function setProjectDir(string $projectDir): self;

    public function getStoreIn(): ?string;

    public function setStoreIn(string $pathToDir): self;

    public function getSlug(): string;

    public function setMainColor(?string $mainColor): self;

    public function getMainColor(): ?string;

    public function setSize(?int $size): self;

    public function setSlug(?string $filename): self;

    public function setMimeType(?string $mimeType): self;

    /**
     * @param array<int>|null $dimensions
     */
    public function setDimensions(?array $dimensions): self;

    public function setMedia(?string $media): self;

    public function setName(?string $name): self;

    public function setMediaFile(?File $file = null): void;

    public function getMimeType(): ?string;

    public function getName(bool $onlyName = false): string;

    public function getNameLocalized(?string $getLocalized = null, bool $onlyLocalized = false): string;

    public function getMediaFile(): ?File;

    /**
     * @throws \Exception if mediaFile is null
     */
    public function getMediaFileName(): string;

    /**
     * @return PageInterface[]|Collection<int, PageInterface>
     */
    public function getMainImagePages();
}
