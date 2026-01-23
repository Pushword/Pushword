<?php

namespace Pushword\Conversation\Service;

use DateInterval;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Pushword\Conversation\Entity\Message;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Service\Email\EmailEnvelope;
use Pushword\Core\Service\Email\NotificationEmailSender;
use Pushword\Core\Utils\LastTime;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => Message::class, 'event' => 'postPersist'])]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => Message::class, 'event' => 'postUpdate'])]
class NewMessageMailNotifier
{
    private readonly EmailEnvelope $envelope;

    private readonly string $appName;

    private readonly string $interval;

    private readonly string $host;

    public function __construct(
        private readonly NotificationEmailSender $emailSender,
        private readonly AppPool $apps,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly Security $security,
        private readonly CacheInterface $cache,
        private readonly ImportContext $importContext,
    ) {
        $this->envelope = $this->emailSender->resolveEnvelope(
            'conversation_notification_email_from',
            'conversation_notification_email_to',
        );
        $this->interval = $this->apps->get()->getStr('conversation_notification_interval');
        $this->appName = $this->apps->get()->getStr('name');
        $this->host = $this->apps->get()->getMainHost();
    }

    /**
     * @return Message[]
     */
    protected function getMessagesPostedSince(DateTimeInterface $datetime)
    {
        $query = 'SELECT m FROM '.Message::class.' m WHERE m.authorEmail IS NULL AND m.host = :host AND m.createdAt > :lastNotificationTime';
        $query = $this->em->createQuery($query)
            ->setParameter('lastNotificationTime', $datetime, 'datetime')
            ->setParameter('host', $this->host);

        /** @var Message[] */
        $result = $query->getResult();

        return $result;
    }

    public function postUpdate(Message $message): void
    {
        if ($this->importContext->isImporting()) {
            return; // Skip notifications during import
        }

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
        if ($this->importContext->isImporting()) {
            return; // Skip notifications during import
        }

        if (false === filter_var($message->getAuthorEmail(), \FILTER_VALIDATE_EMAIL)) {
            // $this->send();

            return;
        }

        $this->sendMessage($message);
    }

    public function sendMessage(Message $message): void
    {
        if (! $this->emailSender->canSend($this->envelope)) {
            $this->logger->info('Not sending conversation notification: email not configured');

            return;
        }

        $authorEmail = $message->getAuthorEmail() ?? throw new Exception();
        $subject = $this->translator->trans('adminConversationNotificationTitleSingular', ['%appName%' => $this->appName]);

        $cacheKey = 'conversation_message_'.md5($message->getContent().' '.$message->getAuthorEmail());

        $this->cache->get($cacheKey, function (ItemInterface $item) use ($message, $authorEmail, $subject): bool {
            $item->expiresAfter(600); // 10 minutes

            $envelopeWithReply = $this->envelope->withReplyTo($authorEmail);

            $textBody = htmlspecialchars_decode($message->getContent())
                ."\n\n---\n"
                .$this->translator->trans('adminConversationNotificationSentBy', [
                    '%authorName%' => $message->getAuthorName() ?? '...',
                    '%authorEmail%' => $message->getAuthorEmail(),
                ])
                ."\n".$this->translator->trans('adminConversationNotificationFrom', [
                    '%host%' => $message->host,
                    '%referring%' => $message->getReferring(),
                ]);

            $this->emailSender->send($envelopeWithReply, $subject, nl2br($textBody), $textBody);

            return true;
        });
    }

    public function send(): void
    {
        if (! $this->emailSender->canSend($this->envelope)) {
            $this->logger->info('Not sending conversation notification: email not configured');

            return;
        }

        $lastTime = new LastTime($this->projectDir.'/var/lastNewMessageNotification');
        if ($lastTime->wasRunSince(new DateInterval($this->interval))) {
            $this->logger->info('Not sending conversation notification: a previous notification was sent recently ('.$this->interval.', see `conversation_notification_interval`).');

            return;
        }

        $since = $lastTime->safeGet('2000-01-01');

        $messages = $this->getMessagesPostedSince($since);
        if ([] === $messages) {
            $this->logger->info('Not sending conversation notification: nothing to notify.');

            return;
        }

        $subject = $this->translator->trans(
            \count($messages) > 1 ? 'adminConversationNotificationTitlePlural' : 'adminConversationNotificationTitleSingular',
            ['%appName%' => $this->appName],
        );

        $sent = $this->emailSender->sendTemplated(
            $this->envelope,
            $subject,
            '@PushwordConversation/conversation/notification.html.twig',
            [
                'appName' => $this->appName,
                'messages' => $messages,
            ],
        );

        if ($sent) {
            $lastTime->set();
        }
    }
}
