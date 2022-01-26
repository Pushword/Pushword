<?php

namespace Pushword\PageUpdateNotifier;

use DateInterval;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use LogicException;
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
    private \Symfony\Component\Mailer\MailerInterface $mailer;

    private string $emailTo = '';

    private string $emailFrom = '';

    private string $appName = '';

    private string $varDir;

    private string $interval = '';

    private \Doctrine\ORM\EntityManagerInterface $em;

    private \Symfony\Contracts\Translation\TranslatorInterface $translator;

    /**
     * @var class-string<PageInterface>
     */
    private string $pageClass;

    private \Pushword\Core\Component\App\AppConfig $app;

    private Twig $twig;

    private \Pushword\Core\Component\App\AppPool $apps;

    /**
     * @var int
     */
    public const ERROR_NO_EMAIL = 1;

    /**
     * @var int
     */
    public const ERROR_NO_INTERVAL = 2;

    /**
     * @var int
     */
    public const WAS_EVER_RUN_SINCE_INTERVAL = 3;

    /**
     * @var int
     */
    public const NOTHING_TO_NOTIFY = 4;

    /**
     * @param class-string<PageInterface> $pageClass
     */
    public function __construct(
        string $pageClass,
        MailerInterface $mailer,
        AppPool $appPool,
        string $varDir,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        Twig $twig
    ) {
        $this->mailer = $mailer;
        $this->apps = $appPool;
        $this->varDir = $varDir;
        $this->em = $entityManager;
        $this->translator = $translator;
        $this->pageClass = $pageClass;
        $this->twig = $twig;
    }

    public function postUpdate(PageInterface $page): void
    {
        try {
            $this->run($page);
        } catch (Exception $exception) {
        }
    }

    public function postPersist(PageInterface $page): void
    {
        try {
            $this->run($page);
        } catch (Exception $e) {
            // todo log exception
        }
    }

    /**
     * @return PageInterface[]
     */
    protected function getPageUpdatedSince(DateTimeInterface $datetime)
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
        $this->emailFrom = \strval($this->app->get('page_update_notification_from'));
        $this->emailTo = \strval($this->app->get('page_update_notification_to'));
        $this->interval = \strval($this->app->get('page_update_notification_interval'));
        $this->appName = \strval($this->app->get('name'));
    }

    protected function checkConfig(PageInterface $page): void
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
            \Safe\mkdir($dir);
        }

        return $dir;
    }

    public function getCacheFilePath(): string
    {
        return $this->getCacheDir().'/lastPageUpdateNotification'.md5($this->app->getMainHost());
    }

    /**
     * @return string|int
     */
    public function run(PageInterface $page)
    {
        $this->checkConfig($page);

        $cache = $this->getCacheFilePath();
        $lastTime = new LastTime($cache);
        if ($lastTime->wasRunSince(new DateInterval($this->interval))) {
            return self::WAS_EVER_RUN_SINCE_INTERVAL;
        }

        if (($lastTime30min = $lastTime->get('30 minutes ago')) === null) {
            throw new LogicException();
        }

        $pages = $this->getPageUpdatedSince($lastTime30min);
        //dd($pages);
        if ([] === $pages) {
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

        return 'Notify send for '.\count($pages).' page(s)';
    }
}
