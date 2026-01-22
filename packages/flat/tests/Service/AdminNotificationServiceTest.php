<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Pushword\Flat\Entity\AdminNotification;
use Pushword\Flat\Repository\AdminNotificationRepository;
use Pushword\Flat\Service\AdminNotificationService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AdminNotificationServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private AdminNotificationRepository $repository;

    private AdminNotificationService $service;

    /** @var AdminNotification[] */
    private array $testNotifications = [];

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;

        /** @var AdminNotificationRepository $repository */
        $repository = self::getContainer()->get(AdminNotificationRepository::class);
        $this->repository = $repository;

        /** @var AdminNotificationService $service */
        $service = self::getContainer()->get(AdminNotificationService::class);
        $this->service = $service;
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->testNotifications as $notification) {
            $this->em->remove($notification);
        }

        if ([] !== $this->testNotifications) {
            $this->em->flush();
        }

        $this->testNotifications = [];

        parent::tearDown();
    }

    public function testCreateNotificationPersistsToDatabase(): void
    {
        $notification = $this->service->createNotification(
            'test_type',
            'Test message',
            'example.com',
            ['key' => 'value'],
        );

        $this->testNotifications[] = $notification;

        self::assertNotNull($notification->id);
        self::assertSame('test_type', $notification->type);
        self::assertSame('Test message', $notification->message);
        self::assertSame('example.com', $notification->host);
        self::assertSame(['key' => 'value'], $notification->metadata);
        self::assertFalse($notification->isRead);

        $this->em->clear();

        $found = $this->repository->find($notification->id);
        self::assertNotNull($found);
        self::assertSame('test_type', $found->type);

        $this->testNotifications = [$found];
    }

    public function testCreateNotificationWithoutEmail(): void
    {
        $notification = $this->service->createNotification(
            'test_type',
            'Test message',
            null,
            [],
            sendEmail: false,
        );

        $this->testNotifications[] = $notification;

        self::assertNotNull($notification->id);
        self::assertSame('test_type', $notification->type);
    }

    public function testNotifyConflictCreatesCorrectMessageFormat(): void
    {
        $conflictData = [
            'entityType' => 'page',
            'entityId' => 42,
            'winner' => 'flat',
            'backupFile' => '/var/content/backup.md',
        ];

        $notification = $this->service->notifyConflict($conflictData, 'example.com');
        $this->testNotifications[] = $notification;

        self::assertSame(AdminNotification::TYPE_CONFLICT, $notification->type);
        self::assertStringContainsString('Conflict detected on page #42', $notification->message);
        self::assertStringContainsString('Winner: flat', $notification->message);
        self::assertStringContainsString('Backup: backup.md', $notification->message);
        self::assertSame('example.com', $notification->host);
    }

    public function testNotifyConflictHandlesMissingData(): void
    {
        $notification = $this->service->notifyConflict([]);
        $this->testNotifications[] = $notification;

        self::assertSame(AdminNotification::TYPE_CONFLICT, $notification->type);
        self::assertStringContainsString('unknown', $notification->message);
    }

    public function testNotifySyncErrorCreatesCorrectMessageFormat(): void
    {
        $notification = $this->service->notifySyncError('Sync failed: Database error', 'example.com');
        $this->testNotifications[] = $notification;

        self::assertSame(AdminNotification::TYPE_SYNC_ERROR, $notification->type);
        self::assertSame('Sync failed: Database error', $notification->message);
        self::assertSame(['error' => 'Sync failed: Database error'], $notification->metadata);
        self::assertSame('example.com', $notification->host);
    }

    public function testNotifyLockChangeCreatesNotificationWithoutEmail(): void
    {
        $notification = $this->service->notifyLockChange('acquired', 'example.com', 'admin@test.com');
        $this->testNotifications[] = $notification;

        self::assertSame(AdminNotification::TYPE_LOCK_INFO, $notification->type);
        self::assertSame('Lock acquired for example.com by admin@test.com', $notification->message);
        self::assertSame(['action' => 'acquired', 'user' => 'admin@test.com'], $notification->metadata);
    }

    public function testNotifyLockChangeWithoutHostAndUser(): void
    {
        $notification = $this->service->notifyLockChange('released');
        $this->testNotifications[] = $notification;

        self::assertSame(AdminNotification::TYPE_LOCK_INFO, $notification->type);
        self::assertSame('Lock released', $notification->message);
    }

    public function testCountUnread(): void
    {
        $this->service->createNotification('type1', 'Message 1', 'example.com');
        $this->service->createNotification('type2', 'Message 2', 'example.com');

        $readNotification = new AdminNotification();
        $readNotification->type = 'type3';
        $readNotification->message = 'Message 3';
        $readNotification->host = 'example.com';
        $readNotification->isRead = true;

        $this->em->persist($readNotification);
        $this->em->flush();

        $notifications = $this->repository->findBy(['host' => 'example.com']);
        foreach ($notifications as $n) {
            $this->testNotifications[] = $n;
        }

        $count = $this->service->countUnread('example.com');

        self::assertSame(2, $count);
    }

    public function testGetUnread(): void
    {
        $notification1 = $this->service->createNotification('type1', 'Message 1', 'test-host.com');
        $notification2 = $this->service->createNotification('type2', 'Message 2', 'test-host.com');
        $this->testNotifications[] = $notification1;
        $this->testNotifications[] = $notification2;

        $unread = $this->service->getUnread('test-host.com');

        self::assertCount(2, $unread);
    }

    public function testMarkAsRead(): void
    {
        $notification = $this->service->createNotification('type', 'Message', 'example.com');
        $this->testNotifications[] = $notification;

        self::assertNotNull($notification->id);
        self::assertFalse($notification->isRead);

        $this->service->markAsRead($notification->id);

        $this->em->clear();

        $found = $this->repository->find($notification->id);
        self::assertNotNull($found);
        self::assertTrue($found->isRead);

        $this->testNotifications = [$found];
    }

    public function testMarkAllAsRead(): void
    {
        $notification1 = $this->service->createNotification('type1', 'Message 1', 'mark-all.com');
        $notification2 = $this->service->createNotification('type2', 'Message 2', 'mark-all.com');
        $this->testNotifications[] = $notification1;
        $this->testNotifications[] = $notification2;

        $count = $this->service->markAllAsRead('mark-all.com');

        self::assertSame(2, $count);

        $this->em->clear();

        $found1 = $this->repository->find($notification1->id);
        $found2 = $this->repository->find($notification2->id);
        self::assertNotNull($found1);
        self::assertNotNull($found2);
        self::assertTrue($found1->isRead);
        self::assertTrue($found2->isRead);

        $this->testNotifications = [$found1, $found2];
    }
}
