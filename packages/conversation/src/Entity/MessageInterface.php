<?php

namespace Pushword\Conversation\Entity;

use Pushword\Core\Entity\SharedTrait\HostInterface;
use Pushword\Core\Entity\SharedTrait\IdInterface;

interface MessageInterface extends IdInterface, HostInterface
{
    public function getAuthorName(): ?string;

    public function getAuthorEmail(): ?string;

    public function getAuthorIp(): ?int;

    public function setAuthorIpRaw(string $authorIp): self;

    public function setReferring(string $referring): self;

    public function getContent(): ?string;

    public function setContent(string $content): self;
}
