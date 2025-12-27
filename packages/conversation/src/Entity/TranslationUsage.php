<?php

namespace Pushword\Conversation\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'translation_usage')]
#[ORM\UniqueConstraint(name: 'service_month_idx', columns: ['service', 'month'])]
class TranslationUsage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    public string $service = '';

    /** @var string Format: YYYY-MM */
    #[ORM\Column(type: Types::STRING, length: 7)]
    public string $month = '';

    #[ORM\Column(type: Types::INTEGER)]
    public int $characterCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function addCharacters(int $count): self
    {
        $this->characterCount += $count;
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }
}
