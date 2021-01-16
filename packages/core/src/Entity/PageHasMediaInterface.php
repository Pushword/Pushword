<?php

namespace Pushword\Core\Entity;

interface PageHasMediaInterface
{
    public function setPage(?PageInterface $page): self;

    public function setMedia(?MediaInterface $media = null): self;
}
