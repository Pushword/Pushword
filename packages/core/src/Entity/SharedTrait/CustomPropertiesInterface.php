<?php

namespace Pushword\Core\Entity\SharedTrait;

interface CustomPropertiesInterface
{
    /**
     * @param array<mixed> $customProperties
     */
    public function setCustomProperties(array $customProperties): self;

    /**
     * @return array<mixed>
     */
    public function getCustomProperties(): array;

    public function getStandAloneCustomProperties(): string;

    public function setStandAloneCustomProperties(?string $standStandAloneCustomProperties, bool $merge = false): self;

    public function isStandAloneCustomProperty(string $name): bool;

    public function setCustomProperty(string $name, mixed $value): self;

    /**
     * @return mixed
     */
    public function getCustomProperty(string $name);

    public function removeCustomProperty(string $name): void;
}
