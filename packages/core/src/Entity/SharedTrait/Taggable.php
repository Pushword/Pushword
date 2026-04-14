<?php

namespace Pushword\Core\Entity\SharedTrait;

interface Taggable extends IdInterface
{
    public function getTags(): string;

    /**
     * @return string[]
     */
    public function getTagList(): array;

    public function addTag(string $tag): void;

    /**
     * @param string[]|string|null $tags
     */
    public function setTags(array|string|null $tags): self;
}
