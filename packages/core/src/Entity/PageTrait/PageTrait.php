<?php

namespace Pushword\Core\Entity\PageTrait;

use Cocur\Slugify\Slugify;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

trait PageTrait
{
    #[ORM\Column(type: 'string', length: 150)]
    protected string $slug = '';

    #[ORM\Column(type: 'string', length: 255)]
    protected string $h1 = '';

    /**
     * RawContent would have been a more appropriate name.
     */
    #[ORM\Column(type: 'text')]
    protected string $mainContent = '';

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected ?DateTimeInterface $publishedAt = null;

    public function __toString(): string
    {
        return trim($this->host.'/'.$this->getSlug().' ');
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
        return '' === $this->slug ? (string) $this->id : $this->slug;
    }

    public function getRealSlug(): string
    {
        return 'homepage' === $this->getSlug() ? '' : $this->getSlug();
    }

    public static function normalizeSlug(string $slug): string
    {
        $slugify = new Slugify(['regexp' => '/[^A-Za-z0-9_\/\.]+/']);
        $slug = $slugify->slugify($slug);

        return trim($slug, '/');
    }

    public function setSlug(?string $slug): self
    {
        if (null === $slug) { // work around for disabled input in sonata admin
            // if ('' === $this->slug) throw new \ErrorException('slug cant be empty.');
        } else {
            $this->slug = static::normalizeSlug($slug);
        }

        return $this;
    }

    public function getMainContent(): string
    {
        return $this->mainContent;
    }

    public function setMainContent(?string $mainContent): self
    {
        $this->mainContent = (string) $mainContent;

        return $this;
    }

    /**
     * Get the value of publishedAt.
     */
    public function getPublishedAt(bool $safe = true): ?DateTimeInterface
    {
        if ($safe && null === $this->publishedAt) {
            return new DateTime();
        }

        return $this->publishedAt;
    }

    public function setPublishedAt(DateTimeInterface $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->publishedAt <= new DateTime('now');
    }
}
