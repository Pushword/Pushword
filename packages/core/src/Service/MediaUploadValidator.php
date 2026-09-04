<?php

namespace Pushword\Core\Service;

use InvalidArgumentException;
use Pushword\Core\Utils\SafeMediaMimeType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class MediaUploadValidator
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    public function validate(UploadedFile $file): void
    {
        $violations = $this->validator->validate($file, new File(
            extensions: SafeMediaMimeType::EXTENSIONS,
            extensionsMessage: 'This file format is not allowed.',
        ));

        if (0 === $violations->count()) {
            return;
        }

        throw new InvalidArgumentException((string) $violations->get(0)->getMessage());
    }
}
