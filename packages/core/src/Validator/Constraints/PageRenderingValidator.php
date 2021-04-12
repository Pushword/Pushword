<?php

namespace Pushword\Core\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PageRenderingValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (! $constraint instanceof PageRendering) {
            throw new UnexpectedTypeException($constraint, PageRendering::class);
        }

        if (false !== $value->getRedirection()) { // si c'est une redir, on check rien
            return;
        }

        // First time, right to failed :D
        if (null === $value->getContent()) {
            return;
        }

        try {
            $value->getContent()->getBody();
        } catch (\Exception $exception) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            $this->context->buildViolation($exception->getMessage())
                ->addViolation();
        }
    }
}
