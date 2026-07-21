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
    private string $page = '';

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $network = '';

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $format = '';

    #[ORM\Column(type: Types::STRING, length: 16)]
    private string $status = 'draft';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $plannedAt = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $spec = [];

    public function __construct()
    {
        $this->initTimestampableProperties();
    }

    public function __toString(): string
    {
        return $this->page.' · '.$this->network;
    }

    public function getPage(): string
    {
        return $this->page;
    }

    public function setPage(string $page): self
    {
        $this->page = $page;

        return $this;
    }

    public function getNetwork(): string
    {
        return $this->network;
    }

    public function setNetwork(string $network): self
    {
        $this->network = $network;

        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getPlannedAt(): ?DateTimeImmutable
    {
        return $this->plannedAt;
    }

    public function setPlannedAt(?DateTimeImmutable $plannedAt): self
    {
        $this->plannedAt = $plannedAt;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSpec(): array
    {
        return $this->spec;
    }

    /**
     * Store the carousel payload and mirror its queryable fields into columns.
     *
     * @param array<string, mixed> $spec
     */
    public function setSpec(array $spec): self
    {
        $this->spec = $spec;
        $this->page = \is_string($spec['page'] ?? null) ? $spec['page'] : $this->page;
        $this->network = \is_string($spec['network'] ?? null) ? $spec['network'] : $this->network;
        $this->format = \is_string($spec['format'] ?? null) ? $spec['format'] : $this->format;
        $this->status = \is_string($spec['status'] ?? null) ? $spec['status'] : $this->status;

        $plannedAt = $spec['plannedAt'] ?? null;
        $this->plannedAt = \is_string($plannedAt) && '' !== $plannedAt
            ? new DateTimeImmutable($plannedAt)
            : null;

        return $this;
    }
}
