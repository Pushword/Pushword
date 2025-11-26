<?php

namespace Pushword\Conversation\Flat;

use League\Csv\Writer;
use Pushword\Conversation\Entity\Message;
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

        $customColumns = $this->collectCustomColumns($messages);
        $header = array_merge(ConversationCsvHelper::BASE_COLUMNS, $customColumns);

        /** @var array<int, array<string, float|int|string|Stringable|null>> $rows */
        $rows = [];
        foreach ($messages as $message) {
            $rows[] = $this->buildRow($message, $customColumns);
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
     * @param string[] $customColumns
     *
     * @return array<string, float|int|string|Stringable|null>
     */
    private function buildRow(Message $message, array $customColumns): array
    {
        $row = [
            'id' => (string) ($message->getId() ?? ''),
            'type' => $message::class,
            'host' => $message->getHost(),
            'referring' => $message->getReferring(),
            'content' => $message->getContent(),
            'authorName' => $message->getAuthorName(),
            'authorEmail' => $message->getAuthorEmail(),
            'authorIp' => (string) ($message->getAuthorIpRaw() ?: ''),
            'tags' => implode('|', $message->getTagList()),
            'publishedAt' => ConversationCsvHelper::formatDate($message->getPublishedAt()),
            'createdAt' => ConversationCsvHelper::formatDate($message->safegetCreatedAt()),
            'updatedAt' => ConversationCsvHelper::formatDate($message->safegetUpdatedAt()),
        ];

        /** @var array<string, mixed> $customProperties */
        $customProperties = $message->getCustomProperties();
        foreach ($customColumns as $column) {
            $row[$column] = array_key_exists($column, $customProperties)
                ? ConversationCsvHelper::encodeValue($customProperties[$column])
                : null;
        }

        return $row;
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        mkdir($directory, 0755, true);
    }
}
