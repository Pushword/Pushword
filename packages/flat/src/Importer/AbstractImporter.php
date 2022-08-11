<?php

namespace Pushword\Flat\Importer;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Component\App\AppPool;

/**
 * Permit to find error in image or link.
 *
 * @template T of object
 */
abstract class AbstractImporter
{
    /**
     * @param class-string<T> $entityClass
     */
    public function __construct(protected EntityManagerInterface $em, protected AppPool $apps, protected string $entityClass)
    {
    }

    abstract public function import(string $filePath, DateTimeInterface $lastEditDateTime): void;

    public function finishImport(): void
    {
        $this->em->flush();
    }

    protected static function underscoreToCamelCase(string $string): string
    {
        $str = str_replace('_', '', ucwords($string, '_'));

        return lcfirst($str);
    }

    protected function getMimeTypeFromFile(string $filePath): string
    {
        $finfo = finfo_open(\FILEINFO_MIME_TYPE);
        if (false === $finfo) {
            throw new \Exception('finfo is not working');
        }

        return (string) finfo_file($finfo, $filePath);
    }
}
