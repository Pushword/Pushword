<?php

namespace Pushword\Core\Entity;

interface MediaInterface
{
    public function getWidth();

    public function getMedia();

    public function getMediaBeforeUpdate();

    public function getRelativeDir();

    public function getSlug();

    public function getPath();

    public function setMainColor(?string $mainColor);
}
