<?php

namespace Pushword\Conversation\Entity;

interface MessageInterface
{
    public function getAuthorName();

    public function getAuthorEmail();

    public function getAuthorIp();

    public function getContent();

    public function getId();

    public function getHost();

    public function setHost($host);

    public function setContent(string $content);
}
