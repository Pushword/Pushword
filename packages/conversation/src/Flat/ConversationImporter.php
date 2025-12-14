<?php

namespace Pushword\Conversation\Flat;

use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Service\ImportContext;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Throwable;

final class ConversationImporter
{
    use ConversationContextTrait;

    private const float DUPLICATE_SIMILARITY_THRESHOLD = 90.0;

    /**
     * @var array<string, Message[]>
     */
    private array $messagesCacheByHost = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DenormalizerInterface $denormalizer,
        private readonly MediaRepository $mediaRepository,
        private readonly ImportContext $importContext,
    ) {
    }

    public function importExternal(string $csvPath): void
    {
        $defaultHost = $this->apps->get()->getMainHost();

        $this->importFromCsv(
            $csvPath,
            $defaultHost,
            fn (array $row, string $host): ?Message => $this->findSimilarMessage($row, $host),
            skipExisting: true,
        );
    }

    public function import(?string $host = null): void
    {
        $app = $this->resolveApp($host);

        $this->importFromCsv(
            $this->buildCsvPath($app),
            $app->getMainHost(),
            fn (array $row, string $_host): ?Message => $this->findMessage($row['id'] ?? null),
        );
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
     * @return string[]
     */
    private function prepareTags(string $tagsString): array
    {
        if ('' === $tagsString) {
            return [];
        }

        if (str_contains($tagsString, '|')) {
            return explode('|', $tagsString);
        }

        if (str_contains($tagsString, ',')) {
            return explode(',', $tagsString);
        }

        return explode(' ', $tagsString);
    }

    /**
     * @param array<string, string|null> $row
     * @param string[]                   $customColumns
     *
     * @return array<string, mixed>
     */
    private function buildDenormalizationData(array $row, array $customColumns, string $host): array
    {
        $tags = $this->prepareTags($row['tags'] ?? '');

        $data = [
            'host' => $host,
            'referring' => $row['referring'] ?? '',
            'content' => $row['content'] ?? '',
            'authorName' => $row['authorName'] ?? null,
            'authorEmail' => $row['authorEmail'] ?? null,
            'tags' => $tags,
        ];

        // Ajoute authorIpRaw seulement si la valeur n'est pas null et pas vide
        $authorIpValue = $row['authorIp'] ?? null;
        if (null !== $authorIpValue && '' !== trim($authorIpValue)) {
            $data['authorIpRaw'] = trim($authorIpValue);
        }

        // Ajoute les dates seulement si elles ne sont pas null et pas vides
        // Le dénormaliseur de Symfony attend des chaînes pour les dates, pas des objets DateTime
        $publishedAtValue = $row['publishedAt'] ?? null;
        if (null !== $publishedAtValue && '' !== trim($publishedAtValue)) {
            $data['publishedAt'] = trim($publishedAtValue);
        }

        $createdAtValue = $row['createdAt'] ?? null;
        if (null !== $createdAtValue && '' !== trim($createdAtValue)) {
            $data['createdAt'] = trim($createdAtValue);
        }

        $updatedAtValue = $row['updatedAt'] ?? null;
        if (null !== $updatedAtValue && '' !== trim($updatedAtValue)) {
            $data['updatedAt'] = trim($updatedAtValue);
        }

        $mediaList = $this->extractMediaList($row['mediaList'] ?? null);
        if ([] !== $mediaList) {
            // Le dénormaliseur attend un tableau, pas une ArrayCollection
            $data['mediaList'] = $mediaList;
        }

        $customProperties = $this->extractCustomProperties($row, $customColumns);
        if ([] !== $customProperties) {
            $data['customProperties'] = $customProperties;
        }

        // Supprime les valeurs null sauf pour les dates qui peuvent être null
        foreach ($data as $key => $value) {
            if (null === $value && ! in_array($key, ['publishedAt', 'createdAt', 'updatedAt'], true)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * @return Media[]
     */
    private function extractMediaList(?string $mediaListValue): array
    {
        if (null === $mediaListValue || '' === trim($mediaListValue)) {
            return [];
        }

        $fileNames = array_filter(
            array_map(trim(...), explode(',', $mediaListValue)),
            fn (string $fileName): bool => '' !== $fileName,
        );

        if ([] === $fileNames) {
            return [];
        }

        $medias = [];
        foreach ($fileNames as $fileName) {
            $media = $this->mediaRepository->findOneByFileName($fileName)
              ?? $this->mediaRepository->findOneBySearch($fileName);
            if (null !== $media) {
                $medias[] = $media;
            }
        }

        return $medias;
    }

    /**
     * @param array<string, string|null> $row
     * @param string[]                   $header
     */
    private function isHeaderRow(array $row, array $header): bool
    {
        // Une ligne est considérée comme un header si elle contient principalement des noms de colonnes
        $matches = 0;
        foreach ($row as $value) {
            $trimmed = null === $value ? '' : trim($value);
            if ('' !== $trimmed && in_array($trimmed, $header, true)) {
                ++$matches;
            }
        }

        // Si plus de la moitié des valeurs correspondent à des noms de colonnes, c'est un header
        return $matches > \count($header) / 2;
    }

    /**
     * @param array<string, string|null> $row
     */
    private function isEmptyRow(array $row): bool
    {
        return array_all($row, fn ($value): bool => ! (null !== $value && '' !== trim($value)));
    }

    /**
     * @param array<string, string|null> $row
     */
    private function isValidRow(array $row): bool
    {
        // Valide que content n'est pas vide (contrainte NotBlank)
        $content = $row['content'] ?? null;
        if (null === $content || '' === trim($content)) {
            return false;
        }

        // Valide que content ne dépasse pas 200000 caractères (contrainte Length max)
        $contentLength = mb_strlen(trim($content));
        if ($contentLength > 200000) {
            return false;
        }

        // Valide que content a au moins 1 caractère (contrainte Length min)
        return $contentLength >= 1;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function validateDates(array $data): array
    {
        // Valide le format des dates avant de les passer au dénormaliseur
        $dateFields = ['publishedAt', 'createdAt', 'updatedAt'];
        foreach ($dateFields as $field) {
            if (! isset($data[$field])) {
                continue;
            }

            $dateValue = $data[$field];
            if (! \is_string($dateValue)) {
                unset($data[$field]);

                continue;
            }

            // Essaie de parser la date pour valider le format
            $parsed = ConversationCsvHelper::parseDate($dateValue);
            if (null === $parsed) {
                // Si le parsing échoue, retire la date pour éviter les erreurs
                unset($data[$field]);
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

    /**
     * @param callable(array<string, string|null>, string): ?Message $messageResolver
     */
    private function importFromCsv(
        string $csvPath,
        string $defaultHost,
        callable $messageResolver,
        bool $skipExisting = false,
    ): void {
        $this->importContext->startImport();

        try {
            $reader = $this->createReader($csvPath);
            if (null === $reader) {
                return;
            }

            $header = $this->prepareHeader($reader);
            if ([] === $header) {
                return;
            }

            $customColumns = array_values(array_diff($header, ConversationCsvHelper::BASE_COLUMNS));

            $records = $reader->getRecords();
            foreach ($records as $row) {
                if ($this->shouldSkipRow($row, $header)) {
                    continue;
                }

                $host = $this->resolveRowHost($row, $defaultHost);
                $message = $messageResolver($row, $host);

                if ($skipExisting && null !== $message) {
                    continue;
                }

                if (null === $message) {
                    $messageClass = $this->resolveMessageClass($row['type'] ?? null, $row);
                    if (null === $messageClass) {
                        continue;
                    }

                    $options = [];
                } else {
                    $messageClass = $message::class;
                    $options = [AbstractNormalizer::OBJECT_TO_POPULATE => $message];
                }

                $data = $this->buildDenormalizationData($row, $customColumns, $host);

                // Valide les dates avant de les passer au dénormaliseur
                $data = $this->validateDates($data);

                // Extrait mediaList avant la dénormalisation car setMediaList() attend une Collection
                /** @var Media[] $mediaList */
                $mediaList = $data['mediaList'] ?? [];
                unset($data['mediaList']);

                // Ajoute le contexte pour ignorer les propriétés manquantes ou vides
                $ignoredAttributes = [];
                // Ignore authorIpRaw si la valeur est absente ou vide pour éviter les erreurs
                if (! isset($data['authorIpRaw'])) {
                    $ignoredAttributes[] = 'authorIpRaw';
                }

                // Ignore les dates si elles sont absentes pour éviter les erreurs de parsing
                if (! isset($data['publishedAt'])) {
                    $ignoredAttributes[] = 'publishedAt';
                }

                if (! isset($data['createdAt'])) {
                    $ignoredAttributes[] = 'createdAt';
                }

                if (! isset($data['updatedAt'])) {
                    $ignoredAttributes[] = 'updatedAt';
                }

                $options[AbstractNormalizer::IGNORED_ATTRIBUTES] = $ignoredAttributes;
                $options[AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES] = true;

                try {
                    /** @var Message $normalizedMessage */
                    $normalizedMessage = $this->denormalizer->denormalize($data, $messageClass, 'array', $options);

                    // Ajoute les médias manuellement après la dénormalisation
                    foreach ($mediaList as $media) {
                        $normalizedMessage->addMedia($media);
                    }

                    if (! isset($options[AbstractNormalizer::OBJECT_TO_POPULATE])) {
                        $this->entityManager->persist($normalizedMessage);
                    }
                } catch (Throwable) {
                    // Ignore les lignes qui échouent lors de la dénormalisation
                    continue;
                }
            }

            $this->entityManager->flush();
        } finally {
            $this->importContext->stopImport();
        }
    }

    /**
     * @param array<string, string|null> $row
     */
    private function resolveRowHost(array $row, string $defaultHost): string
    {
        $host = $row['host'] ?? null;
        if (! \is_string($host)) {
            return $defaultHost;
        }

        $trimmed = trim($host);

        return '' === $trimmed ? $defaultHost : $trimmed;
    }

    /**
     * @param array<string, string|null> $row
     * @param string[]                   $header
     */
    private function shouldSkipRow(array $row, array $header): bool
    {
        if ($this->isHeaderRow($row, $header)) {
            return true;
        }

        if ($this->isEmptyRow($row)) {
            return true;
        }

        return ! $this->isValidRow($row);
    }

    /**
     * @return Reader<array<string, string|null>>|null
     */
    private function createReader(string $csvPath): ?Reader
    {
        if (! file_exists($csvPath)) {
            return null;
        }

        try {
            $reader = Reader::from($csvPath, 'r');
        } catch (CsvException) {
            return null;
        }

        try {
            $reader->setHeaderOffset(0);
        } catch (CsvException) {
            return null;
        }

        return $reader;
    }

    /**
     * @param Reader<array<string, string|null>> $reader
     *
     * @return string[]
     */
    private function prepareHeader(Reader $reader): array
    {
        /** @var string[] $header */
        $header = $reader->getHeader();
        if ([] === $header) {
            return [];
        }

        // Filtre les colonnes vides
        return array_values(array_filter($header, fn (string $col): bool => '' !== trim($col)));
    }

    /**
     * @param array<string, string|null> $row
     */
    private function findSimilarMessage(array $row, string $host): ?Message
    {
        $content = $row['content'] ?? null;
        if (! \is_string($content)) {
            return null;
        }

        $normalized = trim($content);
        if ('' === $normalized) {
            return null;
        }

        foreach ($this->getMessagesForHost($host) as $message) {
            $percent = 0.0;
            similar_text($normalized, $message->getContent(), $percent);

            if ($percent >= self::DUPLICATE_SIMILARITY_THRESHOLD) {
                return $message;
            }
        }

        return null;
    }

    /**
     * @return Message[]
     */
    private function getMessagesForHost(string $host): array
    {
        if (! array_key_exists($host, $this->messagesCacheByHost)) {
            $this->messagesCacheByHost[$host] = $this->getMessageRepository()->findByHost($host);
        }

        return $this->messagesCacheByHost[$host];
    }
}
