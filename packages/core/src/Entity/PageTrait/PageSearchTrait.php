<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\ORM\Mapping as ORM;
use Exception;

trait PageSearchTrait
{
    /**
     * may Index ?
     *
     * @ORM\Column(type="string", length=50)
     */
    protected string $metaRobots = '';

    /**
     * (links improver) / Breadcrumb.
     *
     * @ORM\Column(type="string", length=150)
     */
    protected string $name = '';

    /**
     * HTML Title - SEO.
     *
     * @ORM\Column(type="string", length=200)
     */
    protected string $title = '';

    /**
     * priority.
     *
     * @ORM\Column(type="integer")
     */
    protected int $priority = 0;

    public function getTemplate(): ?string
    {
        return $this->getCustomProperty('template');
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

    public function getSearchExcrept(): ?string
    {
        return $this->getCustomProperty('searchExcrept');
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = (string) $name;

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority($priority): self
    {
        $this->priority = (int) $priority;

        return $this;
    }
}
