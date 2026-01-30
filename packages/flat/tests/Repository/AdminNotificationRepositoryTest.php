<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Pushword\Flat\Entity\AdminNotification;
use Pushword\Flat\Repository\AdminNotificationRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AdminNotificationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private AdminNotificationRepository $repository;

    /** @var AdminNotification[] */
    private array $testNotifications = [];

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        /** @var AdminNotificationRepository $repository */
        $repository = self::getContainer()->get(AdminNotificationRepository::class);
        $this->repository = $repository;
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

    private function createNotification(
        string $type = AdminNotification::TYPE_CONFLICT,
        string $message = 'Test notification',
        ?string $host = null,
        bool $isRead = false,
    ): AdminNotification {
        $notification = new AdminNotification();
        $notification->type = $type;
        $notification->message = $message;
        $notification->host = $host;
        $notification->isRead = $isRead;

        $this->em->persist($notification);
        $this->em->flush();

        $this->testNotifications[] = $notification;

        return $notification;
    }

    public function testFindUnreadReturnsOnlyUnreadNotifications(): void
    {
        $unread1 = $this->createNotification(message: 'Unread 1', isRead: false);
        $unread2 = $this->createNotification(message: 'Unread 2', isRead: false);
        $this->createNotification(message: 'Read', isRead: true);

        $result = $this->repository->findUnread();

        self::assertCount(2, $result);
        self::assertContains($unread1, $result);
        self::assertContains($unread2, $result);
    }

    public function testFindUnreadFiltersByHost(): void
    {
        $this->createNotification(message: 'Host A', host: 'host-a.com', isRead: false);
        $globalNotification = $this->createNotification(message: 'Global', host: null, isRead: false);
        $this->createNotification(message: 'Host B', host: 'host-b.com', isRead: false);

        $result = $this->repository->findUnread('host-a.com');

        self::assertCount(2, $result);
        self::assertContains($globalNotification, $result);
    }

    public function testCountUnreadReturnsCorrectCount(): void
    {
        $this->createNotification(isRead: false);
        $this->createNotification(isRead: false);
        $this->createNotification(isRead: true);

        self::assertSame(2, $this->repository->countUnread());
    }

    public function testCountUnreadFiltersByHost(): void
    {
        $this->createNotification(host: 'host-a.com', isRead: false);
        $this->createNotification(host: null, isRead: false); // Global
        $this->createNotification(host: 'host-b.com', isRead: false);

        self::assertSame(2, $this->repository->countUnread('host-a.com'));
    }

    public function testMarkAsReadUpdatesNotification(): void
    {
        $notification = $this->createNotification(isRead: false);

        self::assertFalse($notification->isRead);
        self::assertNotNull($notification->id);

        $this->repository->markAsRead($notification->id);

        $this->em->clear();

        $refreshed = $this->repository->find($notification->id);
        self::assertNotNull($refreshed);
        self::assertTrue($refreshed->isRead);

        $this->testNotifications = [$refreshed];
    }

    public function testMarkAsReadWithNonexistentIdDoesNotThrow(): void
    {
        $this->repository->markAsRead(999999);

        self::assertSame(0, $this->repository->countUnread());
    }

    public function testMarkAllAsReadUpdatesAllUnread(): void
    {
        $notification1 = $this->createNotification(isRead: false);
        $notification2 = $this->createNotification(isRead: false);
        $this->createNotification(isRead: true);

        $count = $this->repository->markAllAsRead();

        self::assertSame(2, $count);

        $this->em->clear();

        $refreshed1 = $this->repository->find($notification1->id);
        $refreshed2 = $this->repository->find($notification2->id);

        self::assertNotNull($refreshed1);
        self::assertNotNull($refreshed2);
        self::assertTrue($refreshed1->isRead);
        self::assertTrue($refreshed2->isRead);

        $this->testNotifications = [$refreshed1, $refreshed2];
    }

    public function testMarkAllAsReadFiltersByHost(): void
    {
        $hostA = $this->createNotification(host: 'host-a.com', isRead: false);
        $this->createNotification(host: 'host-b.com', isRead: false);

        $count = $this->repository->markAllAsRead('host-a.com');

        self::assertSame(1, $count);

        $this->em->clear();

        $refreshedA = $this->repository->find($hostA->id);
        self::assertNotNull($refreshedA);
        self::assertTrue($refreshedA->isRead);

        $this->testNotifications = [$refreshedA];
    }

    public function testFindByTypeFiltersCorrectly(): void
    {
        $uniqueHost = 'find-by-type-test-'.uniqid().'.com';
        $conflict1 = $this->createNotification(type: AdminNotification::TYPE_CONFLICT, host: $uniqueHost);
        $syncError = $this->createNotification(type: AdminNotification::TYPE_SYNC_ERROR, host: $uniqueHost);
        $conflict2 = $this->createNotification(type: AdminNotification::TYPE_CONFLICT, host: $uniqueHost);

        $result = $this->repository->findByType(AdminNotification::TYPE_CONFLICT, $uniqueHost);

        self::assertContains($conflict1, $result);
        self::assertContains($conflict2, $result);

        $resultIds = array_map(static fn (AdminNotification $n): ?int => $n->id, $result);
        self::assertNotContains($syncError->id, $resultIds);
    }

    public function testFindByTypeFiltersByHost(): void
    {
        $uniqueHostA = 'host-a-'.uniqid().'.com';
        $uniqueHostB = 'host-b-'.uniqid().'.com';

        $hostAConflict = $this->createNotification(
            type: AdminNotification::TYPE_CONFLICT,
            host: $uniqueHostA,
        );
        $hostBConflict = $this->createNotification(
            type: AdminNotification::TYPE_CONFLICT,
            host: $uniqueHostB,
        );

        $result = $this->repository->findByType(AdminNotification::TYPE_CONFLICT, $uniqueHostA);

        self::assertContains($hostAConflict, $result);

        $resultIds = array_map(static fn (AdminNotification $n): ?int => $n->id, $result);
        self::assertNotContains($hostBConflict->id, $resultIds);
    }

    public function testFindByTypeRespectsLimit(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->createNotification(type: AdminNotification::TYPE_SYNC_ERROR);
        }

        $result = $this->repository->findByType(AdminNotification::TYPE_SYNC_ERROR, null, 3);

        self::assertCount(3, $result);
    }

    public function testDeleteOlderThanRemovesOldNotifications(): void
    {
        $recentNotification = $this->createNotification(message: 'Recent notification');

        $oldNotification = new AdminNotification();
        $oldNotification->type = AdminNotification::TYPE_LOCK_INFO;
        $oldNotification->message = 'Old notification';

        $this->em->persist($oldNotification);
        $this->em->flush();
        $this->testNotifications[] = $oldNotification;

        $connection = $this->em->getConnection();
        $connection->executeStatement(
            'UPDATE admin_notification SET created_at = ? WHERE id = ?',
            [new DateTimeImmutable('-60 days')->format('Y-m-d H:i:s'), $oldNotification->id],
        );

        $deleted = $this->repository->deleteOlderThan(30);

        self::assertSame(1, $deleted);

        $this->em->clear();

        $remaining = $this->repository->find($recentNotification->id);
        self::assertNotNull($remaining);

        $removedResult = $this->repository->find($oldNotification->id);
        self::assertNull($removedResult);

        $this->testNotifications = [$remaining];
    }

    public function testFindUnreadOrdersByCreatedAtDescending(): void
    {
        $uniqueHost = 'ordering-test-'.uniqid().'.com';

        $first = $this->createNotification(message: 'First', host: $uniqueHost);
        $second = $this->createNotification(message: 'Second', host: $uniqueHost);
        $third = $this->createNotification(message: 'Third', host: $uniqueHost);

        $connection = $this->em->getConnection();
        $connection->executeStatement(
            'UPDATE admin_notification SET created_at = ? WHERE id = ?',
            [new DateTimeImmutable('-3 hours')->format('Y-m-d H:i:s'), $first->id],
        );
        $connection->executeStatement(
            'UPDATE admin_notification SET created_at = ? WHERE id = ?',
            [new DateTimeImmutable('-2 hours')->format('Y-m-d H:i:s'), $second->id],
        );
        $connection->executeStatement(
            'UPDATE admin_notification SET created_at = ? WHERE id = ?',
            [new DateTimeImmutable('-1 hour')->format('Y-m-d H:i:s'), $third->id],
        );

        $this->em->clear();

        $result = $this->repository->findUnread($uniqueHost);

        $resultIds = array_map(static fn (AdminNotification $n): ?int => $n->id, $result);

        $firstPos = array_search($first->id, $resultIds, true);
        $secondPos = array_search($second->id, $resultIds, true);
        $thirdPos = array_search($third->id, $resultIds, true);

        self::assertNotFalse($firstPos, 'First notification should be in result');
        self::assertNotFalse($secondPos, 'Second notification should be in result');
        self::assertNotFalse($thirdPos, 'Third notification should be in result');

        self::assertLessThan($firstPos, $thirdPos, 'Third (newest) should come before First (oldest)');
        self::assertLessThan($firstPos, $secondPos, 'Second should come before First');
        self::assertLessThan($secondPos, $thirdPos, 'Third should come before Second');

        $freshFirst = $this->repository->find($first->id);
        $freshSecond = $this->repository->find($second->id);
        $freshThird = $this->repository->find($third->id);
        self::assertNotNull($freshFirst);
        self::assertNotNull($freshSecond);
        self::assertNotNull($freshThird);
        $this->testNotifications = [$freshFirst, $freshSecond, $freshThird];
    }
}
