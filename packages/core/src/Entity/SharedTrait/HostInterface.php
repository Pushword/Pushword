<?php

namespace Pushword\Core\Entity\SharedTrait;

interface HostInterface
{
    public function getHost(): ?string;

    public function setHost(?string $host): self;
}
