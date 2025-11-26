<?php

namespace Pushword\Conversation\Flat;

use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use Pushword\Conversation\Entity\Message;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class ConversationImporter
{
    use ConversationContextTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DenormalizerInterface $denormalizer,
        private readonly MediaRepository $mediaRepository,
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

        // Filtre les colonnes vides
        $header = array_filter($header, fn (string $col): bool => '' !== trim($col));
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

            // Extrait mediaList avant la dénormalisation car setMediaList() attend une Collection
            /** @var Media[] $mediaList */
            $mediaList = $data['mediaList'] ?? [];
            unset($data['mediaList']);

            // Ajoute le contexte pour ignorer les propriétés manquantes
            $options[AbstractNormalizer::IGNORED_ATTRIBUTES] = [];
            $options[AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES] = true;

            /** @var Message $normalizedMessage */
            $normalizedMessage = $this->denormalizer->denormalize($data, $messageClass, 'array', $options);

            // Ajoute les médias manuellement après la dénormalisation
            foreach ($mediaList as $media) {
                $normalizedMessage->addMedia($media);
            }

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
        // Convertit tags de string (séparé par |) en array
        $tagsString = $row['tags'] ?? '';
        $tags = '' !== $tagsString ? explode('|', $tagsString) : [];

        $data = [
            'host' => $row['host'] ?: $defaultHost,
            'referring' => $row['referring'] ?? '',
            'content' => $row['content'] ?? '',
            'authorName' => $row['authorName'] ?? null,
            'authorEmail' => $row['authorEmail'] ?? null,
            'authorIpRaw' => $row['authorIp'] ?? null,
            'tags' => $tags,
        ];

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
            $media = $this->mediaRepository->findOneBy(['fileName' => $fileName]);
            if (null !== $media) {
                $medias[] = $media;
            }
        }

        return $medias;
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
