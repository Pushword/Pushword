<?php

namespace Pushword\Conversation\Service;

use DateInterval;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Pushword\Conversation\Entity\MessageInterface;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Utils\LastTime;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class NewMessageMailNotifier
{
    private string $emailTo;

    private string $emailFrom;

    private string $appName;

    private string $interval;

    private string $host;

    /**
     .
     *
     * @param class-string<MessageInterface> $message Entity
     */
    public function __construct(
        private string $message,
        private MailerInterface $mailer,
        private AppPool $apps,
        private string $projectDir,
        private EntityManagerInterface $em,
        private TranslatorInterface $translator,
        private LoggerInterface $logger
    ) {
        $this->emailTo = \strval($this->apps->get()->get('conversation_notification_email_to'));
        $this->emailFrom = \strval($this->apps->get()->get('conversation_notification_email_from'));
        $this->interval = \strval($this->apps->get()->get('conversation_notification_interval'));
        $this->appName = \strval($this->apps->get()->get('name'));
        $this->host = $this->apps->get()->getMainHost();
    }

    /**
     * @return MessageInterface[]
     */
    protected function getMessagesPostedSince(DateTimeInterface $datetime)
    {
        $query = 'SELECT m FROM '.$this->message.' m WHERE m.host = :host AND m.createdAt > :lastNotificationTime';
        $query = $this->em->createQuery($query)
            ->setParameter('lastNotificationTime', $datetime, 'datetime')
            ->setParameter('host', $this->host);

        return $query->getResult(); // @phpstan-ignore-line
    }

    public function postPersist(): void
    {
        $this->send();
    }

    /**
     * @return bool|void
     */
    public function send()
    {
        if ('' === $this->emailTo) {
            $this->logger->info('Not sending conversation notification : `conversation_notification_email_to` is not configured.');

            return;
        }

        $lastTime = new LastTime($this->projectDir.'/var/lastNewMessageNotification');
        if ($lastTime->wasRunSince(new DateInterval($this->interval))) {
            $this->logger->info('Not sending conversation notification : a previous notification was send not a long time ago ('.$this->interval.', see `conversation_notification_interval`).');

            return;
        }

        $since = $lastTime->safeGet('2000-01-01');

        $messages = $this->getMessagesPostedSince($since);
        if ([] === $messages) {
            $this->logger->info('Not sending conversation notification : nothing to notify.');

            return;
        }

        $templatedEmail = (new TemplatedEmail())
            ->subject(
                $this->translator->trans(
                    'admin.conversation.notification.title.'.(\count($messages) > 1 ? 'plural' : 'singular'),
                    ['%appName%' => $this->appName]
                )
            )
            ->from($this->emailFrom)
            ->to($this->emailTo)
            ->htmlTemplate('@PushwordConversation/conversation/notification.html.twig')
            ->context([
                'appName' => $this->appName,
                'messages' => $messages,
            ]);

        $lastTime->set();
        $this->mailer->send($templatedEmail);

        return true;
    }
}
