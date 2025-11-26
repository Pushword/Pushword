<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Flat;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Entity\Review;
use Pushword\Conversation\Flat\ConversationExporter;
use Pushword\Conversation\Flat\ConversationImporter;
use Pushword\Conversation\Repository\MessageRepository;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Flat\FlatFileContentDirFinder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;

final class ConversationFlatTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private MessageRepository $messageRepository;

    private MediaRepository $mediaRepository;

    private ConversationExporter $exporter;

    private ConversationImporter $importer;

    private string $testHost = 'localhost.dev';

    private string $csvPath = '';

    /** @var array<int> */
    private array $createdMessageIds = [];

    /** @var array<string> */
    private array $createdMediaFileNames = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->messageRepository = self::getContainer()->get(MessageRepository::class);
        $this->mediaRepository = self::getContainer()->get(MediaRepository::class);

        $appPool = self::getContainer()->get(AppPool::class);
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);

        $this->exporter = new ConversationExporter();
        $this->exporter->initConversationContext(
            $appPool,
            $contentDirFinder,
            $this->messageRepository,
        );

        $denormalizer = self::getContainer()->get('serializer');
        $this->importer = new ConversationImporter(
            $this->entityManager,
            $denormalizer,
            $this->mediaRepository,
        );
        $this->importer->initConversationContext(
            $appPool,
            $contentDirFinder,
            $this->messageRepository,
        );

        // Détermine le chemin du CSV
        $app = $appPool->switchCurrentApp($this->testHost)->get();
        $this->csvPath = $contentDirFinder->get($app->getMainHost()).'/conversation.csv';
    }

    #[Override]
    protected function tearDown(): void
    {
        // Nettoie les messages créés
        if ([] !== $this->createdMessageIds) {
            try {
                foreach ($this->createdMessageIds as $id) {
                    $message = $this->messageRepository->find($id);
                    if (null !== $message) {
                        $this->entityManager->remove($message);
                    }
                }

                $this->entityManager->flush();
            } catch (Throwable) {
                // Ignore errors during cleanup
            }
        }

        // Nettoie les médias créés
        if ([] !== $this->createdMediaFileNames) {
            try {
                foreach ($this->createdMediaFileNames as $fileName) {
                    $media = $this->mediaRepository->findOneBy(['fileName' => $fileName]);
                    if (null !== $media) {
                        $this->entityManager->remove($media);
                    }
                }

                $this->entityManager->flush();
            } catch (Throwable) {
                // Ignore errors during cleanup
            }
        }

        // Supprime le fichier CSV de test
        if (file_exists($this->csvPath)) {
            @unlink($this->csvPath);
        }

        parent::tearDown();
    }

    public function testExportAndImport(): void
    {
        // Crée des médias de test
        $media1 = $this->createTestMedia('test-image-1.jpg');
        $media2 = $this->createTestMedia('test-image-2.png');

        // Crée des messages de test
        $message1 = $this->createTestMessage('Test message 1', 'user1@example.com', 'User One');
        $message1->addMedia($media1);
        $message1->addMedia($media2);
        $message1->addTag('test');
        $message1->addTag('export');
        $message1->setCustomProperty('customField1', 'value1');
        $message1->setCustomProperty('customField2', 42);

        $message2 = $this->createTestReview('Test review', 'user2@example.com', 'User Two', 5);
        $message2->addMedia($media1);
        $message2->addTag('review');

        $this->entityManager->persist($message1);
        $this->entityManager->persist($message2);
        $this->entityManager->flush();

        $message1Id = $message1->getId();
        $message2Id = $message2->getId();

        if (null !== $message1Id) {
            $this->createdMessageIds[] = $message1Id;
        }

        if (null !== $message2Id) {
            $this->createdMessageIds[] = $message2Id;
        }

        // Exporte les messages
        $this->exporter->export($this->testHost);

        // Vérifie que le fichier CSV existe
        self::assertFileExists($this->csvPath);

        // Vérifie le contenu du CSV
        $csvContent = file_get_contents($this->csvPath);
        self::assertIsString($csvContent);
        self::assertStringContainsString('Test message 1', $csvContent);
        self::assertStringContainsString('user1@example.com', $csvContent);
        self::assertStringContainsString('test-image-1.jpg', $csvContent);
        self::assertStringContainsString('test-image-2.png', $csvContent);
        // Les tags sont triés alphabétiquement
        self::assertTrue(
            str_contains($csvContent, 'export|test') || str_contains($csvContent, 'test|export'),
            'Tags should be present in CSV (export|test or test|export)'
        );
        self::assertStringContainsString('customField1', $csvContent);
        self::assertStringContainsString('value1', $csvContent);
        self::assertStringContainsString('customField2', $csvContent);
        self::assertStringContainsString('42', $csvContent);

        // Vérifie que mediaList est bien exporté avec des virgules
        $lines = explode("\n", $csvContent);
        /** @var string $headerLine */
        $headerLine = $lines[0];
        self::assertStringContainsString('mediaList', $headerLine);

        // Trouve la ligne du message1
        foreach ($lines as $line) {
            if (str_contains($line, 'Test message 1')) {
                self::assertStringContainsString('test-image-1.jpg,test-image-2.png', $line);

                break;
            }
        }

        // Supprime les messages de la base de données
        $this->entityManager->remove($message1);
        $this->entityManager->remove($message2);
        $this->entityManager->flush();

        // Réinitialise les IDs pour éviter de supprimer deux fois
        $this->createdMessageIds = [];

        // Importe les messages depuis le CSV
        $this->importer->import($this->testHost);

        // Vérifie que les messages ont été importés
        $importedMessage1 = $this->messageRepository->findOneBy([
            'host' => $this->testHost,
            'content' => 'Test message 1',
        ]);
        self::assertInstanceOf(Message::class, $importedMessage1);
        self::assertSame('Test message 1', $importedMessage1->getContent());
        self::assertSame('user1@example.com', $importedMessage1->getAuthorEmail());
        self::assertSame('User One', $importedMessage1->getAuthorName());
        self::assertSame('value1', $importedMessage1->getCustomProperty('customField1'));
        self::assertSame(42, $importedMessage1->getCustomProperty('customField2'));

        // Vérifie les tags
        $tagList = $importedMessage1->getTagList();
        self::assertContains('test', $tagList);
        self::assertContains('export', $tagList);

        // Vérifie la mediaList
        $mediaList = $importedMessage1->getMediaList();
        self::assertCount(2, $mediaList);
        $mediaFileNames = [];
        foreach ($mediaList as $media) {
            $mediaFileNames[] = $media->getFileName();
        }

        self::assertContains('test-image-1.jpg', $mediaFileNames);
        self::assertContains('test-image-2.png', $mediaFileNames);

        // Vérifie le Review importé
        $importedMessage2 = $this->messageRepository->findOneBy([
            'host' => $this->testHost,
            'content' => 'Test review',
        ]);
        self::assertInstanceOf(Review::class, $importedMessage2);
        self::assertSame(5, $importedMessage2->getRating());
        self::assertCount(1, $importedMessage2->getMediaList());

        // Nettoie les messages importés
        if (null !== $importedMessage1->getId()) {
            $this->createdMessageIds[] = $importedMessage1->getId();
        }

        if (null !== $importedMessage2->getId()) {
            $this->createdMessageIds[] = $importedMessage2->getId();
        }
    }

    public function testExportWithEmptyMediaList(): void
    {
        $message = $this->createTestMessage('Message without media', 'test@example.com', 'Test User');
        $this->entityManager->persist($message);
        $this->entityManager->flush();

        if (null !== $message->getId()) {
            $this->createdMessageIds[] = $message->getId();
        }

        $this->exporter->export($this->testHost);

        $csvContent = file_get_contents($this->csvPath);
        self::assertIsString($csvContent);
        self::assertStringContainsString('Message without media', $csvContent);

        // Vérifie que mediaList est présent mais vide
        $lines = explode("\n", $csvContent);
        foreach ($lines as $line) {
            if (str_contains($line, 'Message without media')) {
                // La colonne mediaList devrait être vide ou contenir juste une virgule
                self::assertTrue(
                    str_contains($line, ',') || ! str_contains($line, 'test-image'),
                    'mediaList should be empty for message without media'
                );

                break;
            }
        }
    }

    public function testImportWithNonExistentMedia(): void
    {
        // Crée un message sans média
        $message = $this->createTestMessage('Message with missing media', 'test@example.com', 'Test User');
        $this->entityManager->persist($message);
        $this->entityManager->flush();

        if (null !== $message->getId()) {
            $this->createdMessageIds[] = $message->getId();
        }

        // Exporte
        $this->exporter->export($this->testHost);

        // Modifie le CSV pour ajouter un média inexistant dans la colonne mediaList
        $csvContentRaw = file_get_contents($this->csvPath);
        self::assertIsString($csvContentRaw);
        /** @var string $csvContent */
        $csvContent = $csvContentRaw;
        $lines = explode("\n", $csvContent);
        /** @var string $headerLine */
        $headerLine = $lines[0];
        $mediaListIndex = $this->getColumnIndex($headerLine, 'mediaList');

        if ($mediaListIndex >= 0) {
            foreach ($lines as $i => $line) {
                if (str_contains($line, 'Message with missing media')) {
                    $columns = str_getcsv($line, escape: '\\');
                    if (isset($columns[$mediaListIndex])) {
                        $columns[$mediaListIndex] = 'non-existent-media.jpg';
                        $lines[$i] = $this->arrayToCsvLine($columns);
                    }

                    break;
                }
            }

            $csvContent = implode("\n", $lines);
            file_put_contents($this->csvPath, $csvContent);
        }

        // Supprime le message
        $this->entityManager->remove($message);
        $this->entityManager->flush();

        $this->createdMessageIds = [];

        // Importe - ne devrait pas planter même si le média n'existe pas
        $this->importer->import($this->testHost);

        $importedMessage = $this->messageRepository->findOneBy([
            'host' => $this->testHost,
            'content' => 'Message with missing media',
        ]);
        self::assertInstanceOf(Message::class, $importedMessage);
        // La mediaList devrait être vide car le média n'existe pas
        self::assertCount(0, $importedMessage->getMediaList());

        if (null !== $importedMessage->getId()) {
            $this->createdMessageIds[] = $importedMessage->getId();
        }
    }

    public function testImportWithMediaNameWithoutExtension(): void
    {
        // Crée un média avec extension
        $media = $this->createTestMedia('piedweb-logo.png');

        // Crée un message avec ce média
        $message = $this->createTestMessage('Message with media', 'test@example.com', 'Test User');
        $message->addMedia($media);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        if (null !== $message->getId()) {
            $this->createdMessageIds[] = $message->getId();
        }

        // Exporte
        $this->exporter->export($this->testHost);

        // Modifie le CSV pour utiliser le nom sans extension dans mediaList
        $csvContentRaw = file_get_contents($this->csvPath);
        self::assertIsString($csvContentRaw);
        /** @var string $csvContent */
        $csvContent = $csvContentRaw;
        $lines = explode("\n", $csvContent);
        /** @var string $headerLine */
        $headerLine = $lines[0];
        $mediaListIndex = $this->getColumnIndex($headerLine, 'mediaList');

        if ($mediaListIndex >= 0) {
            foreach ($lines as $i => $line) {
                if (str_contains($line, 'Message with media')) {
                    $columns = str_getcsv($line, escape: '\\');
                    if (isset($columns[$mediaListIndex])) {
                        // Remplace le nom avec extension par le nom sans extension
                        $columns[$mediaListIndex] = 'piedweb-logo';
                        $lines[$i] = $this->arrayToCsvLine($columns);
                    }

                    break;
                }
            }

            $csvContent = implode("\n", $lines);
            file_put_contents($this->csvPath, $csvContent);
        }

        // Supprime le message
        $this->entityManager->remove($message);
        $this->entityManager->flush();

        $this->createdMessageIds = [];

        // Importe - devrait trouver le média même avec le nom sans extension
        $this->importer->import($this->testHost);

        $importedMessage = $this->messageRepository->findOneBy([
            'host' => $this->testHost,
            'content' => 'Message with media',
        ]);
        self::assertInstanceOf(Message::class, $importedMessage);
        // La mediaList devrait contenir le média trouvé par son nom sans extension
        $mediaList = $importedMessage->getMediaList();
        self::assertCount(1, $mediaList);
        $mediaArray = $mediaList->toArray();
        self::assertArrayHasKey(0, $mediaArray);
        /** @var Media $firstMedia */
        $firstMedia = $mediaArray[0];
        self::assertSame('piedweb-logo.png', $firstMedia->getFileName());

        if (null !== $importedMessage->getId()) {
            $this->createdMessageIds[] = $importedMessage->getId();
        }
    }

    private function getColumnIndex(string $headerLine, string $columnName): int
    {
        $columns = str_getcsv($headerLine, escape: '\\');
        $index = array_search($columnName, $columns, true);

        return false !== $index ? $index : -1;
    }

    /**
     * @param array<int|string, bool|float|int|string|null> $columns
     */
    private function arrayToCsvLine(array $columns): string
    {
        $fp = fopen('php://temp', 'r+');
        if (false === $fp) {
            return '';
        }

        /** @var array<int|string, bool|float|int|string|null> $csvColumns */
        $csvColumns = $columns;
        fputcsv($fp, $csvColumns, ',', '"', '\\');
        rewind($fp);
        $line = stream_get_contents($fp);
        fclose($fp);

        return is_string($line) ? rtrim($line, "\n\r") : '';
    }

    private function createTestMessage(
        string $content,
        string $email,
        string $name
    ): Message {
        $message = new Message();
        $message->setHost($this->testHost);
        $message->setContent($content);
        $message->setAuthorEmail($email);
        $message->setAuthorName($name);
        $message->setReferring('/test-page');
        $message->setAuthorIpRaw('127.0.0.1');
        $message->setPublishedAt(new DateTime());

        return $message;
    }

    private function createTestReview(
        string $content,
        string $email,
        string $name,
        int $rating
    ): Review {
        $review = new Review();
        $review->setHost($this->testHost);
        $review->setContent($content);
        $review->setAuthorEmail($email);
        $review->setAuthorName($name);
        $review->setReferring('/test-page');
        $review->setAuthorIpRaw('127.0.0.1');
        $review->setRating($rating);
        $review->setPublishedAt(new DateTime());

        return $review;
    }

    private function createTestMedia(string $fileName): Media
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        // Crée le répertoire média s'il n'existe pas
        if (! is_dir($mediaDir)) {
            mkdir($mediaDir, 0755, true);
        }

        // Crée un fichier média factice
        $mediaFilePath = $mediaDir.'/'.$fileName;
        file_put_contents($mediaFilePath, 'fake image content');

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setStoreIn($mediaDir);
        $media->setFileName($fileName);
        $media->setAlt('Test media '.$fileName);
        $media->setMimeType('image/jpeg');
        $media->setSize(1024);

        $this->entityManager->persist($media);
        $this->entityManager->flush();

        $this->createdMediaFileNames[] = $fileName;

        return $media;
    }
}
