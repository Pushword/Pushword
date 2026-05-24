<?php

namespace Pushword\Snippet\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\ExtensiblePropertiesTrait;
use Pushword\Core\Entity\SharedTrait\HostTrait;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Core\Entity\SharedTrait\Taggable;
use Pushword\Core\Entity\SharedTrait\TagsTrait;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Pushword\Snippet\Repository\SnippetRepository;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Editor-owned reusable content fragment, referenced by `slug` from page content
 * via the `snippet('slug')` Twig function. Per-host (one row per locale/site),
 * stored as Markdown, rendered through the same filter pipeline as a Page.
 *
 * Traits: IdTrait (PK), HostTrait (per-locale host), TimestampableTrait,
 *   TagsTrait (organisation), ExtensiblePropertiesTrait (custom key-value bag).
 */
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: SnippetRepository::class)]
#[ORM\Table(name: 'snippet')]
#[ORM\UniqueConstraint(name: 'unique_snippet_slug_host', columns: ['slug', 'host'])]
#[ORM\Index(name: 'idx_snippet_slug', columns: ['slug'])]
class Snippet implements IdInterface, Taggable, Stringable
{
    use ExtensiblePropertiesTrait;
    use HostTrait;
    use IdTrait;
    use TagsTrait;
    use TimestampableTrait;

    #[Assert\Regex(pattern: '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', message: 'snippet.slug.invalid')]
    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $slug = '';

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $name = '';

    #[ORM\Column(type: Types::TEXT)]
    protected string $content = '';

    public function __construct()
    {
        $this->initTimestampableProperties();
    }

    public function __toString(): string
    {
        return '' !== $this->name ? $this->name : $this->slug;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = self::normalizeSlug($slug);

        return $this;
    }

    public static function normalizeSlug(string $slug): string
    {
        return trim(strtolower($slug));
    }

    public function getName(): string
    {
        return '' !== $this->name ? $this->name : $this->slug;
    }

    public function setName(?string $name): self
    {
        $this->name = (string) $name;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = (string) $content;

        return $this;
    }
}
