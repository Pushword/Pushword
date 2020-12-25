<?php

namespace Pushword\Conversation\Service;

use DateInterval;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Utils\LastTime;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Move it to a plugin (todo).
 */
class NewMessageMailNotifier
{
    /** @var MailerInterface */
    private $mailer;
    /** @var AppPool */
    private $apps;
    /** @var EntityManagerInterface */
    private $em;
    /** @var TranslatorInterface */
    private $translator;
    private $emailTo;
    private $emailFrom;
    private $appName;
    private $rootDir;
    private $interval;
    private $message;
    private $host;

    /**
     * Undocumented function.
     *
     * @param string $message Entity
     */
    public function __construct(
        $message,
        MailerInterface $mailer,
        AppPool $apps,
        $rootDir,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator
    ) {
        $this->mailer = $mailer;
        $this->apps = $apps;
        $this->emailTo = $this->apps->get()->get('conversation_notification_email_to');
        $this->emailFrom = $this->apps->get()->get('conversation_notification_email_from');
        $this->interval = $this->apps->get()->get('conversation_notification_interval');
        $this->appName = $this->apps->get()->get('name');
        $this->host = $this->apps->get()->getMainHost();
        $this->rootDir = $rootDir;
        $this->em = $entityManager;
        $this->translator = $translator;
        $this->message = $message;
    }

    protected function getMessagesPostedSince($datetime)
    {
        $query = 'SELECT m FROM '.$this->message.' m WHERE m.host = :host AND m.createdAt > :lastNotificationTime';
        $query = $this->em->createQuery($query)
            ->setParameter('lastNotificationTime', $datetime)
            ->setParameter('host', $this->host);

        return $query->getResult();
    }

    public function send()
    {
        if (! $this->emailTo) {
            return;
        }

        $lastTime = new LastTime($this->rootDir.'/../var/lastNewMessageNotification');
        if (false === $lastTime->wasRunSince(new DateInterval($this->interval))) {
            return;
        }

        $messages = $this->getMessagesPostedSince($lastTime->get('15 minutes ago'));
        if (empty($messages)) {
            return;
        }

        $message = (new TemplatedEmail())
            ->subject(
                $this->translator->trans(
                    'admin.conversation.notification.title.'.(\count($messages) > 1 ? 'plural' : 'singular'),
                    ['%appName%' => $this->appName]
                )
            )
            ->from($this->emailFrom)
            ->to($this->emailTo)
            ->htmlTemplate('@PushwordConversation/notification.html.twig')
            ->context([
                'appName' => $this->appName,
                'messages' => $messages,
            ]);

        $lastTime->set();
        $this->mailer->send($message);

        return true;
    }
}
