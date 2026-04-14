<?php

namespace Pushword\Core\Extension\Markdown\Node;

use League\CommonMark\Extension\CommonMark\Node\Inline\Link;

/**
 * Représente un lien obfusqué (commence par #).
 */
class ObfuscatedLink extends Link
{
    private ?string $attributeClass = null;

    private ?string $attributeId = null;

    /** @var array<string, string> */
    private array $attributes = [];

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

    /**
     * @param array<string, string> $attributes
     */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
