<?php

declare(strict_types=1);

namespace Pushword\Conversation\Command;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Service\NewMessageMailNotifier;
use Pushword\Core\Command\AgentOutputTrait;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Translation\LocaleSwitcher;
use Throwable;

#[AsCommand(name: 'pw:conversation:notify-pending', description: 'Send due conversation notifications using the latest saved messages')]
final class NotifyPendingMessagesCommand
{
    use AgentOutputTrait;

    private bool $agentMode = false;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NewMessageMailNotifier $notifier,
        private readonly LockFactory $lockFactory,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly SiteRegistry $apps,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Option(description: 'Output format: auto, agent or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);
        $lock = $this->lockFactory->createLock('pushword_conversation_notifications');
        if (! $lock->acquire()) {
            if ($this->agentMode) {
                $this->writeAgentJson($output, ['tool' => 'pw:conversation:notify-pending', 'result' => 'blocked', 'locked' => true]);
            } else {
                $output->writeln('Another conversation notification worker is running.');
            }

            return Command::SUCCESS;
        }

        $sent = 0;
        $failed = 0;
        $ignored = 0;

        try {
            /** @var Message[] $messages */
            $messages = $this->em->createQuery('SELECT m FROM '.Message::class.' m WHERE m.notificationSendAfter <= :now AND m.notificationSentAt IS NULL AND m.deletedAt IS NULL ORDER BY m.notificationSendAfter ASC')
                ->setParameter('now', new DateTimeImmutable(), 'datetime_immutable')
                ->setMaxResults(100)
                ->getResult();

            foreach ($messages as $message) {
                $lock->refresh();
                $this->em->refresh($message);
                if (null !== $message->notificationSentAt || null !== $message->deletedAt) {
                    ++$ignored;

                    continue;
                }

                try {
                    $locale = $message->locale ?? $this->apps->get($message->host)->locale;
                    $delivered = $this->localeSwitcher->runWithLocale($locale, fn (): bool => $this->notifier->sendMessage($message));
                } catch (Throwable $error) {
                    $this->logger->error('Conversation notification remains pending', ['uuid' => $message->uuid, 'exception' => $error]);
                    ++$failed;

                    continue;
                }

                if (! $delivered) {
                    ++$failed;

                    continue;
                }

                $message->notificationSentAt = new DateTimeImmutable();
                $this->em->flush();
                ++$sent;
            }
        } finally {
            $lock->release();
        }

        if ($this->agentMode) {
            $this->writeAgentJson($output, ['tool' => 'pw:conversation:notify-pending', 'result' => $failed > 0 ? 'failed' : 'done', 'sent' => $sent, 'failed' => $failed, 'ignored' => $ignored]);
        } else {
            $output->writeln(\sprintf('%d sent, %d failed, %d ignored.', $sent, $failed, $ignored));
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
