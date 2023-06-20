<?php

namespace Pushword\Core\Entity\MediaTrait;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Yaml\Yaml;

trait MediaNameTrait
{
    #[ORM\Column(type: 'string', length: 100, unique: true)]
    protected string $name = '';

    #[ORM\Column(type: 'text', options: ['default' => ''], nullable: true)]
    protected ?string $names = '';

    public function __toString(): string
    {
        return $this->name.' ';
    }

    public function getName(bool $onlyName = false): string
    {
        if ($onlyName) {
            return $this->name;
        }

        return '' === $this->name && null !== $this->getMediaFile() ? $this->getMediaFileName() : $this->name;
    }

    public function getNameLocalized(string $getLocalized = null, bool $onlyLocalized = false): string
    {
        $names = $this->getNamesParsed();

        return null !== $getLocalized ?
            ($names[$getLocalized] ?? ($onlyLocalized ? '' : $this->name))
            : $this->name;
    }

    /**
     * @return array<string, string>
     */
    public function getNamesParsed(): array
    {
        $this->names = (string) $this->names;
        $return = '' !== $this->names ? Yaml::parse($this->names) : [];

        if (! \is_array($return)) {
            throw new \Exception('Names malformatted');
        }

        return $return;
    }

    public function getNames(bool $yamlParsed = false): mixed
    {
        $this->names = (string) $this->names;

        return $yamlParsed && '' !== $this->names ? Yaml::parse($this->names) : $this->names;
    }

    public function setNames(?string $names): self
    {
        $this->names = (string) $names;

        return $this;
    }

    public function getNameByLocale(string $locale): string
    {
        $names = $this->getNamesParsed();

        return $names[$locale] ?? $this->getName();
    }

    public function setName(?string $name): self
    {
        $this->name = (string) $name;

        return $this;
    }
}
