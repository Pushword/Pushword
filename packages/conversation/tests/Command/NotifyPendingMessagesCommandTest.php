<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Command;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Pushword\Conversation\Command\NotifyPendingMessagesCommand;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Service\ImportContext;
use Pushword\Conversation\Service\NewMessageMailNotifier;
use Pushword\Core\Service\Email\NotificationEmailSender;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[Group('integration')]
final class NotifyPendingMessagesCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    /** @var list<string> */
    private array $messageUuids = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->messageUuids as $uuid) {
            $message = $this->em->getRepository(Message::class)->findOneBy(['uuid' => $uuid]);
            if (null !== $message) {
                $this->em->remove($message);
            }
        }

        $this->em->flush();
        parent::tearDown();
    }

    public function testFixedDeadlineDeliversLatestStateAndLateUpdatesNeverSendAgain(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(static function (Email $email): void {
            $text = $email->getTextBody();
            self::assertIsString($text);
            self::assertStringStartsWith("Updated optional details\n", $text);
            self::assertSame('visitor@example.org', $email->getReplyTo()[0]->getAddress());
        });
        $notifier = $this->createNotifier($mailer);
        $before = time();
        $message = $this->persistDelayedMessage();
        $deadline = $message->notificationSendAfter;
        self::assertNotNull($deadline);
        self::assertGreaterThanOrEqual($before + 600, $deadline->getTimestamp());
        self::assertLessThanOrEqual(time() + 600, $deadline->getTimestamp());

        $message->setContent('Updated optional details');
        $message->setCustomProperty('notificationDelay', 1200);

        $this->em->flush();
        self::assertSame($deadline, $message->notificationSendAfter);
        $notifier->postUpdate($message);
        $this->assertReport($notifier, 0, 0);

        $message->notificationSendAfter = new DateTimeImmutable('-1 second');
        $this->em->flush();
        $uuid = $message->uuid;
        $this->em->clear();
        $this->assertReport($notifier, 1, 0);

        $message = $this->em->getRepository(Message::class)->findOneBy(['uuid' => $uuid]);
        self::assertInstanceOf(Message::class, $message);
        self::assertNotNull($message->notificationSentAt);
        $message->setContent('Details submitted after the notification');
        $this->em->flush();

        // A fresh notifier has no content cache: the database must suppress this update.
        $freshNotifier = $this->createNotifier($mailer);
        $freshNotifier->postUpdate($message);
        $this->assertReport($freshNotifier, 0, 0);
    }

    public function testAbandonedFormIsDeliveredAndFailedDeliveryRetries(): void
    {
        $attempts = 0;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(2))->method('send')->willReturnCallback(static function () use (&$attempts): void {
            if (1 === ++$attempts) {
                throw new TransportException('Temporary mail failure');
            }
        });
        $notifier = $this->createNotifier($mailer);
        $message = $this->persistDelayedMessage();
        $message->notificationSendAfter = new DateTimeImmutable('-1 second');

        $this->em->flush();

        $this->assertReport($notifier, 0, 1);
        self::assertNull($message->notificationSentAt);
        $this->assertReport($notifier, 1, 0);
        self::assertNotNull($message->notificationSentAt);
        $this->assertReport($this->createNotifier($mailer), 0, 0);
    }

    public function testCustomDelayIsMeasuredInSeconds(): void
    {
        $before = time();
        $message = $this->persistDelayedMessage(1200);
        self::assertNotNull($message->notificationSendAfter);
        self::assertGreaterThanOrEqual($before + 1200, $message->notificationSendAfter->getTimestamp());
        self::assertLessThanOrEqual(time() + 1200, $message->notificationSendAfter->getTimestamp());
    }

    public function testImportedMessagesDoNotScheduleOrSendNotifications(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        $importContext = new ImportContext();
        $importContext->startImport();

        $notifier = $this->createNotifier($mailer, $importContext);
        $message = new Message();
        $message->authorEmail = 'visitor@example.org';
        $message->setCustomProperty('notificationDelay', 600);

        $notifier->prePersist($message);
        $notifier->postPersist($message);
        $notifier->postUpdate($message);
        self::assertNull($message->notificationSendAfter);
    }

    public function testDistinctMessagesWithIdenticalContentAreBothDelivered(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(2))->method('send');
        $notifier = $this->createNotifier($mailer);
        for ($i = 0; $i < 2; ++$i) {
            $message = $this->persistDelayedMessage();
            $message->notificationSendAfter = new DateTimeImmutable('-1 second');
        }

        $this->em->flush();
        $this->assertReport($notifier, 2, 0);
    }

    public function testWorkerUsesTheMessagesSiteAndLocale(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(static function (Email $email): void {
            self::assertSame('[Secondary site] : Nouveau message', $email->getSubject());
            self::assertSame('secondary@example.org', $email->getTo()[0]->getAddress());
        });
        $notifier = $this->createNotifier($mailer);
        $site = self::getContainer()->get(SiteRegistry::class)->get('pushword.piedweb.com');
        $site->setCustomProperty('name', 'Secondary site');
        $site->setCustomProperty('conversation_notification_email_to', 'secondary@example.org');

        $message = $this->persistDelayedMessage();
        $message->host = 'pushword.piedweb.com';
        $message->notificationSendAfter = new DateTimeImmutable('-1 second');

        $this->em->flush();

        $this->assertReport($notifier, 1, 0);
    }

    public function testRenderingFailureStaysPendingAndSoftDeletedMessagesAreExcluded(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        $notifier = $this->createNotifier($mailer);
        $message = $this->persistDelayedMessage();
        $message->notificationSendAfter = new DateTimeImmutable('-1 second');

        $deleted = $this->persistDelayedMessage();
        $deleted->notificationSendAfter = new DateTimeImmutable('-1 second');
        $deleted->softDelete();

        $this->em->flush();
        self::getContainer()->get(Environment::class)->setLoader(new ArrayLoader());

        $this->assertReport($notifier, 0, 1);
        self::assertNull($message->notificationSentAt);
        self::assertNull($deleted->notificationSentAt);
    }

    public function testOverlappingWorkerDoesNotSend(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        $notifier = $this->createNotifier($mailer);
        $message = $this->persistDelayedMessage();
        $message->notificationSendAfter = new DateTimeImmutable('-1 second');

        $this->em->flush();
        $locks = new LockFactory(new InMemoryStore());
        $lock = $locks->createLock('pushword_conversation_notifications');
        self::assertTrue($lock->acquire());

        try {
            [$code, $output] = $this->runWorker($notifier, $locks);
            self::assertSame(0, $code);
            self::assertSame(['tool' => 'pw:conversation:notify-pending', 'result' => 'blocked', 'locked' => true], json_decode($output, true, flags: \JSON_THROW_ON_ERROR));
        } finally {
            $lock->release();
        }
    }

    /** @return iterable<string, array{?int}> */
    public static function immediateDelayProvider(): iterable
    {
        yield 'no delay' => [null];
        yield 'zero delay' => [0];
    }

    #[DataProvider('immediateDelayProvider')]
    public function testUnmarkedAndZeroDelayContactsRemainImmediate(?int $delay): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');
        $notifier = $this->createNotifier($mailer);
        $message = new Message()->setContent('Immediate contact');
        $message->authorEmail = 'visitor@example.org';
        $message->host = 'localhost.dev';
        if (null !== $delay) {
            $message->setCustomProperty('notificationDelay', $delay);
        }

        $notifier->prePersist($message);
        $notifier->postPersist($message);
        self::assertNull($message->notificationSendAfter);
    }

    private function persistDelayedMessage(int $delay = 600): Message
    {
        $message = new Message()->setContent('Contact details only');
        $message->host = 'localhost.dev';
        $message->authorEmail = 'visitor@example.org';
        $message->locale = 'fr';
        $message->setCustomProperty('notificationDelay', $delay);
        $this->messageUuids[] = $message->getOrGenerateUuid();
        $this->em->persist($message);
        $this->em->flush();

        return $message;
    }

    private function createNotifier(MailerInterface $mailer, ?ImportContext $importContext = null): NewMessageMailNotifier
    {
        $apps = self::getContainer()->get(SiteRegistry::class);
        $apps->get()->setCustomProperty('conversation_notification_email_to', 'team@example.org');
        $twig = self::getContainer()->get(Environment::class);

        return new NewMessageMailNotifier(
            new NotificationEmailSender($mailer, $apps, $twig, null),
            $apps,
            self::getContainer()->getParameter('kernel.project_dir'),
            $this->em,
            self::getContainer()->get(TranslatorInterface::class),
            new NullLogger(),
            self::createStub(Security::class),
            new ArrayAdapter(),
            $importContext ?? new ImportContext(),
            $twig,
        );
    }

    private function assertReport(NewMessageMailNotifier $notifier, int $sent, int $failed): void
    {
        [$code, $output] = $this->runWorker($notifier);
        self::assertSame($failed > 0 ? 1 : 0, $code);
        self::assertSame([
            'tool' => 'pw:conversation:notify-pending',
            'result' => $failed > 0 ? 'failed' : 'done',
            'sent' => $sent,
            'failed' => $failed,
            'ignored' => 0,
        ], json_decode($output, true, flags: \JSON_THROW_ON_ERROR));
    }

    /** @return array{int, string} */
    private function runWorker(NewMessageMailNotifier $notifier, ?LockFactory $locks = null): array
    {
        $worker = new NotifyPendingMessagesCommand(
            $this->em,
            $notifier,
            $locks ?? new LockFactory(new InMemoryStore()),
            self::getContainer()->get(LocaleSwitcher::class),
            self::getContainer()->get(SiteRegistry::class),
            new NullLogger(),
        );
        $output = new BufferedOutput();
        $code = $worker($output, 'agent');

        return [$code, $output->fetch()];
    }
}
