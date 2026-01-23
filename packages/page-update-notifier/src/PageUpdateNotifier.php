<?php

declare(strict_types=1);

namespace Pushword\PageUpdateNotifier;

use DateInterval;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use LogicException;
use Psr\Log\LoggerInterface;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\Email\EmailEnvelope;
use Pushword\Core\Service\Email\NotificationEmailSender;
use Pushword\Core\Utils\LastTime;

use function Safe\mkdir;

use Symfony\Contracts\Translation\TranslatorInterface;

class PageUpdateNotifier
{
    private EmailEnvelope $envelope;

    private string $appName = '';

    private string $interval = '';

    private AppConfig $app;

    public function __construct(
        private readonly NotificationEmailSender $emailSender,
        private readonly AppPool $apps,
        private readonly string $varDir,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function postUpdate(Page $page): void
    {
        try {
            $this->run($page);
        } catch (Exception $exception) {
            $this->logger?->info('[PageUpdateNotifier] '.$exception->getMessage());
        }
    }

    public function postPersist(Page $page): void
    {
        try {
            $this->run($page);
        } catch (Exception $exception) {
            $this->logger?->info('[PageUpdateNotifier] '.$exception->getMessage());
        }
    }

    /**
     * @return Page[]
     */
    protected function getPageUpdatedSince(DateTimeInterface $datetime): mixed
    {
        $pageRepo = $this->em->getRepository(Page::class);

        $queryBuilder = $pageRepo->createQueryBuilder('p')
            ->andWhere('p.createdAt > :lastTime OR p.updatedAt > :lastTime')
            ->setParameter('lastTime', $datetime, 'datetime')
            ->orderBy('p.createdAt', 'ASC');

        $pageRepo->andHost($queryBuilder, $this->app->getMainHost());

        return $queryBuilder->getQuery()->getResult();
    }

    protected function init(Page $page): void
    {
        $this->app = $this->apps->get($page->host);
        $this->envelope = $this->emailSender->resolveEnvelope(
            'page_update_notification_from',
            'page_update_notification_to',
            $page->host,
        );
        $this->interval = $this->app->getStr('page_update_notification_interval');
        $this->appName = $this->app->getStr('name');
    }

    protected function checkConfig(Page $page): void
    {
        $this->init($page);

        if (! $this->emailSender->canSend($this->envelope)) {
            throw new Exception('`page_update_notification_from` and `page_update_notification_to` (or global defaults) must be set.', NotificationStatus::ErrorNoEmail->value);
        }

        if ('' === $this->interval) {
            throw new Exception('`page_update_notification_interval` must be set.', NotificationStatus::ErrorNoInterval->value);
        }
    }

    public function getCacheDir(): string
    {
        $dir = $this->varDir.'/PageUpdateNotifier';
        if (! is_dir($dir)) {
            mkdir($dir);
        }

        return $dir;
    }

    public function getCacheFilePath(): string
    {
        return $this->getCacheDir().'/lastPageUpdateNotification';
    }

    public function run(Page $page): NotificationStatus|string
    {
        $this->checkConfig($page);

        $cache = $this->getCacheFilePath();
        $lastTime = new LastTime($cache);
        if ($lastTime->wasRunSince(new DateInterval($this->interval))) {
            $this->logger?->info('[PageUpdateNotifier] was ever run since interval');

            return NotificationStatus::WasEverRunSinceInterval;
        }

        if (($lastTime30min = $lastTime->get('30 minutes ago')) === null) {
            throw new LogicException();
        }

        $pages = $this->getPageUpdatedSince($lastTime30min);
        if ([] === $pages) {
            $this->logger?->info('[PageUpdateNotifier] Nothing to notify');

            return NotificationStatus::NothingToNotify;
        }

        $subject = $this->translator->trans('adminPageUpdateNotificationTitle', ['%appName%' => $this->appName]);

        $sent = $this->emailSender->sendWithRenderedHtml(
            $this->envelope,
            $subject,
            '@pwPageUpdateNotification/pageUpdateMailNotification.html.twig',
            ['appName' => $this->appName, 'pages' => $pages],
        );

        if ($sent) {
            $lastTime->set();
            $this->logger?->info('[PageUpdateNotifier] Notification sent for '.\count($pages).' page(s)');

            return 'Notification sent';
        }

        return NotificationStatus::ErrorNoEmail;
    }
}
