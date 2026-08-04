<?php

namespace Pushword\Core\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Repository\MediaUsageRepository;

/**
 * One edge of "where is this media used": a page references a media, and how.
 *
 * Written through DBAL by {@see \Pushword\Core\Service\MediaUsageTracker} — a page
 * save rewrites a handful of rows and hydrating entities for that is pure overhead —
 * and read through DQL: the admin "not referenced by a page" filter,
 * `pw:media:clean-unused`, `pw:ai-index`.
 *
 * `source` is what lets a consumer explain *why* a media is used, and what makes a
 * fourth source (a template, one day) an added value rather than a schema change.
 *
 * A media referenced only from a Twig template — a navbar logo, an OG fallback — has
 * no row here: nothing scans templates. "Unused" therefore means "not referenced by a
 * page", never "safe to delete", and every consumer has to word it that way.
 */
#[ORM\Entity(repositoryClass: MediaUsageRepository::class)]
#[ORM\Table(name: 'media_usage')]
#[ORM\UniqueConstraint(name: 'uniq_media_usage', columns: ['media_id', 'page_id', 'source'])]
#[ORM\Index(name: 'idx_media_usage_page', columns: ['page_id'])]
class MediaUsage
{
    /** Referenced from the page's Markdown body. */
    public const string SOURCE_CONTENT = 'content';

    /** The page's `mainImage` relation. */
    public const string SOURCE_MAIN_IMAGE = 'main_image';

    /** Referenced from one of the page's custom properties. */
    public const string SOURCE_PROPERTY = 'property';

    #[ORM\Id, ORM\Column(type: Types::INTEGER), ORM\GeneratedValue(strategy: 'AUTO')]
    public private(set) ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Media::class)]
        #[ORM\JoinColumn(name: 'media_id', nullable: false, onDelete: 'CASCADE')]
        public Media $media,
        #[ORM\ManyToOne(targetEntity: Page::class)]
        #[ORM\JoinColumn(name: 'page_id', nullable: false, onDelete: 'CASCADE')]
        public Page $page,
        #[ORM\Column(type: Types::STRING, length: 16)]
        public string $source = self::SOURCE_CONTENT,
    ) {
    }
}
