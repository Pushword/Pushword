<?php

namespace Pushword\Repurpose\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\HostTrait;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Pushword\Repurpose\Repository\SocialPostRepository;
use Stringable;

/**
 * A carousel derived from a page, for one network. Persisted as a row (so the
 * admin can list "everything planned this week" across hosts, filter by status and
 * enforce per-network uniqueness) *and* mirrored to a flat JSON file at
 * `content/{host}/social-post/{page}/{network}.json` by {@see \Pushword\Repurpose\Sync\SocialPostSync}
 * so it round-trips through `pw:flat:sync` on flat-file sites.
 *
 * The `spec` JSON is the authoritative carousel payload (the same shape the schema
 * and validator describe); `page`, `network`, `format`, `status` and `plannedAt`
 * are denormalised columns kept in sync from it for querying.
 *
 * The page is addressed by its natural key (host + slug), never an FK — consistent
 * with how the whole CMS and the API address pages, and robust across renames.
 */
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: SocialPostRepository::class)]
#[ORM\Table(name: 'social_post')]
#[ORM\UniqueConstraint(name: 'unique_social_post', columns: ['host', 'page', 'network'])]
#[ORM\Index(name: 'idx_social_post_status', columns: ['status'])]
class SocialPost implements IdInterface, Stringable
{
    use HostTrait;
    use IdTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $page = '';

    #[ORM\Column(type: Types::STRING, length: 32)]
    public string $network = '';

    #[ORM\Column(type: Types::STRING, length: 32)]
    public string $format = '';

    #[ORM\Column(type: Types::STRING, length: 16)]
    public string $status = 'draft';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $plannedAt = null;

    /**
     * Writing the payload mirrors its queryable fields into the columns above.
     * Doctrine writes the backing store directly, so loading a row never re-derives
     * them — only an application-level write does.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    public array $spec = [] {
        set(array $value) {
            $this->spec = $value;
            $this->page = \is_string($value['page'] ?? null) ? $value['page'] : $this->page;
            $this->network = \is_string($value['network'] ?? null) ? $value['network'] : $this->network;
            $this->format = \is_string($value['format'] ?? null) ? $value['format'] : $this->format;
            $this->status = \is_string($value['status'] ?? null) ? $value['status'] : $this->status;

            $plannedAt = $value['plannedAt'] ?? null;
            $this->plannedAt = \is_string($plannedAt) && '' !== $plannedAt
                ? new DateTimeImmutable($plannedAt)
                : null;
        }
    }

    public function __construct()
    {
        $this->initTimestampableProperties();
    }

    public function __toString(): string
    {
        return $this->page.' · '.$this->network;
    }
}
