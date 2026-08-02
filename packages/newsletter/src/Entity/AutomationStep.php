<?php

namespace Pushword\Newsletter\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

/** One mail in a drip: how long to wait after the previous step, then subject + body. */
#[ORM\Entity]
#[ORM\Table(name: 'newsletter_automation_step')]
class AutomationStep implements IdInterface, Stringable
{
    use IdTrait;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: Automation::class, inversedBy: 'steps')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Automation $automation = null;

    /** 0-based order within the automation. */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    public int $position = 0 {
        set(int $value) => max(0, $value);
    }

    /** Minutes to wait after enrollment (first step) or after the previous step. */
    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    public int $delayMinutes = 0 {
        set(int $value) => max(0, $value);
    }

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $subject = '';

    #[ORM\Column(type: Types::TEXT)]
    public string $bodyMarkdown = '' {
        set(?string $value) => (string) $value;
    }

    public function __toString(): string
    {
        return '' !== $this->subject ? $this->subject : 'Step '.$this->position;
    }
}
