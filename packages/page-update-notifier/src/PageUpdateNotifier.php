<?php

namespace Pushword\PageUpdateNotifier;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\Repository;
use Pushword\Core\Utils\LastTime;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;

class PageUpdateNotifier
{
    private string $emailTo = '';

    private string $emailFrom = '';

    private string $appName = '';

    private string $interval = '';

    private \Pushword\Core\Component\App\AppConfig $app;

    /**
     * @var int
     */
    final public const ERROR_NO_EMAIL = 1;

    /**
     * @var int
     */
    final public const ERROR_NO_INTERVAL = 2;

    /**
     * @var int
     */
    final public const WAS_EVER_RUN_SINCE_INTERVAL = 3;

    /**
     * @var int
     */
    final public const NOTHING_TO_NOTIFY = 4;

    /**
     * @param class-string<PageInterface> $pageClass
     */
    public function __construct(
        private readonly string $pageClass,
        private readonly MailerInterface $mailer,
        private readonly AppPool $apps,
        private readonly string $varDir,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly Twig $twig,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function postUpdate(PageInterface $page): void
    {
        try {
            $this->run($page);
        } catch (\Exception $e) {
            $this->logger?->info('[PageUpdateNotifier] '.$e->getMessage());
        }
    }

    public function postPersist(PageInterface $page): void
    {
        try {
            $this->run($page);
        } catch (\Exception $e) {
            $this->logger?->info('[PageUpdateNotifier] '.$e->getMessage());
        }
    }

    /**
     * @return PageInterface[]
     */
    protected function getPageUpdatedSince(\DateTimeInterface $datetime)
    {
        $pageRepo = Repository::getPageRepository($this->em, $this->pageClass);

        $queryBuilder = $pageRepo->createQueryBuilder('p')
            ->andWhere('p.createdAt > :lastTime OR p.updatedAt > :lastTime')
            ->setParameter('lastTime', $datetime, 'datetime')
            ->orderBy('p.createdAt', 'ASC');

        $pageRepo->andHost($queryBuilder, $this->app->getMainHost());

        return $queryBuilder->getQuery()->getResult();
    }

    protected function init(PageInterface $page): void
    {
        $this->app = $this->apps->get($page->getHost());
        $this->emailFrom = \strval($this->app->getStr('page_update_notification_from'));
        $this->emailTo = \strval($this->app->getStr('page_update_notification_to'));
        $this->interval = \strval($this->app->getStr('page_update_notification_interval'));
        $this->appName = \strval($this->app->getStr('name'));
    }

    protected function checkConfig(PageInterface $page): void
    {
        $this->init($page);

        if ('' === $this->emailTo) {
            throw new \Exception('`page_update_notification_from` must be set to use this extension.', self::ERROR_NO_EMAIL);
        }

        if ('' === $this->emailFrom) {
            throw new \Exception('`page_update_notification_to` must be set to use this extension.', self::ERROR_NO_EMAIL);
        }

        if ('' === $this->interval) {
            throw new \Exception('`page_update_notification_interval` must be set to use this extension.', self::ERROR_NO_INTERVAL);
        }
    }

    public function getCacheDir(): string
    {
        $dir = $this->varDir.'/PageUpdateNotifier';
        if (! is_dir($dir)) {
            \Safe\mkdir($dir);
        }

        return $dir;
    }

    public function getCacheFilePath(): string
    {
        return $this->getCacheDir().'/lastPageUpdateNotification'; // .md5($this->app->getMainHost())
    }

    public function run(PageInterface $page): int|string
    {
        $this->checkConfig($page);

        $cache = $this->getCacheFilePath();
        $lastTime = new LastTime($cache);
        if ($lastTime->wasRunSince(new \DateInterval($this->interval))) {
            $this->logger?->info('[PageUpdateNotifier] was ever run since interval');

            return self::WAS_EVER_RUN_SINCE_INTERVAL;
        }

        if (($lastTime30min = $lastTime->get('30 minutes ago')) === null) {
            throw new \LogicException();
        }

        $pages = $this->getPageUpdatedSince($lastTime30min);
        // dd($pages);
        if ([] === $pages) {
            $this->logger?->info('[PageUpdateNotifier] Nothing to notify');

            return self::NOTHING_TO_NOTIFY;
        }

        $message = (new Email())
            ->subject(
                $this->translator->trans('admin.page.update_notification.title', ['%appName%' => $this->appName])
            )
            ->from($this->emailFrom)
            ->to($this->emailTo)
            ->html(
                $this->twig->render(
                    '@pwPageUpdateNotification/pageUpdateMailNotification.html.twig',
                    ['appName' => $this->appName,                'pages' => $pages]
                )
            );

        $lastTime->set();
        $this->mailer->send($message);

        $this->logger?->info('[PageUpdateNotifier] Notification sent for '.\count($pages).' page(s)');

        return 'Notification sent';
    }
}
