<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\EventListener;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\TestCase;
use Pushword\Core\EventListener\SchemaForeignKeyNamesListener;

final class SchemaForeignKeyNamesListenerTest extends TestCase
{
    public function testGeneratedNamesAreStableAndExplicitNamesArePreserved(): void
    {
        $unnamed = ForeignKeyConstraint::editor()
            ->setUnquotedReferencingColumnNames('parent_id')
            ->setUnquotedReferencedTableName('source')
            ->setUnquotedReferencedColumnNames('id')
            ->setOnDeleteAction(ReferentialAction::CASCADE)
            ->create();
        $named = $unnamed->edit()
            ->setUnquotedName('custom_fk')
            ->setUnquotedReferencingColumnNames('secondary_id')
            ->create();

        $tableEditor = Table::editor()->setUnquotedName('source');
        foreach (['id', 'parent_id', 'secondary_id'] as $columnName) {
            $tableEditor->addColumn(Column::editor()->setUnquotedName($columnName)->setTypeName(Types::INTEGER)->create());
        }

        $schema = Schema::editor()->addTable($tableEditor->setForeignKeyConstraints($unnamed, $named)->create())->create();
        $event = new GenerateSchemaEventArgs(self::createStub(EntityManagerInterface::class), $schema);
        $listener = new SchemaForeignKeyNamesListener();
        $listener->postGenerateSchema($event);

        $table = $event->getSchema()->getTable('source');
        self::assertSame($named, $table->getForeignKey('custom_fk'));
        $foreignKeys = array_values($table->getForeignKeys());
        self::assertCount(2, $foreignKeys);
        $generated = $foreignKeys[0];
        self::assertNotNull($generated->getObjectName());
        self::assertSame(ReferentialAction::CASCADE, $generated->getOnDeleteAction());

        $listener->postGenerateSchema($event);

        self::assertSame($generated, array_values($event->getSchema()->getTable('source')->getForeignKeys())[0]);
    }
}
