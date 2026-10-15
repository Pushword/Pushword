<?php

declare(strict_types=1);

namespace Pushword\Core\Service;

use InvalidArgumentException;
use Pushword\Core\Entity\Media;
use Pushword\Core\Utils\SafeMediaMimeType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class MediaUploadValidator
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    /**
     * The quick check on the raw upload, before it is hashed or becomes a media.
     */
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

    /**
     * The whole entity, as the admin form validates it: Media's own constraints and
     * every constraint an app maps onto it. Each interactive write path calls this
     * before its flush and refuses the write on the first violation.
     *
     * A new upload is given its target file name first, so a constraint judges the
     * name the file is about to be stored under — see Media::nameUpload().
     */
    public function validateMedia(Media $media): ConstraintViolationListInterface
    {
        $media->nameUpload();

        return $this->validator->validate($media);
    }
}
