<?php

namespace Pushword\Quiz\Tests\Flat;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Quiz\Entity\QuizResult;
use Pushword\Quiz\Flat\QuizResultSync;
use Pushword\Quiz\Repository\QuizResultRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class QuizResultSyncTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private QuizResultRepository $quizResultRepository;

    private QuizResultSync $sync;

    private string $testHost = 'localhost.dev';

    private string $csvPath = '';

    /** @var string[] */
    private array $createdUuids = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->quizResultRepository = self::getContainer()->get(QuizResultRepository::class);

        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $this->sync = new QuizResultSync(
            self::getContainer()->get(SiteRegistry::class),
            $contentDirFinder,
            $this->quizResultRepository,
            $this->entityManager,
        );

        $this->csvPath = $contentDirFinder->get($this->testHost).'/quiz-result.csv';
    }

    protected function tearDown(): void
    {
        foreach ($this->createdUuids as $uuid) {
            $quizResult = $this->quizResultRepository->findOneBy(['uuid' => $uuid]);
            if (null !== $quizResult) {
                $this->entityManager->remove($quizResult);
            }
        }

        $this->createdUuids = [];
        $this->entityManager->flush();

        if ('' !== $this->csvPath && file_exists($this->csvPath)) {
            @unlink($this->csvPath);
        }

        parent::tearDown();
    }

    public function testExportWritesResultsWithUuid(): void
    {
        $quizResult = $this->createResult('export-quiz', 80);
        $this->entityManager->flush();

        $this->sync->export($this->testHost);

        self::assertFileExists($this->csvPath);
        $csvContent = (string) file_get_contents($this->csvPath);
        self::assertStringContainsString('export-quiz', $csvContent);
        self::assertStringContainsString((string) $quizResult->uuid, $csvContent);
    }

    public function testExportBackfillsMissingUuid(): void
    {
        $quizResult = $this->createResult('backfill-quiz', 50);
        $quizResult->uuid = null;

        $this->entityManager->flush();

        $this->sync->export($this->testHost);

        self::assertNotNull($quizResult->uuid, 'Export must backfill and persist a uuid on legacy rows');
        $this->createdUuids[] = $quizResult->uuid;
        self::assertStringContainsString($quizResult->uuid, (string) file_get_contents($this->csvPath));
    }

    public function testSyncMergesCsvAndDatabase(): void
    {
        $dbOnly = $this->createResult('merge-quiz', 42);
        $this->entityManager->flush();

        $csvUuid = 'dddddddd-eeee-4fff-8aaa-111111111111';
        $this->createdUuids[] = $csvUuid;
        file_put_contents($this->csvPath, implode("\n", [
            'uuid,host,quiz,score,result,createdAt,updatedAt',
            \sprintf('%s,%s,merge-quiz,90,explorer,2026-01-01T10:00:00+00:00,2026-01-01T10:00:00+00:00', $csvUuid, $this->testHost),
            \sprintf('%s,%s,merge-quiz,42,,2026-01-02T10:00:00+00:00,2026-01-02T10:00:00+00:00', $dbOnly->uuid, $this->testHost),
        ])."\n");

        $this->sync->sync($this->testHost);

        $imported = $this->quizResultRepository->findOneBy(['uuid' => $csvUuid]);
        self::assertNotNull($imported, 'Unknown uuid must be created');
        self::assertSame(90, $imported->score);
        self::assertSame('explorer', $imported->result);
        self::assertSame('2026-01-01T10:00:00+00:00', $imported->createdAt?->format(\DATE_ATOM));

        self::assertCount(
            2,
            $this->quizResultRepository->findBy(['host' => $this->testHost, 'quiz' => 'merge-quiz']),
            'Known uuid must not be duplicated on import'
        );

        $csvContent = (string) file_get_contents($this->csvPath);
        self::assertStringContainsString((string) $dbOnly->uuid, $csvContent, 'Database rows must be re-exported into the CSV');
        self::assertStringContainsString($csvUuid, $csvContent);
    }

    public function testImportedRowsAreImmutable(): void
    {
        $quizResult = $this->createResult('immutable-quiz', 10);
        $this->entityManager->flush();

        file_put_contents($this->csvPath, implode("\n", [
            'uuid,host,quiz,score,result,createdAt,updatedAt',
            \sprintf('%s,%s,immutable-quiz,99,,2026-01-01T10:00:00+00:00,2026-01-01T10:00:00+00:00', $quizResult->uuid, $this->testHost),
        ])."\n");

        $this->sync->import($this->testHost);
        $this->entityManager->refresh($quizResult);

        self::assertSame(10, $quizResult->score, 'A known uuid must never be updated from the CSV');
    }

    private function createResult(string $quiz, int $score): QuizResult
    {
        $quizResult = new QuizResult();
        $quizResult->host = $this->testHost;
        $quizResult->quiz = $quiz;
        $quizResult->score = $score;

        $this->entityManager->persist($quizResult);
        $this->createdUuids[] = (string) $quizResult->uuid;

        return $quizResult;
    }
}
