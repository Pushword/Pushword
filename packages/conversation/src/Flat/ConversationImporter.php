<?php

namespace Pushword\Conversation\Flat;

use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use Pushword\Conversation\Entity\Message;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class ConversationImporter
{
    use ConversationContextTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DenormalizerInterface $denormalizer,
    ) {
    }

    public function import(?string $host = null): void
    {
        $app = $this->resolveApp($host);
        $csvPath = $this->buildCsvPath($app);

        if (! file_exists($csvPath)) {
            return;
        }

        try {
            $reader = Reader::from($csvPath, 'r');
        } catch (CsvException) {
            return;
        }

        try {
            $reader->setHeaderOffset(0);
        } catch (CsvException) {
            return;
        }

        /** @var string[] $header */
        $header = $reader->getHeader();
        if ([] === $header) {
            return;
        }

        $customColumns = array_values(array_diff($header, ConversationCsvHelper::BASE_COLUMNS));

        /** @var iterable<int, array<string, string|null>> $records */
        $records = $reader->getRecords();
        foreach ($records as $row) {
            $message = $this->findMessage($row['id'] ?? null);
            if (null === $message) {
                $messageClass = $this->resolveMessageClass($row['type'] ?? null);
                if (null === $messageClass) {
                    continue;
                }

                $options = [];
            } else {
                $messageClass = $message::class;
                $options = [AbstractNormalizer::OBJECT_TO_POPULATE => $message];
            }

            $data = $this->buildDenormalizationData($row, $customColumns, $app->getMainHost());

            /** @var Message $normalizedMessage */
            $normalizedMessage = $this->denormalizer->denormalize($data, $messageClass, 'array', $options);

            if (! isset($options[AbstractNormalizer::OBJECT_TO_POPULATE])) {
                $this->entityManager->persist($normalizedMessage);
            }
        }

        $this->entityManager->flush();
    }

    public function getLastUpdatedMessage(string $host): ?Message
    {
        return $this->getMessageRepository()->findOneBy(['host' => $host], ['updatedAt' => 'DESC']);
    }

    private function findMessage(?string $id): ?Message
    {
        if (null === $id || '' === trim($id)) {
            return null;
        }

        return $this->getMessageRepository()->find((int) $id);
    }

    /**
     * @param array<string, string|null> $row
     * @param string[]                   $customColumns
     *
     * @return array<string, mixed>
     */
    private function buildDenormalizationData(array $row, array $customColumns, string $defaultHost): array
    {
        $data = [
            'host' => $row['host'] ?: $defaultHost,
            'referring' => $row['referring'] ?? '',
            'content' => $row['content'] ?? '',
            'authorName' => $row['authorName'] ?? null,
            'authorEmail' => $row['authorEmail'] ?? null,
            'authorIpRaw' => $row['authorIp'] ?? null,
            'tags' => $row['tags'] ?? '',
            'publishedAt' => ConversationCsvHelper::parseDate($row['publishedAt'] ?? null),
            'createdAt' => ConversationCsvHelper::parseDate($row['createdAt'] ?? null),
            'updatedAt' => ConversationCsvHelper::parseDate($row['updatedAt'] ?? null),
        ];

        $customProperties = $this->extractCustomProperties($row, $customColumns);
        if ([] !== $customProperties) {
            $data['customProperties'] = $customProperties;
        }

        foreach ($data as $key => $value) {
            if (null === $value) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * @param array<string, string|null> $row
     * @param string[]                   $customColumns
     *
     * @return array<string, mixed>
     */
    private function extractCustomProperties(array $row, array $customColumns): array
    {
        $customProperties = [];

        foreach ($customColumns as $column) {
            if (! array_key_exists($column, $row)) {
                continue;
            }

            $value = $row[$column];
            if (null === $value) {
                continue;
            }

            $trimmed = trim($value);
            if ('' === $trimmed) {
                continue;
            }

            $customProperties[$column] = ConversationCsvHelper::decodeValue($trimmed);
        }

        return $customProperties;
    }
}
