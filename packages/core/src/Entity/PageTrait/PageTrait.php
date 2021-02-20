<?php

namespace Pushword\Core\Entity\PageTrait;

use Cocur\Slugify\Slugify;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

trait PageTrait
{
    /**
     * @ORM\Column(type="string", length=150)
     */
    protected string $slug = '';

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected string $h1 = '';

    /**
     * RawContent would have been a more appropriate name.
     *
     * @ORM\Column(type="text")
     */
    protected $mainContent = '';

    /**
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    protected DateTimeInterface $publishedAt;

    public function __toString()
    {
        return trim($this->host.'/'.$this->slug.' ');
    }

    public function getH1(): ?string
    {
        return $this->h1;
    }

    public function setH1(?string $h1): self
    {
        $this->h1 = (string) $h1;

        return $this;
    }

    public function getSlug(): string
    {
        return ! $this->slug ? (string) $this->id : $this->slug;
    }

    public function getRealSlug(): string
    {
        return 'homepage' == $this->getSlug() ? '' : $this->getSlug();
    }

    public static function normalizeSlug(string $slug): string
    {
        $slugifier = new Slugify(['regexp' => '/[^A-Za-z0-9_\/\.]+/']);
        $slug = $slugifier->slugify($slug);
        $slug = trim($slug, '/');

        return $slug;
    }

    public function setSlug($slug, $set = false): self
    {
        if (true === $set) {
            $this->slug = $slug;
        } elseif (null === $slug) { // work around for disabled input in sonata admin
            //if ('' === $this->slug) throw new \ErrorException('slug cant be empty.');
        } else {
            $this->slug = static::normalizeSlug($slug); //$this->setSlug(trim($slug, '/'), true);
        }

        return $this;
    }

    /** @return string */
    public function getMainContent(): string
    {
        return $this->mainContent;
    }

    /**
     * @param string $mainContent
     */
    public function setMainContent($mainContent): self
    {
        $this->mainContent = (string) $mainContent;

        return $this;
    }

    /**
     * Get the value of publishedAt.
     */
    public function getPublishedAt(): DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(DateTimeInterface $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }
}
