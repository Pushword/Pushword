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

        // In global mode, export all messages from all hosts
        $messages = $this->isGlobalMode()
            ? $this->getAllMessages()
            : $this->getMessages($app->getMainHost());

        if ([] === $messages) {
            return;
        }

        $baseColumns = $this->detectBaseColumns($messages[0] ?? null);
        $customColumns = $this->collectCustomColumns($messages);
        // Filter out custom columns that are already in base columns to avoid duplicates
        $customColumns = array_values(array_diff($customColumns, $baseColumns));

        $header = array_merge($baseColumns, $customColumns);

        /** @var array<int, array<string, float|int|string|Stringable|null>> $rows */
        $rows = [];
        foreach ($messages as $message) {
            $row = $this->buildRow($message, $baseColumns, $customColumns);
            // Réorganise les valeurs dans l'ordre du header pour garantir la cohérence
            $orderedRow = [];
            foreach ($header as $column) {
                $orderedRow[$column] = $row[$column] ?? null;
            }

            $rows[] = $orderedRow;
        }

        $this->ensureDirectoryExists(\dirname($csvPath));

        $tempPath = $csvPath.'.tmp';
        $writer = Writer::from($tempPath, 'w+');
        $writer->insertOne($header);
        $writer->insertAll($rows);

        // Only replace if content changed (compare hashes)
        if (file_exists($csvPath) && md5_file($csvPath) === md5_file($tempPath)) {
            unlink($tempPath);

            return;
        }

        rename($tempPath, $csvPath);
    }

    /**
     * @return Message[]
     */
    private function getMessages(string $host): array
    {
        return $this->getMessageRepository()->findByHost($host);
    }

    /**
     * @return Message[]
     */
    private function getAllMessages(): array
    {
        return $this->getMessageRepository()->findAll();
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

        // Retire les doublons
        $allColumns = array_unique($allColumns);

        // Extrait 'id' s'il existe pour le mettre en premier
        $hasId = in_array('id', $allColumns, true);
        if ($hasId) {
            $allColumns = array_values(array_filter($allColumns, fn (string $col): bool => 'id' !== $col));
        }

        // Trie le reste des colonnes
        sort($allColumns);

        // Remet 'id' en première position
        if ($hasId) {
            array_unshift($allColumns, 'id');
        }

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
            'id' => (string) ($message->id ?? ''),
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
            'createdAt' => ConversationCsvHelper::formatDate($message->createdAt),
            'updatedAt' => ConversationCsvHelper::formatDate($message->updatedAt),
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
