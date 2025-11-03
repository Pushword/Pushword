<?php

namespace Pushword\Core\Service\Markdown\Extension\Node;

use League\CommonMark\Extension\CommonMark\Node\Inline\Link;

/**
 * Représente un lien obfusqué (commence par #).
 */
class ObfuscatedLink extends Link
{
    private ?string $attributeClass = null;

    private ?string $attributeId = null;

    public function __construct(string $url, ?string $label = null, ?string $title = null)
    {
        parent::__construct($url, $label, $title);
    }

    public function setAttributeClass(?string $class): void
    {
        $this->attributeClass = $class;
    }

    public function getAttributeClass(): ?string
    {
        return $this->attributeClass;
    }

    public function setAttributeId(?string $id): void
    {
        $this->attributeId = $id;
    }

    public function getAttributeId(): ?string
    {
        return $this->attributeId;
    }
}
