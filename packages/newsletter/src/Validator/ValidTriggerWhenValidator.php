<?php

namespace Pushword\Newsletter\Validator;

use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Segment\SegmentException;
use Pushword\Newsletter\Trigger\TriggerSourceRegistry;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Parses an automation's trigger rule against the vocabulary its source speaks,
 * and stores the result — the same round trip the contact-side rules make in
 * {@see Automation::validateCriteria()}, one step later because it needs the
 * registry.
 *
 * The source is validated first and by name: a rule cannot be read at all until
 * it is known which language it is in, and an automation naming a source nothing
 * answers to is a form error here rather than a silent no-op on the next tick.
 */
final class ValidTriggerWhenValidator extends ConstraintValidator
{
    public function __construct(private readonly TriggerSourceRegistry $registry)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof ValidTriggerWhen) {
            throw new UnexpectedValueException($constraint, ValidTriggerWhen::class);
        }

        if (! $value instanceof Automation) {
            return;
        }

        $source = $this->registry->for($value);

        if (null === $source) {
            $this->context->buildViolation($constraint->unknownSource)
                ->setParameter('{{ source }}', $value->source)
                ->setParameter('{{ known }}', implode(', ', $this->registry->names()))
                ->atPath('source')
                ->addViolation();

            return;
        }

        $json = $value->pendingCriteriaJson('triggerWhen');

        if (null === $json) {
            return;
        }

        $criteria = $source->criteria();

        try {
            $value->triggerWhen = $criteria::fromJson($json);
            $value->criteriaJsonParsed('triggerWhen');
        } catch (SegmentException $segmentException) {
            $this->context->buildViolation($segmentException->getMessage())
                ->atPath('triggerWhenAsJson')
                ->addViolation();
        }
    }
}
