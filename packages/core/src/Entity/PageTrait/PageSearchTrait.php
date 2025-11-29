<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait PageSearchTrait
{
    /**
     * HTML Title - SEO.
     */
    #[ORM\Column(type: Types::STRING, length: 200)]
    protected string $title = '';

    /**
     * may Index ?
     */
    #[ORM\Column(type: Types::STRING, length: 50, options: ['default' => ''])]
    protected string $metaRobots = '';

    /**
     * (links improver) / Breadcrumb.
     */
    #[ORM\Column(type: Types::STRING, length: 150)]
    protected string $name = '';

    public function getTemplate(): ?string
    {
        return $this->hasCustomProperty('template') ? (string) $this->getCustomPropertyScalar('template') : null;
    }

    /*
    public function getReadableContent()
    {
        throw new Exception('You should use getContent.content');
    }

    public function getChapeau()
    {
        throw new Exception('You should use getContent');
    }*/

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = (string) $title;

        return $this;
    }

    abstract public function getCustomPropertyScalar(string $name): bool|float|int|string|null;

    abstract public function hasCustomProperty(string $name): bool;

    abstract public function setCustomProperty(string $name, mixed $value): void;

    public function getSearchExcrept(): ?string
    {
        return $this->hasCustomProperty('searchExcrept') ? (string) $this->getCustomPropertyScalar('searchExcrept') : null;
    }

    public function setSearchExcrept(?string $searchExcrept): self
    {
        $this->setCustomProperty('searchExcrept', $searchExcrept);

        return $this;
    }

    public function getMetaRobots(): string
    {
        return $this->metaRobots;
    }

    public function setMetaRobots(?string $metaRobots): self
    {
        $this->metaRobots = (string) $metaRobots;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = (string) $name;

        return $this;
    }
}
