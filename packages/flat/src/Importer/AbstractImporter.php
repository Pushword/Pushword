<?php

namespace Pushword\Flat\Importer;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Component\App\AppPool;

/**
 * Permit to find error in image or link.
 */
abstract class AbstractImporter
{
    /** @var string */
    protected $entityClass;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var AppPool */
    protected $apps;

    public function __construct(
        EntityManagerInterface $em,
        AppPool $apps,
        string $entityClass
    ) {
        $this->entityClass = $entityClass;
        $this->apps = $apps;
        $this->em = $em;
    }

    abstract public function import(string $filePath, DateTimeInterface $lastEditDatetime);

    public function finishImport()
    {
        $this->em->flush();
    }
}
