<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Validator;

use Pushword\Repurpose\Model\FontPairing;
use Pushword\Repurpose\Service\FontResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class InstalledFontsValidator extends ConstraintValidator
{
    public function __construct(private readonly FontResolver $fonts)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof InstalledFonts) {
            throw new UnexpectedTypeException($constraint, InstalledFonts::class);
        }

        if (! \is_string($value) && ! $value instanceof FontPairing) {
            return;
        }

        foreach ($this->fonts->faces($value) as $role => $face) {
            // A malformed family name is the pairing's own violation.
            if (1 !== preg_match(FontPairing::FAMILY, $face['family']) || $this->fonts->hasFace($face['family'], $face['weight'])) {
                continue;
            }

            $violation = $this->context->buildViolation($constraint->message)
                ->setParameter('%family%', $face['family'])
                ->setParameter('%weight%', (string) $face['weight'])
                ->setParameter('%role%', $role);
            if ($value instanceof FontPairing) {
                $violation->atPath($role);
            }

            $violation->addViolation();
        }
    }
}
