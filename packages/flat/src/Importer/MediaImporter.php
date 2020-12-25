<?php

namespace Pushword\Flat\Importer;

use DateTimeInterface;

/**
 * Permit to find error in image or link.
 */
class MediaImporter extends AbstractImporter
{
    /** @var array */
    protected $medias;

    /** @var string */
    protected $mediaDir;

    public function setWebDir(string $webDir)
    {
        $this->mediaDir = $webDir.'/../media';
    }

    public function import(string $filePath, DateTimeInterface $lastEditDatetime)
    {
    }
}
