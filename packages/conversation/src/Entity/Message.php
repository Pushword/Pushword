<?php

namespace Pushword\Conversation\Entity;

use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Conversation\Repository\MessageRepository;
use Pushword\Core\Entity\SharedTrait\ExtensiblePropertiesTrait;
use Pushword\Core\Entity\SharedTrait\HostTrait;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Core\Entity\SharedTrait\MediaListTrait;
use Pushword\Core\Entity\SharedTrait\Taggable;
use Pushword\Core\Entity\SharedTrait\TagsTrait;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Pushword\Core\Entity\SharedTrait\UuidTrait;
use Pushword\Core\Entity\SharedTrait\WeightTrait;
use Stringable;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'message_type', type: Types::INTEGER, options: ['default' => 0])]
#[ORM\DiscriminatorMap([
    0 => Message::class,
    1 => Review::class,
])]
class Message implements Stringable, Taggable, IdInterface
{
    use IdTrait;

    use TimestampableTrait;

    use HostTrait;

    use WeightTrait;

    use TagsTrait;

    use MediaListTrait;

    use ExtensiblePropertiesTrait;

    use UuidTrait;

    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    public ?string $authorName = '';

    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    public ?string $authorEmail = '';

    /**
     * Anonymized visitor IP, stored as text: ip2long() has no IPv6 counterpart,
     * and 45 chars is the longest an IPv6 address can get.
     *
     * Writing null leaves the stored value untouched — see {@see setAuthorIpRaw()},
     * the only writer, which drops anything that is not a valid IP.
     */
    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    public ?string $authorIp = null {
        set(?string $value) => $this->authorIp = $value ?? $this->authorIp;
    }

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 200000, minMessage: 'conversationContentShort', maxMessage: 'conversation.content.long')]
    protected ?string $content = null;

    /**
     * Identifier referring (most of time, URI).
     */
    #[ORM\Column(type: Types::TEXT, options: ['default' => ''])]
    public string $referring = '' {
        set(?string $value) => $this->referring = (string) $value;
    }

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $publishedAt = null;

    /**
     * Deletion tombstone: the row is kept (and synced through the flat CSV) so
     * the deletion propagates to databases that no longer travel together — a
     * hard delete would resurrect on the next merge. Import never clears it.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $deletedAt = null;

    #[ORM\Column(type: Types::STRING, length: 5, nullable: true)]
    public ?string $locale = null;

    public function __construct()
    {
        $this->initTimestampableProperties();
        $this->getOrGenerateUuid();
    }

    public function softDelete(): void
    {
        $this->deletedAt = new DateTime();
        // Bump updatedAt so the sync sees the row as changed and exports it.
        $this->updatedAt = new DateTime();
    }

    /**
     * Set message content.
     */
    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get message content. Stays a method: the column is nullable, and a get hook
     * cannot narrow `?string` to the string every caller relies on.
     */
    public function getContent(): string
    {
        return $this->content ?? '';
    }

    /**
     * Anonymize an untrusted client IP (IPv4 or IPv6) before storing it.
     */
    public function setAuthorIpRaw(string $authorIp): self
    {
        $trimmed = trim($authorIp);
        if (false === filter_var($trimmed, \FILTER_VALIDATE_IP)) {
            return $this;
        }

        $this->authorIp = IpUtils::anonymize($trimmed);

        return $this;
    }

    public function getAuthorIpRaw(): string
    {
        return $this->authorIp ?? '';
    }

    public function __toString(): string
    {
        return ($this->id ?? '0').' ';
    }
}
