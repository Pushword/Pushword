<?php

declare(strict_types=1);

namespace Pushword\Core\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
final class SchemaForeignKeyNamesListener
{
    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $schema = $event->getSchema();
        $schemaEditor = $schema->edit();

        foreach ($schema->getTables() as $table) {
            $foreignKeys = $table->getForeignKeys();
            foreach ($foreignKeys as $name => $foreignKey) {
                if (null !== $foreignKey->getObjectName()) {
                    continue;
                }

                // DBAL keys unnamed constraints by their generated name. Explicit names
                // prevent its comparator from matching unrelated empty names on SQLite.
                /** @var non-empty-string $name */
                $foreignKeys[$name] = $foreignKey->edit()->setUnquotedName(strtoupper($name))->create();
            }

            $schemaEditor->modifyTable(
                $table->getObjectName(),
                static function (TableEditor $tableEditor) use ($foreignKeys): void {
                    $tableEditor->setForeignKeyConstraints(...array_values($foreignKeys));
                },
            );
        }

        $event->setSchema($schemaEditor->create());
    }
}
