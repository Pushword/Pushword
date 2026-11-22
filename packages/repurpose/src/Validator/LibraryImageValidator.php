<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Validator;

use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class LibraryImageValidator extends ConstraintValidator
{
    public function __construct(private readonly MediaRepository $mediaRepository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof LibraryImage) {
            throw new UnexpectedTypeException($constraint, LibraryImage::class);
        }

        // An empty name is NotBlank's violation.
        if (! \is_string($value) || '' === $value) {
            return;
        }

        $media = $this->mediaRepository->findOneByFileName($value);
        if ($media instanceof Media && $media->isImage()) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('%media%', $value)
            ->addViolation();
    }
}
