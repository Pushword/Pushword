<?php

namespace Pushword\Core\Entity\PageTrait;

use Cocur\Slugify\Slugify;
use Doctrine\ORM\Mapping as ORM;

trait PageTrait
{
    /**
     * @ORM\Column(type="string", length=150)
     */
    protected $slug;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $h1 = '';

    /**
     * RawContent would have been a more appropriate name.
     *
     * @ORM\Column(type="text")
     */
    protected $mainContent = '';

    public function __toString()
    {
        return trim($this->host.'/'.$this->slug.' ');
    }

    public function __constructPage()
    {
        $this->slug = '';
    }

    public function getH1(): ?string
    {
        return $this->h1;
    }

    public function setH1(?string $h1): self
    {
        $this->h1 = $h1;

        return $this;
    }

    public function getSlug(): ?string
    {
        if (! $this->slug) {
            return $this->id;
        }

        return $this->slug;
    }

    public function getRealSlug(): ?string
    {
        if ('homepage' == $this->slug) {
            return '';
        }

        return $this->slug;
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
            if (null === $this->slug) {
                throw new \ErrorException('slug cant be null');
            }
        } else {
            $this->slug = static::normalizeSlug($slug); //$this->setSlug(trim($slug, '/'), true);
        }

        return $this;
    }

    public function getMainContent(): string
    {
        return $this->mainContent;
    }

    public function setMainContent(?string $mainContent): self
    {
        $this->mainContent = $mainContent ?: '';

        return $this;
    }
}
