<?php

namespace Pushword\Quiz\Flat;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use League\Csv\Writer;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Sync\FlatSyncInterface;
use Pushword\Quiz\Entity\QuizResult;
use Pushword\Quiz\Repository\QuizResultRepository;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Round-trips quiz results through `content/<host>/quiz-result.csv` so a
 * deploy that rebuilds the database from flat files cannot erase attempts
 * collected in production. Results are anonymous and immutable: import only
 * creates rows whose uuid is unknown — never updates, never deletes — and the
 * merged union is written back on export.
 */
final readonly class QuizResultSync implements FlatSyncInterface
{
    private const array COLUMNS = ['uuid', 'host', 'quiz', 'score', 'result', 'createdAt', 'updatedAt'];

    public function __construct(
        private SiteRegistry $apps,
        private FlatFileContentDirFinder $contentDirFinder,
        private QuizResultRepository $quizResultRepository,
        private EntityManagerInterface $entityManager,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function getEntityName(): string
    {
        return 'quiz-result';
    }

    public function sync(?string $host = null, bool $forceExport = false): void
    {
        foreach ($this->resolveHosts($host) as $syncHost) {
            $this->importHost($syncHost);
            $this->exportHost($syncHost);
        }
    }

    public function import(?string $host = null): void
    {
        foreach ($this->resolveHosts($host) as $importHost) {
            $this->importHost($importHost);
        }
    }

    public function export(?string $host = null): void
    {
        foreach ($this->resolveHosts($host) as $exportHost) {
            $this->exportHost($exportHost);
        }
    }

    /**
     * @return string[]
     */
    private function resolveHosts(?string $host): array
    {
        if (null !== $host) {
            return [$this->apps->switchSite($host)->get()->getMainHost()];
        }

        return $this->apps->getHosts();
    }

    private function importHost(string $host): void
    {
        $csvPath = $this->getCsvPath($host);
        if (! $this->filesystem->exists($csvPath)) {
            return;
        }

        try {
            /** @var Reader<array<string, string|null>> $reader */
            $reader = Reader::from($csvPath, 'r');
            $reader->setHeaderOffset(0);
            $records = [...$reader->getRecords()];
        } catch (CsvException) {
            return;
        }

        $knownUuids = $this->getKnownUuids($host);

        $created = false;
        foreach ($records as $row) {
            $quiz = trim($row['quiz'] ?? '');
            if ('' === $quiz) {
                continue;
            }

            $uuid = trim($row['uuid'] ?? '');
            if ('' !== $uuid && isset($knownUuids[$uuid])) {
                continue;
            }

            $quizResult = new QuizResult();
            $quizResult->host = $host;
            $quizResult->quiz = $quiz;
            $quizResult->score = (int) ($row['score'] ?? 0);
            $result = trim($row['result'] ?? '');
            $quizResult->result = '' === $result ? null : $result;

            // Keep the CSV identity so the row stays the same result on every
            // machine; a hand-added row without uuid keeps the generated one.
            if ('' !== $uuid) {
                $quizResult->uuid = $uuid;
            }

            $createdAt = $this->parseDate($row['createdAt'] ?? null);
            if (null !== $createdAt) {
                $quizResult->createdAt = $createdAt;
            }

            $updatedAt = $this->parseDate($row['updatedAt'] ?? null);
            if (null !== $updatedAt) {
                $quizResult->updatedAt = $updatedAt;
            }

            $this->entityManager->persist($quizResult);
            $knownUuids[$quizResult->getOrGenerateUuid()] = true;
            $created = true;
        }

        if ($created) {
            $this->entityManager->flush();
        }
    }

    private function exportHost(string $host): void
    {
        $quizResults = $this->quizResultRepository->findBy(['host' => $host], ['createdAt' => 'ASC', 'id' => 'ASC']);
        if ([] === $quizResults) {
            return;
        }

        $this->backfillUuids($quizResults);

        $rows = [];
        foreach ($quizResults as $quizResult) {
            $rows[] = [
                'uuid' => $quizResult->uuid,
                'host' => $quizResult->host,
                'quiz' => $quizResult->quiz,
                'score' => (string) $quizResult->score,
                'result' => $quizResult->result ?? '',
                'createdAt' => $quizResult->createdAt?->format(\DATE_ATOM) ?? '',
                'updatedAt' => $quizResult->updatedAt?->format(\DATE_ATOM) ?? '',
            ];
        }

        $csvPath = $this->getCsvPath($host);
        $this->filesystem->mkdir(\dirname($csvPath));

        $tempPath = $csvPath.'.tmp';
        $writer = Writer::from($tempPath, 'w+');
        $writer->insertOne(self::COLUMNS);
        $writer->insertAll($rows);

        // Only replace if content changed, to keep the file mtime meaningful
        if ($this->filesystem->exists($csvPath) && md5_file($csvPath) === md5_file($tempPath)) {
            $this->filesystem->remove($tempPath);

            return;
        }

        $this->filesystem->rename($tempPath, $csvPath, true);
    }

    /**
     * Results predating the uuid column get one on their first export, and it
     * must be flushed: the uuid written to the CSV is only an identity if the
     * database keeps the same value.
     *
     * @param QuizResult[] $quizResults
     */
    private function backfillUuids(array $quizResults): void
    {
        $backfilled = false;
        foreach ($quizResults as $quizResult) {
            if (null === $quizResult->uuid) {
                $quizResult->getOrGenerateUuid();
                $backfilled = true;
            }
        }

        if ($backfilled) {
            $this->entityManager->flush();
        }
    }

    /**
     * @return array<string, true>
     */
    private function getKnownUuids(string $host): array
    {
        return array_fill_keys($this->quizResultRepository->uuidsByHost($host), true);
    }

    private function parseDate(?string $value): ?DateTime
    {
        $value = trim($value ?? '');
        if ('' === $value) {
            return null;
        }

        $date = DateTime::createFromFormat(\DATE_ATOM, $value) ?: date_create($value);

        return false === $date ? null : $date;
    }

    private function getCsvPath(string $host): string
    {
        return $this->contentDirFinder->get($host).'/quiz-result.csv';
    }
}
