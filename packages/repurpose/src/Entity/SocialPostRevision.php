<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Repurpose\Doctrine\CompressedJsonType;
use Pushword\Repurpose\Repository\SocialPostRevisionRepository;

#[ORM\Entity(repositoryClass: SocialPostRevisionRepository::class)]
#[ORM\Table(name: 'social_post_revision')]
class SocialPostRevision
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: SocialPost::class)]
    #[ORM\JoinColumn(name: 'post_id', nullable: false, onDelete: 'CASCADE')]
    public SocialPost $post;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    public int $version;

    #[ORM\Column(name: 'saved_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $savedAt;

    /** @var array<string, mixed> */
    #[ORM\Column(type: CompressedJsonType::NAME)]
    public array $spec;
}
