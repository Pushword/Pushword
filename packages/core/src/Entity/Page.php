<?php

namespace Pushword\Core\Entity;

use Cocur\Slugify\Slugify;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use LogicException;
use Pushword\Core\Entity\PageTrait\PageI18nTrait;
use Pushword\Core\Entity\PageTrait\PageParentTrait;
use Pushword\Core\Entity\SharedTrait\ExtensiblePropertiesTrait;
use Pushword\Core\Entity\SharedTrait\HostTrait;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Core\Entity\SharedTrait\Taggable;
use Pushword\Core\Entity\SharedTrait\TagsTrait;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Pushword\Core\Entity\SharedTrait\Weightable;
use Pushword\Core\Entity\SharedTrait\WeightTrait;
use Pushword\Core\Entity\ValueObject\OpenGraphData;
use Pushword\Core\Entity\ValueObject\PageRedirection;
use Pushword\Core\Entity\ValueObject\TwitterCardData;
use Pushword\Core\Repository\PageRepository;
use Stringable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['host', 'slug'], message: 'pageSlugAlreadyUsed', errorPath: 'slug')]
#[ORM\Entity(repositoryClass: PageRepository::class)]
#[ORM\Table(name: 'page')]
#[ORM\UniqueConstraint(name: 'unique_slug_host', columns: ['slug', 'host'])]
class Page implements IdInterface, Taggable, Stringable, Weightable
{
    use IdTrait;

    use HostTrait;

    use TimestampableTrait;

    use PageI18nTrait;

    use PageParentTrait;

    use TagsTrait;

    use WeightTrait;

    use ExtensiblePropertiesTrait;

    // --- Editor properties (inlined from PageEditorTrait) ---

    #[ORM\ManyToOne(targetEntity: User::class)]
    public ?User $editedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    public ?User $createdBy = null;

    #[ORM\Column(type: Types::TEXT, options: ['default' => ''])]
    public string $editMessage = '' {
        set(?string $value) => $this->editMessage = (string) $value;
    }

    // --- Extended page (inlined from PageExtendedTrait) ---

    #[ORM\ManyToOne(targetEntity: self::class)]
    public ?Page $extendedPage = null;

    // --- Main image (inlined from PageMainImageTrait) ---

    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'], inversedBy: 'mainImagePages')]
    protected ?Media $mainImage = null;

    public function getMainImage(): ?Media
    {
        return $this->mainImage;
    }

    public function setMainImage(?Media $media): self
    {
        if (null !== $media && null === $media->getWidth()) {
            throw new Exception('mainImage must be an Image. Media imported is not an image');
        }

        $this->mainImage = $media;

        return $this;
    }

    // --- Core page properties ---

    #[ORM\Column(type: Types::STRING, length: 150)]
    public string $slug = '' {
        get => '' === $this->slug ? (string) $this->id : $this->slug;
        set(?string $value) {
            $this->slug = null === $value ? '' : static::normalizeSlug($value);
        }
    }

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $h1 = '' {
        set(?string $value) => $this->h1 = (string) $value;
    }

    /** RawContent would have been a more appropriate name. */
    #[ORM\Column(type: Types::TEXT)]
    protected string $mainContent = '';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    public ?DateTimeInterface $publishedAt = null;

    // --- Search/SEO properties (inlined from PageSearchTrait) ---

    /** HTML Title - SEO */
    #[ORM\Column(type: Types::STRING, length: 200)]
    public string $title = '' {
        set(?string $value) => $this->title = (string) $value;
    }

    /** May index? */
    #[ORM\Column(type: Types::STRING, length: 50, options: ['default' => ''])]
    public string $metaRobots = '' {
        set(?string $value) => $this->metaRobots = (string) $value;
    }

    /** Links improver / Breadcrumb */
    #[ORM\Column(type: Types::STRING, length: 150)]
    public string $name = '' {
        set(?string $value) => $this->name = (string) $value;
    }

    /** Template - promoted to real column from customProperties */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $template = null;

    // --- Redirection (computed, not persisted) ---

    private ?PageRedirection $redirectionCache = null;

    private bool $redirectionChecked = false;

    // --- Constructor ---

    public function __construct(bool $initDateTimeProperties = true)
    {
        if ($initDateTimeProperties) {
            $this->initTimestampableProperties();
            $this->publishedAt = new DateTime();
        }
    }

    public function __toString(): string
    {
        return trim($this->host.'/'.$this->slug.' ');
    }

    // --- Getters/setters for hooked properties (for caller compatibility) ---

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getH1(): string
    {
        return $this->h1;
    }

    public function setH1(?string $h1): self
    {
        $this->h1 = $h1;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getMetaRobots(): string
    {
        return $this->metaRobots;
    }

    public function setMetaRobots(?string $metaRobots): self
    {
        $this->metaRobots = $metaRobots;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

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

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getEditMessage(): string
    {
        return $this->editMessage;
    }

    public function getRealSlug(): string
    {
        return 'homepage' === $this->slug ? '' : $this->slug;
    }

    public static function normalizeSlug(string $slug): string
    {
        $slugify = new Slugify(['regexp' => '/[^A-Za-z0-9_\/\.]+/']);
        $slug = $slugify->slugify($slug);

        return trim($slug, '/');
    }

    public function getMainContent(): string
    {
        return $this->mainContent;
    }

    public function setMainContent(?string $mainContent): self
    {
        $this->mainContent = (string) $mainContent;
        $this->mainContent = trim($this->mainContent);
        $this->mainContent = preg_replace('@<a[^>]+"></a>@U', '', $this->mainContent) ?? throw new Exception();

        $this->redirectionCache = null;
        $this->redirectionChecked = false;

        return $this;
    }

    public function isPublished(): bool
    {
        return null !== $this->publishedAt && $this->publishedAt <= new DateTime('now');
    }

    // --- Template ---

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template): self
    {
        $this->template = $template;

        return $this;
    }

    // --- Search excerpt (fixed typo: searchExcrept -> searchExcerpt) ---

    public function getSearchExcerpt(): ?string
    {
        return $this->hasCustomProperty('searchExcerpt')
            ? (string) $this->getCustomPropertyScalar('searchExcerpt')
            : null;
    }

    public function setSearchExcerpt(?string $searchExcerpt): self
    {
        $this->setCustomProperty('searchExcerpt', $searchExcerpt);

        return $this;
    }

    // --- Open Graph (explicit getters, stored in customProperties) ---

    public function getOgTitle(): ?string
    {
        return $this->hasCustomProperty('ogTitle') ? (string) $this->getCustomPropertyScalar('ogTitle') : null;
    }

    public function setOgTitle(?string $ogTitle): self
    {
        $this->setCustomProperty('ogTitle', $ogTitle);

        return $this;
    }

    public function getOgDescription(): ?string
    {
        return $this->hasCustomProperty('ogDescription') ? (string) $this->getCustomPropertyScalar('ogDescription') : null;
    }

    public function setOgDescription(?string $ogDescription): self
    {
        $this->setCustomProperty('ogDescription', $ogDescription);

        return $this;
    }

    public function getOgImage(): ?string
    {
        return $this->hasCustomProperty('ogImage') ? (string) $this->getCustomPropertyScalar('ogImage') : null;
    }

    public function setOgImage(?string $ogImage): self
    {
        $this->setCustomProperty('ogImage', $ogImage);

        return $this;
    }

    // --- Twitter Card (explicit getters, stored in customProperties) ---

    public function getTwitterCard(): ?string
    {
        return $this->hasCustomProperty('twitterCard') ? (string) $this->getCustomPropertyScalar('twitterCard') : null;
    }

    public function setTwitterCard(?string $twitterCard): self
    {
        $this->setCustomProperty('twitterCard', $twitterCard);

        return $this;
    }

    public function getTwitterSite(): ?string
    {
        return $this->hasCustomProperty('twitterSite') ? (string) $this->getCustomPropertyScalar('twitterSite') : null;
    }

    public function setTwitterSite(?string $twitterSite): self
    {
        $this->setCustomProperty('twitterSite', $twitterSite);

        return $this;
    }

    public function getTwitterCreator(): ?string
    {
        return $this->hasCustomProperty('twitterCreator') ? (string) $this->getCustomPropertyScalar('twitterCreator') : null;
    }

    public function setTwitterCreator(?string $twitterCreator): self
    {
        $this->setCustomProperty('twitterCreator', $twitterCreator);

        return $this;
    }

    public function getOpenGraphData(): OpenGraphData
    {
        return new OpenGraphData(
            title: $this->getOgTitle(),
            description: $this->getOgDescription(),
            image: $this->getOgImage(),
        );
    }

    public function getTwitterCardData(): TwitterCardData
    {
        return new TwitterCardData(
            card: $this->getTwitterCard(),
            site: $this->getTwitterSite(),
            creator: $this->getTwitterCreator(),
        );
    }

    // --- Redirection (uses PageRedirection value object) ---

    public function getRedirection(): ?PageRedirection
    {
        if (! $this->redirectionChecked) {
            $this->redirectionCache = PageRedirection::fromContent($this->mainContent);
            $this->redirectionChecked = true;
        }

        return $this->redirectionCache;
    }

    public function hasRedirection(): bool
    {
        return null !== $this->getRedirection();
    }

    public function getRedirectionUrl(): string
    {
        $redirection = $this->getRedirection();

        return null !== $redirection ? $redirection->url : throw new LogicException('Check hasRedirection() before calling getRedirectionUrl()');
    }

    public function getRedirectionCode(): int
    {
        $redirection = $this->getRedirection();

        return null !== $redirection ? $redirection->code : throw new LogicException('Check hasRedirection() before calling getRedirectionCode()');
    }

    // --- Managed property keys (OG/Twitter fields are managed by dedicated form fields) ---

    /** @return string[] */
    public function getManagedPropertyKeys(): array
    {
        return [
            ...array_keys($this->runtimeManagedKeys),
            'ogTitle', 'ogDescription', 'ogImage',
            'twitterCard', 'twitterSite', 'twitterCreator',
            'searchExcerpt',
        ];
    }
}
