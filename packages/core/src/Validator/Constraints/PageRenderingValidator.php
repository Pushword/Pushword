<?php

namespace Pushword\Core\Validator\Constraints;

use Pushword\Core\Entity\PageInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PageRenderingValidator extends ConstraintValidator
{
    private \Pushword\Core\Controller\PageController $pageController;

    public function __construct(\Pushword\Core\Controller\PageController $pageController)
    {
        $this->pageController = $pageController;
    }

    public function validate($value, Constraint $constraint): void
    {
        if (! $constraint instanceof PageRendering) {
            throw new UnexpectedTypeException($constraint, PageRendering::class);
        }

        if (! $value instanceof PageInterface) {
            throw new UnexpectedTypeException($value, PageInterface::class);
        }

        if ($value->hasRedirection()) { // si c'est une redir, on check rien
            return;
        }

        // First time, right to failed :D
        if (null === $value->getId()) {
            return;
        }

        try {
            $this->pageController->setApp($value->getHost());
            $this->pageController->showPage($value);
        } catch (\Exception $exception) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            $this->context->buildViolation($exception->getMessage())
                ->addViolation();
        }
    }
}
