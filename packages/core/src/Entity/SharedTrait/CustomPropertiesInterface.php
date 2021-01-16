<?php

namespace Pushword\Core\Entity\SharedTrait;

interface CustomPropertiesInterface
{
    public function setCustomProperties(array $customProperties): self;

    public function getCustomProperties(): array;

    public function getStandAloneCustomProperties(): string;

    public function setStandAloneCustomProperties(?string $standStandAloneCustomProperties, $merge = false): self;

    public function isStandAloneCustomProperty($name): bool;

    public function setCustomProperty($name, $value): self;

    public function getCustomProperty(string $name);

    public function removeCustomProperty($name): void;
}
