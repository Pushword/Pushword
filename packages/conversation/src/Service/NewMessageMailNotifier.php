<?php

namespace Pushword\Conversation\Service;

use DateInterval;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Pushword\Conversation\Entity\Message;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Utils\LastTime;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.conversation.entity_message%', 'event' => 'postPersist'])]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.conversation.entity_message%', 'event' => 'postUpdate'])]
class NewMessageMailNotifier
{
    private readonly string $emailTo;

    private readonly string $emailFrom;

    private readonly string $appName;

    private readonly string $interval;

    private readonly string $host;

    /**
     * @param class-string<Message> $message Entity
     */
    public function __construct(
        private readonly string $message,
        private readonly MailerInterface $mailer,
        private readonly AppPool $apps,
        private readonly string $projectDir,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly Security $security,
    ) {
        $this->emailTo = $this->apps->get()->getStr('conversation_notification_email_to');
        $this->emailFrom = $this->apps->get()->getStr('conversation_notification_email_from');
        $this->interval = $this->apps->get()->getStr('conversation_notification_interval');
        $this->appName = $this->apps->get()->getStr('name');
        $this->host = $this->apps->get()->getMainHost();
    }

    /**
     * @return Message[]
     */
    protected function getMessagesPostedSince(DateTimeInterface $datetime)
    {
        $query = 'SELECT m FROM '.$this->message.' m WHERE m.authorEmail IS NULL AND m.host = :host AND m.createdAt > :lastNotificationTime';
        $query = $this->em->createQuery($query)
            ->setParameter('lastNotificationTime', $datetime, 'datetime')
            ->setParameter('host', $this->host);

        /** @var Message[] */
        $result = $query->getResult();

        return $result;
    }

    public function postUpdate(Message $message): void
    {
        if (null !== $this->security->getUser()) {
            return;
        } // we send notification only for message sent by not logged people

        if (false === filter_var($message->getAuthorEmail(), \FILTER_VALIDATE_EMAIL)) {
            return; // no valid email, so nothing to reply, no notification.
        }

        $this->sendMessage($message);
    }

    public function postPersist(Message $message): void
    {
        if (false === filter_var($message->getAuthorEmail(), \FILTER_VALIDATE_EMAIL)) {
            // $this->send();

            return;
        }

        $this->sendMessage($message);
    }

    public function sendMessage(Message $message): void
    {
        $authorEmail = $message->getAuthorEmail() ?? throw new Exception();

        $templatedEmail = (new TemplatedEmail())
            ->subject(
                $this->translator->trans('admin.conversation.notification.title.singular', ['%appName%' => $this->appName])
            )
            ->from($this->emailFrom)
            ->to($this->emailTo)
            ->replyTo($authorEmail)
            ->text(
                htmlspecialchars_decode($message->getContent())
                ."\n\n---\n"
                .'Envoyé par '.($message->getAuthorName() ?? '...')
                .' - '.$message->getAuthorEmail()
                ."\n".'Depuis '.$message->getHost().' › form['.$message->getReferring().']'
            );

        $this->mailer->send($templatedEmail);
    }

    public function send(): void
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
    }
}
