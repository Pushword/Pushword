<?php

namespace Pushword\Newsletter\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Pushword\Newsletter\Repository\AutomationRepository;
use Pushword\Newsletter\Segment\SegmentCriteria;
use Pushword\Newsletter\Segment\SegmentException;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A criteria-driven drip. Every tick, contacts matching {@see self::$enrollWhen}
 * and registered after {@see self::$enrollFrom} are enrolled and then receive the
 * ordered steps. {@see self::$stopWhen} is re-checked before each step, so a
 * contact whose situation changed stops mid-sequence.
 *
 * "Two mails after subscription" is an empty `enrollWhen` (every subscribed
 * contact) with two steps.
 */
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: AutomationRepository::class)]
#[ORM\Table(name: 'newsletter_automation')]
class Automation implements IdInterface, Stringable
{
    use IdTrait;
    use TimestampableTrait;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: Audience::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Audience $audience = null;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name = '';

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $enabled = true;

    /**
     * Who gets enrolled; an empty list means every subscribed contact of the
     * audience.
     *
     * @var array<int, array<string, mixed>>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $enrollWhen = [];

    /**
     * Re-checked before each step: a match stops the enrollment. Empty means the
     * sequence always runs to its end.
     *
     * @var array<int, array<string, mixed>>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $stopWhen = [];

    /**
     * Contacts registered before this date are never enrolled. Set at creation
     * so switching on an automation with a wide `enrollWhen` cannot mail an
     * entire existing base at once. A column rather than a criterion, because
     * this must not be possible to forget.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $enrollFrom;

    /** @var Collection<int, AutomationStep> */
    #[ORM\OneToMany(targetEntity: AutomationStep::class, mappedBy: 'automation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $steps;

    /** @var array<string, string> the admin's raw JSON, pending validation */
    private array $criteriaJson = [];

    public function __construct()
    {
        $this->enrollFrom = new DateTimeImmutable();
        $this->steps = new ArrayCollection();
        $this->initTimestampableProperties();
    }

    public function __toString(): string
    {
        return '' !== $this->name ? $this->name : 'Automation #'.($this->id ?? '?');
    }

    public function getAudience(): ?Audience
    {
        return $this->audience;
    }

    public function setAudience(?Audience $audience): self
    {
        $this->audience = $audience;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getEnrollWhen(): array
    {
        return $this->enrollWhen;
    }

    /** @param array<int, array<string, mixed>> $enrollWhen */
    public function setEnrollWhen(array $enrollWhen): self
    {
        $this->enrollWhen = array_values($enrollWhen);

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getStopWhen(): array
    {
        return $this->stopWhen;
    }

    /** @param array<int, array<string, mixed>> $stopWhen */
    public function setStopWhen(array $stopWhen): self
    {
        $this->stopWhen = array_values($stopWhen);

        return $this;
    }

    /**
     * The admin's editing surface for both criteria lists. Parsing happens
     * during validation so a malformed expression is a form error, not a 500.
     */
    public function getEnrollWhenAsJson(): string
    {
        return $this->criteriaJson['enrollWhen'] ?? SegmentCriteria::toJson($this->enrollWhen);
    }

    public function setEnrollWhenAsJson(?string $json): self
    {
        $this->criteriaJson['enrollWhen'] = (string) $json;

        return $this;
    }

    public function getStopWhenAsJson(): string
    {
        return $this->criteriaJson['stopWhen'] ?? SegmentCriteria::toJson($this->stopWhen);
    }

    public function setStopWhenAsJson(?string $json): self
    {
        $this->criteriaJson['stopWhen'] = (string) $json;

        return $this;
    }

    #[Assert\Callback]
    public function validateCriteria(ExecutionContextInterface $executionContext): void
    {
        foreach (['enrollWhen', 'stopWhen'] as $key) {
            if (! isset($this->criteriaJson[$key])) {
                continue;
            }

            try {
                $parsed = SegmentCriteria::fromJson($this->criteriaJson[$key]);

                if ('enrollWhen' === $key) {
                    $this->enrollWhen = $parsed;
                } else {
                    $this->stopWhen = $parsed;
                }

                unset($this->criteriaJson[$key]);
            } catch (SegmentException $segmentException) {
                $executionContext->buildViolation($segmentException->getMessage())
                    ->atPath($key.'AsJson')
                    ->addViolation();
            }
        }
    }

    public function getEnrollFrom(): DateTimeImmutable
    {
        return $this->enrollFrom;
    }

    public function setEnrollFrom(DateTimeImmutable $enrollFrom): self
    {
        $this->enrollFrom = $enrollFrom;

        return $this;
    }

    /** @return Collection<int, AutomationStep> */
    public function getSteps(): Collection
    {
        return $this->steps;
    }

    /** @return list<AutomationStep> */
    public function getOrderedSteps(): array
    {
        $steps = $this->steps->toArray();
        usort($steps, static fn (AutomationStep $a, AutomationStep $b): int => $a->getPosition() <=> $b->getPosition());

        return $steps;
    }

    public function getStep(int $position): ?AutomationStep
    {
        return $this->getOrderedSteps()[$position] ?? null;
    }

    public function countSteps(): int
    {
        return $this->steps->count();
    }

    public function addStep(AutomationStep $step): self
    {
        if (! $this->steps->contains($step)) {
            $this->steps->add($step);
            $step->setAutomation($this);
        }

        return $this;
    }

    public function removeStep(AutomationStep $step): self
    {
        if ($this->steps->removeElement($step) && $step->getAutomation() === $this) {
            $step->setAutomation(null);
        }

        return $this;
    }
}
