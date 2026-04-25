<?php

namespace Pushword\Core\Entity\SharedTrait;

interface CustomPropertiesInterface
{
    /** @param array<mixed> $customProperties */
    public function setCustomProperties(array $customProperties): self;

    /** @return array<mixed> */
    public function getCustomProperties(): array;

    public function getUnmanagedPropertiesAsYaml(): string;

    public function setUnmanagedPropertiesFromYaml(?string $yaml, bool $merge = false): self;

    public function isManagedProperty(string $name): bool;

    /** @return string[] */
    public function getManagedPropertyKeys(): array;

    public function setCustomProperty(string $name, mixed $value): void;

    public function getCustomProperty(string $name): mixed;

    public function getCustomPropertyScalar(string $name): bool|float|int|string|null;

    public function removeCustomProperty(string $name): void;
}
