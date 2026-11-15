<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class SchemaEditorTest extends KernelTestCase
{
    public function testSchemaGenerationUsesEditorApi(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertTrue($entityManager->getConfiguration()->getUseSchemaEditorApi());

        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schema = $schemaTool->getSchemaFromMetadata($metadata);

        self::assertTrue($schema->hasTable('page'));
        self::assertTrue($schema->getTable('page')->hasColumn('slug'));

        $schemaManager = $entityManager->getConnection()->createSchemaManager();
        $pageDiff = $schemaManager->createComparator()->compareTables($schemaManager->introspectTable('page'), $schema->getTable('page'));

        self::assertTrue($pageDiff->isEmpty(), 'Schema updates must not rebuild an unchanged page table.');
    }
}
