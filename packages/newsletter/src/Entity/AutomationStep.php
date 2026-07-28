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
    private ?Automation $automation = null;

    /** 0-based order within the automation. */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $position = 0;

    /** Minutes to wait after enrollment (first step) or after the previous step. */
    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $delayMinutes = 0;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $subject = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $bodyMarkdown = '';

    public function __toString(): string
    {
        return '' !== $this->subject ? $this->subject : 'Step '.$this->position;
    }

    public function getAutomation(): ?Automation
    {
        return $this->automation;
    }

    public function setAutomation(?Automation $automation): self
    {
        $this->automation = $automation;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = max(0, $position);

        return $this;
    }

    public function getDelayMinutes(): int
    {
        return $this->delayMinutes;
    }

    public function setDelayMinutes(int $delayMinutes): self
    {
        $this->delayMinutes = max(0, $delayMinutes);

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBodyMarkdown(): string
    {
        return $this->bodyMarkdown;
    }

    public function setBodyMarkdown(?string $bodyMarkdown): self
    {
        $this->bodyMarkdown = (string) $bodyMarkdown;

        return $this;
    }
}
