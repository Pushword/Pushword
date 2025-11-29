<?php

namespace Pushword\Core\Entity;

use Cocur\Slugify\Slugify;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Pushword\Core\Entity\PageTrait\PageEditorTrait;
use Pushword\Core\Entity\PageTrait\PageExtendedTrait;
use Pushword\Core\Entity\PageTrait\PageI18nTrait;
use Pushword\Core\Entity\PageTrait\PageMainImageTrait;
use Pushword\Core\Entity\PageTrait\PageOpenGraphTrait;
use Pushword\Core\Entity\PageTrait\PageParentTrait;
use Pushword\Core\Entity\PageTrait\PageRedirectionTrait;
use Pushword\Core\Entity\PageTrait\PageSearchTrait;
use Pushword\Core\Entity\SharedTrait\CustomPropertiesTrait;
use Pushword\Core\Entity\SharedTrait\HostTrait;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Core\Entity\SharedTrait\Taggable;
use Pushword\Core\Entity\SharedTrait\TagsTrait;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Pushword\Core\Entity\SharedTrait\Weightable;
use Pushword\Core\Entity\SharedTrait\WeightTrait;
use Pushword\Core\Repository\PageRepository;
use Stringable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Mapping\ClassMetadata;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['host', 'slug'], errorPath: 'slug', message: 'pageSlugAlreadyUsed')]
#[ORM\Entity(repositoryClass: PageRepository::class)]
#[ORM\Table(name: 'page')]
class Page implements IdInterface, Taggable, Stringable, Weightable
{
    use IdTrait;

    use HostTrait;

    use TimestampableTrait;

    use PageEditorTrait;

    use PageExtendedTrait;

    use PageI18nTrait;

    use PageMainImageTrait;

    use PageOpenGraphTrait;

    use PageParentTrait;

    use PageRedirectionTrait;

    use PageSearchTrait;

    use TagsTrait;

    use WeightTrait;

    use CustomPropertiesTrait;

    public function __construct(bool $initDateTimeProperties = true)
    {
        if ($initDateTimeProperties) {
            $this->initTimestampableProperties();
            $this->publishedAt = new DateTime();
        }
    }

    #[ORM\Column(type: Types::STRING, length: 150)]
    protected string $slug = '';

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $h1 = '';

    /**
     * RawContent would have been a more appropriate name.
     */
    #[ORM\Column(type: Types::TEXT)]
    protected string $mainContent = '';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected ?DateTimeInterface $publishedAt = null;

    public function __toString(): string
    {
        return trim($this->host.'/'.$this->getSlug().' ');
    }

    public function getH1(): string
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
        $slug = trim($slug, '/');

        return $slug;
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
        // clean empty link added by editor.js
        $this->mainContent = preg_replace('@<a[^>]+"></a>@U', '', $this->mainContent) ?? throw new Exception();

        return $this;
    }

    public function getPublishedAt(): ?DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?DateTimeInterface $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function isPublished(): bool
    {
        return null !== $this->publishedAt && $this->publishedAt <= new DateTime('now');
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        // TODO : fix why on admin, it's not throwing exception on submit
        // $metadata->addConstraint( new PageRendering());
    }
}
