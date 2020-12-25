<?php

namespace Pushword\Core\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Mapping\PropertyMapping;

//implements NamerInterface, ConfigurableInterface
class VichUploadPropertyNamer extends \Vich\UploaderBundle\Naming\PropertyNamer
{
    /**
     * Guess the extension of the given file.
     */
    private function getExtension(UploadedFile $file): ?string
    {
        $originalName = $file->getClientOriginalName();

        if ($extension = pathinfo($originalName, PATHINFO_EXTENSION)) {
            return strtolower($extension);
        }

        if ($extension = $file->guessExtension()) {
            return strtolower($extension);
        }

        return null;
    }

    public function name($object, PropertyMapping $mapping): string
    {
        return strtolower(parent::name($object, $mapping));
    }
}
