<?php

namespace Pushword\Conversation\Tests\Flat;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Flat\ConversationExporter;
use Pushword\Conversation\Flat\ConversationImporter;
use Pushword\Conversation\Flat\ConversationSync;
use Pushword\Conversation\Repository\MessageRepository;
use Pushword\Conversation\Service\ImportContext;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\FlatFileContentDirFinder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The uuid is what keeps two independently written databases (local and prod
 * once deploys stop shipping the SQLite file) merging instead of colliding on
 * auto-increment ids.
 */
#[Group('integration')]
final class ConversationUuidMergeTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private MessageRepository $messageRepository;

    private ConversationExporter $exporter;

    private ConversationImporter $importer;

    private ConversationSync $sync;

    private string $testHost = 'localhost.dev';

    private string $csvPath = '';

    /** @var string[] */
    private array $createdMessageUuids = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->messageRepository = self::getContainer()->get(MessageRepository::class);

        $appPool = self::getContainer()->get(SiteRegistry::class);
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);

        $this->exporter = new ConversationExporter($this->entityManager);
        $this->exporter->initConversationContext($appPool, $contentDirFinder, $this->messageRepository);

        $this->importer = new ConversationImporter(
            $this->entityManager,
            self::getContainer()->get('serializer'),
            self::getContainer()->get(MediaRepository::class),
            new ImportContext(),
        );
        $this->importer->initConversationContext($appPool, $contentDirFinder, $this->messageRepository);

        $this->sync = new ConversationSync($appPool, $contentDirFinder, $this->importer, $this->exporter);

        $appPool->switchSite($this->testHost);
        $this->csvPath = $contentDirFinder->getBaseDir().'/conversation.csv';
    }

    protected function tearDown(): void
    {
        foreach ($this->createdMessageUuids as $uuid) {
            $message = $this->messageRepository->findOneBy(['uuid' => $uuid]);
            if (null !== $message) {
                $this->entityManager->remove($message);
            }
        }

        $this->createdMessageUuids = [];
        $this->entityManager->flush();

        if ('' !== $this->csvPath && file_exists($this->csvPath)) {
            @unlink($this->csvPath);
        }

        parent::tearDown();
    }

    public function testExportBackfillsMissingUuid(): void
    {
        $message = $this->createMessage('Legacy message without uuid');
        $message->uuid = null;

        $this->entityManager->flush();

        $this->exporter->export($this->testHost);

        self::assertNotNull($message->uuid, 'Export must backfill and persist a uuid on legacy rows');
        $this->createdMessageUuids[] = $message->uuid;

        $csvContent = (string) file_get_contents($this->csvPath);
        self::assertStringContainsString('uuid', explode("\n", $csvContent)[0]);
        self::assertStringContainsString($message->uuid, $csvContent);
    }

    public function testImportMatchesByUuidNotById(): void
    {
        $existing = $this->createMessage('Message from this database');
        $this->entityManager->flush();
        $this->createdMessageUuids[] = (string) $existing->uuid;

        // Same id, different uuid: a distinct message from another database
        // lineage. It must be created, not overwrite the id-colliding row.
        $foreignUuid = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
        $this->createdMessageUuids[] = $foreignUuid;
        $this->writeCsv([
            [(string) $existing->id, $foreignUuid, 'Message from another database', '2026-01-01 10:00'],
        ]);

        $this->importer->import($this->testHost);

        $this->entityManager->refresh($existing);
        self::assertSame('Message from this database', $existing->getContent(), 'The id-colliding row must not be overwritten');

        $imported = $this->messageRepository->findOneBy(['uuid' => $foreignUuid]);
        self::assertNotNull($imported, 'The unknown uuid must become a new message');
        self::assertSame('Message from another database', $imported->getContent());
    }

    public function testImportAdoptsUuidOnLegacyRow(): void
    {
        $legacy = $this->createMessage('Legacy content');
        $legacy->uuid = null;

        $this->entityManager->flush();

        $adoptedUuid = 'bbbbbbbb-cccc-4ddd-8eee-ffffffffffff';
        $this->createdMessageUuids[] = $adoptedUuid;
        $this->writeCsv([
            [(string) $legacy->id, $adoptedUuid, 'Legacy content edited', '2026-01-01 10:00'],
        ]);

        $this->importer->import($this->testHost);
        $this->entityManager->refresh($legacy);

        self::assertSame($adoptedUuid, $legacy->uuid, 'A not-yet-backfilled row matched by id must adopt the CSV uuid');
        self::assertSame('Legacy content edited', $legacy->getContent());
    }

    public function testSyncExportsUnionAfterImport(): void
    {
        $dbOnly = $this->createMessage('Database-only message');
        $this->entityManager->flush();
        $this->createdMessageUuids[] = (string) $dbOnly->uuid;

        $csvUuid = 'cccccccc-dddd-4eee-8fff-000000000000';
        $this->createdMessageUuids[] = $csvUuid;
        $this->writeCsv([
            ['', $csvUuid, 'Csv-only message', '2026-01-01 10:00'],
        ]);
        // A CSV newer than the last database write is the import trigger.
        touch($this->csvPath, time() + 3600);

        self::assertTrue($this->sync->mustImport($this->testHost));
        $this->sync->sync($this->testHost);

        self::assertNotNull($this->messageRepository->findOneBy(['uuid' => $csvUuid]), 'Csv row must be imported');

        $csvContent = (string) file_get_contents($this->csvPath);
        self::assertStringContainsString($csvUuid, $csvContent);
        self::assertStringContainsString((string) $dbOnly->uuid, $csvContent, 'Database-only messages must flow back into the CSV after import');
    }

    public function testDeletionPropagatesAsTombstone(): void
    {
        $message = $this->createMessage('Message deleted on the other machine');
        $this->entityManager->flush();
        $this->createdMessageUuids[] = (string) $message->uuid;

        $this->writeCsv([
            ['', (string) $message->uuid, 'Message deleted on the other machine', '2026-01-01 10:00', '2026-01-02 12:00'],
        ]);

        $this->importer->import($this->testHost);
        $this->entityManager->refresh($message);

        self::assertNotNull($message->deletedAt, 'A CSV tombstone must soft-delete the matching message');
    }

    public function testStaleCsvDoesNotClearTombstone(): void
    {
        $message = $this->createMessage('Deleted here');
        $message->softDelete();

        $this->entityManager->flush();
        $this->createdMessageUuids[] = (string) $message->uuid;

        // A stale CSV exported before the deletion has no deletedAt value.
        $this->writeCsv([
            ['', (string) $message->uuid, 'Deleted here', '2026-01-01 10:00'],
        ]);

        $this->importer->import($this->testHost);
        $this->entityManager->refresh($message);

        self::assertNotNull($message->deletedAt, 'An empty deletedAt cell must never resurrect a deleted message');
    }

    public function testTombstoneTravelsThroughExport(): void
    {
        $message = $this->createMessage('Soft deleted, still synced');
        $message->softDelete();

        $this->entityManager->flush();
        $this->createdMessageUuids[] = (string) $message->uuid;

        $this->exporter->export($this->testHost);

        $csvContent = (string) file_get_contents($this->csvPath);
        self::assertStringContainsString((string) $message->uuid, $csvContent, 'The tombstone row must stay in the CSV to reach other databases');
        self::assertStringContainsString('deletedAt', explode("\n", $csvContent)[0]);
    }

    private function createMessage(string $content): Message
    {
        $message = new Message();
        $message->host = $this->testHost;
        $message->setContent($content);
        $message->setReferring('/uuid-merge-test');

        $this->entityManager->persist($message);

        return $message;
    }

    /**
     * @param array<array{string, string, string, string, 4?: string}> $rows [id, uuid, content, date, deletedAt?]
     */
    private function writeCsv(array $rows): void
    {
        $lines = ['id,uuid,type,host,referring,content,authorName,authorEmail,authorIp,tags,mediaList,publishedAt,createdAt,updatedAt,deletedAt'];
        foreach ($rows as $row) {
            [$id, $uuid, $content, $date] = $row;
            $lines[] = \sprintf('%s,%s,,%s,/uuid-merge-test,"%s",,,,,,,%s,%s,%s', $id, $uuid, $this->testHost, $content, $date, $date, $row[4] ?? '');
        }

        file_put_contents($this->csvPath, implode("\n", $lines)."\n");
    }
}
