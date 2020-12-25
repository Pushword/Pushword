<?php

namespace Pushword\Core\Validator\Constraints;

use Exception;
use Pushword\Core\Controller\PageController;
use Pushword\Core\Entity\Page;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

#[AutoconfigureTag('validator.constraint_validator', ['alias' => 'page_rendering'])]
class PageRenderingValidator extends ConstraintValidator
{
    public function __construct(private readonly PageController $pageController)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof PageRendering) {
            throw new UnexpectedTypeException($constraint, PageRendering::class);
        }

        if (! $value instanceof Page) {
            throw new UnexpectedTypeException($value, Page::class);
        }

        if ($value->hasRedirection()) { // si c'est une redir, on check rien
            return;
        }

        // First time, right to failed :D
        if (null === $value->getId()) {
            return;
        }

        try {
            $this->pageController->setHost($value->getHost());
            $this->pageController->showPage($value);
        } catch (Exception $exception) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            $this->context->buildViolation($exception->getMessage())
                ->addViolation();
        }
    }
}
