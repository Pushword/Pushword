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
     * @var class-string<T>
     */
    protected string $entityClass;

    protected \Doctrine\ORM\EntityManagerInterface $em;

    protected \Pushword\Core\Component\App\AppPool $apps;

    /**
     * @param class-string<T> $entityClass
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        AppPool $appPool,
        string $entityClass
    ) {
        $this->entityClass = $entityClass;
        $this->apps = $appPool;
        $this->em = $entityManager;
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
}
