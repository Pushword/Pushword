<?php

namespace Pushword\PageUpdateNotifier;

use DateInterval;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Core\Component\App\AppConfig;
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
    /** @var MailerInterface */
    private $mailer;
    /** @var string */
    private $emailTo;
    /** @var string */
    private $emailFrom;
    /** @var string */
    private $appName;
    /** @var string */
    private $varDir;
    private $interval;
    /** @var EntityManagerInterface */
    private $em;
    /** @var TranslatorInterface */
    private $translator;
    /** @var string */
    private $pageClass;
    /** @var AppConfig */
    private $app;
    /** @var Twig */
    private $twig;

    /** @var AppPool */
    private $apps;

    const ERROR_NO_EMAIL = 1;
    const ERROR_NO_INTERVAL = 2;
    const WAS_EVER_RUN_SINCE_INTERVAL = 3;
    const NOTHING_TO_NOTIFY = 4;

    public function __construct(
        string $pageClass,
        MailerInterface $mailer,
        AppPool $apps,
        string $varDir,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        Twig $twig
    ) {
        $this->mailer = $mailer;
        $this->apps = $apps;
        $this->varDir = $varDir;
        $this->em = $entityManager;
        $this->translator = $translator;
        $this->pageClass = $pageClass;
        $this->twig = $twig;
    }

    public function postUpdate($page)
    {
        try {
            $this->run($page);
        } catch (Exception $e) {
            // todo log exception
        }
    }

    public function postPersist($page)
    {
        try {
            $this->run($page);
        } catch (Exception $e) {
            // todo log exception
        }
    }

    protected function getPageUpdatedSince($datetime)
    {
        $pageRepo = Repository::getPageRepository($this->em, $this->pageClass);

        $queryBuilder = $pageRepo->createQueryBuilder('p')
            ->andWhere('p.createdAt > :lastTime OR p.updatedAt > :lastTime')
            ->setParameter('lastTime', $datetime)
            ->orderBy('p.createdAt', 'ASC');

        $pageRepo->andHost($queryBuilder, $this->app->getMainHost());

        return $queryBuilder->getQuery()->getResult();
    }

    protected function init(PageInterface $page)
    {
        $this->app = $this->apps->get($page->getHost());
        $this->emailFrom = $this->app->get('notifier_email');
        $this->emailTo = $this->app->get('page_update_notification_mail');
        $this->interval = $this->app->get('page_update_notification_interval');
        $this->appName = $this->app->get('name');
    }

    protected function checkConfig($page)
    {
        $this->init($page);

        if (! $this->emailTo) {
            throw new Exception('`page_update_notification_mail` must be set to use this extension.', self::ERROR_NO_EMAIL);
        }

        if (! $this->emailFrom) {
            throw new Exception('`notifier_email` must be set to use this extension.', self::ERROR_NO_EMAIL);
        }

        if (! $this->interval) {
            throw new Exception('`notifier_email` must be set to use this extension.', self::ERROR_NO_INTERVAL);
        }
    }

    public function getCacheDir()
    {
        $dir = $this->varDir.'/PageUpdateNotifier';
        if (! is_dir($dir)) {
            mkdir($dir);
        }

        return $dir;
    }

    public function getCacheFilePath()
    {
        return $this->getCacheDir().'/lastPageUpdateNotification'.md5($this->app->getMainHost());
    }

    public function run(PageInterface $page)
    {
        if ($error = $this->checkConfig($page)) {
            return $error;
        }

        $cache = $this->getCacheFilePath();
        $lastTime = new LastTime($cache);
        if ($lastTime->wasRunSince(new DateInterval($this->interval))) {
            return self::WAS_EVER_RUN_SINCE_INTERVAL;
        }

        $pages = $this->getPageUpdatedSince($lastTime->get('30 minutes ago'));
        //dd($pages);
        if (empty($pages)) { // impossible
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
