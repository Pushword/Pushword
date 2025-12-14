<?php

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
use Pushword\Core\Utils\LastTime;

use function Safe\mkdir;

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

    private AppConfig $app;

    /**
     * @var int
     */
    final public const int ERROR_NO_EMAIL = 1;

    /**
     * @var int
     */
    final public const int ERROR_NO_INTERVAL = 2;

    /**
     * @var int
     */
    final public const int WAS_EVER_RUN_SINCE_INTERVAL = 3;

    /**
     * @var int
     */
    final public const int NOTHING_TO_NOTIFY = 4;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly AppPool $apps,
        private readonly string $varDir,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly Twig $twig,
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
        $this->app = $this->apps->get($page->getHost());
        $this->emailFrom = $this->app->getStr('page_update_notification_from');
        $this->emailTo = $this->app->getStr('page_update_notification_to');
        $this->interval = $this->app->getStr('page_update_notification_interval');
        $this->appName = $this->app->getStr('name');
    }

    protected function checkConfig(Page $page): void
    {
        $this->init($page);

        if ('' === $this->emailTo) {
            throw new Exception('`page_update_notification_from` must be set to use this extension.', self::ERROR_NO_EMAIL);
        }

        if ('' === $this->emailFrom) {
            throw new Exception('`page_update_notification_to` must be set to use this extension.', self::ERROR_NO_EMAIL);
        }

        if ('' === $this->interval) {
            throw new Exception('`page_update_notification_interval` must be set to use this extension.', self::ERROR_NO_INTERVAL);
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
        return $this->getCacheDir().'/lastPageUpdateNotification'; // .md5($this->app->getMainHost())
    }

    public function run(Page $page): int|string
    {
        $this->checkConfig($page);

        $cache = $this->getCacheFilePath();
        $lastTime = new LastTime($cache);
        if ($lastTime->wasRunSince(new DateInterval($this->interval))) {
            $this->logger?->info('[PageUpdateNotifier] was ever run since interval');

            return self::WAS_EVER_RUN_SINCE_INTERVAL;
        }

        if (($lastTime30min = $lastTime->get('30 minutes ago')) === null) {
            throw new LogicException();
        }

        $pages = $this->getPageUpdatedSince($lastTime30min);
        // dd($pages);
        if ([] === $pages) {
            $this->logger?->info('[PageUpdateNotifier] Nothing to notify');

            return self::NOTHING_TO_NOTIFY;
        }

        $message = new Email()
            ->subject(
                $this->translator->trans('adminPageUpdateNotificationTitle', ['%appName%' => $this->appName])
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
