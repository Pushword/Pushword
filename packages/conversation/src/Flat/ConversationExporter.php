<?php

namespace Pushword\Conversation\Flat;

use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\ManyToMany;
use League\Csv\Writer;
use Pushword\Conversation\Entity\Message;
use Pushword\Core\Utils\Entity;
use Stringable;

final class ConversationExporter
{
    use ConversationContextTrait;

    public function __construct(
    ) {
    }

    public function export(?string $host = null): void
    {
        $app = $this->resolveApp($host);
        $csvPath = $this->buildCsvPath($app);
        $messages = $this->getMessages($app->getMainHost());

        if ([] === $messages) {
            return;
        }

        $baseColumns = $this->detectBaseColumns($messages[0] ?? null);
        $customColumns = $this->collectCustomColumns($messages);
        $header = array_merge($baseColumns, $customColumns);

        /** @var array<int, array<string, float|int|string|Stringable|null>> $rows */
        $rows = [];
        foreach ($messages as $message) {
            $rows[] = $this->buildRow($message, $baseColumns, $customColumns);
        }

        $this->ensureDirectoryExists(\dirname($csvPath));

        $writer = Writer::from($csvPath, 'w+');
        $writer->insertOne($header);
        $writer->insertAll($rows);
    }

    /**
     * @return Message[]
     */
    private function getMessages(string $host): array
    {
        return $this->getMessageRepository()->findByHost($host);
    }

    /**
     * @return string[]
     */
    private function detectBaseColumns(?Message $sampleMessage): array
    {
        if (null === $sampleMessage) {
            return ConversationCsvHelper::BASE_COLUMNS;
        }

        // Détecte automatiquement les propriétés avec attributs Doctrine
        $properties = Entity::getProperties(
            $sampleMessage,
            [Column::class, ManyToMany::class]
        );

        // Colonnes spéciales qui ne sont pas des propriétés Doctrine
        $specialColumns = ['id', 'type'];

        // Colonnes calculées ou avec logique spéciale
        $computedColumns = ['tags', 'mediaList', 'authorIp'];

        // Combine toutes les colonnes
        $allColumns = array_merge($specialColumns, $properties, $computedColumns);

        // Retire les doublons et trie
        $allColumns = array_unique($allColumns);
        sort($allColumns);

        return $allColumns;
    }

    /**
     * @param Message[] $messages
     *
     * @return string[]
     */
    private function collectCustomColumns(array $messages): array
    {
        $columns = [];
        foreach ($messages as $message) {
            /** @var array<string, mixed> $customProperties */
            $customProperties = $message->getCustomProperties();
            foreach (array_keys($customProperties) as $property) {
                $columns[$property] = true;
            }
        }

        $columns = array_keys($columns);
        sort($columns);

        return $columns;
    }

    /**
     * @param string[] $baseColumns
     * @param string[] $customColumns
     *
     * @return array<string, float|int|string|Stringable|null>
     */
    private function buildRow(Message $message, array $baseColumns, array $customColumns): array
    {
        $row = [];

        foreach ($baseColumns as $column) {
            $row[$column] = $this->getColumnValue($message, $column);
        }

        /** @var array<string, mixed> $customProperties */
        $customProperties = $message->getCustomProperties();
        foreach ($customColumns as $column) {
            $row[$column] = array_key_exists($column, $customProperties)
                ? ConversationCsvHelper::encodeValue($customProperties[$column])
                : null;
        }

        return $row;
    }

    private function getColumnValue(Message $message, string $column): float|int|string|null
    {
        return match ($column) {
            'id' => (string) ($message->getId() ?? ''),
            'type' => $message::class,
            'authorIp' => (string) ($message->getAuthorIpRaw() ?: ''),
            'tags' => implode('|', $message->getTagList()),
            'mediaList' => $this->getMediaListValue($message),
            'publishedAt', 'createdAt', 'updatedAt' => $this->getDateValue($message, $column),
            default => $this->getPropertyValue($message, $column),
        };
    }

    private function getMediaListValue(Message $message): string
    {
        $mediaFileNames = [];
        foreach ($message->getMediaList() as $media) {
            $mediaFileNames[] = $media->getFileName();
        }

        return implode(',', $mediaFileNames);
    }

    private function getDateValue(Message $message, string $column): ?string
    {
        return match ($column) {
            'publishedAt' => ConversationCsvHelper::formatDate($message->getPublishedAt()),
            'createdAt' => ConversationCsvHelper::formatDate($message->safegetCreatedAt()),
            'updatedAt' => ConversationCsvHelper::formatDate($message->safegetUpdatedAt()),
            default => null,
        };
    }

    private function getPropertyValue(Message $message, string $property): float|int|string|null
    {
        $getter = 'get'.ucfirst($property);
        if (! method_exists($message, $getter)) {
            return null;
        }

        $value = $message->$getter(); // @phpstan-ignore-line

        if ($value instanceof Collection) {
            // Pour les collections, on exporte une représentation string
            $items = [];
            foreach ($value->toArray() as $item) {
                if ($item instanceof Stringable) {
                    $items[] = (string) $item;
                } elseif (is_scalar($item)) {
                    $items[] = (string) $item;
                }
            }

            return implode(',', $items);
        }

        if ($value instanceof DateTimeInterface) {
            return ConversationCsvHelper::formatDate($value) ?? '';
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_scalar($value)) {
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            return $value;
        }

        return null;
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        mkdir($directory, 0755, true);
    }
}
