<?php

namespace Pushword\Conversation\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Conversation\Repository\MessageRepository;
use Pushword\Core\Component\App\AppPool;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    /** @var EntityManagerInterface */
    private $em;

    /** @var \Pushword\Core\Component\App\AppConfig */
    private $app;
    /** @var \Pushword\Core\Component\App\AppPool */
    private $apps;

    /** @var string */
    private $messageEntity;

    /** @var RouterInterface */
    private $router;

    public function __construct(EntityManagerInterface $em, string $messageEntity, AppPool $apps, RouterInterface $router)
    {
        $this->em = $em;
        $this->apps = $apps;
        $this->app = $apps->get();
        $this->messageEntity = $messageEntity;
        $this->router = $router;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('showConversation', [$this, 'showConversation'], ['is_safe' => ['html'], 'needs_environment' => true]),
            new TwigFunction('conversation', [$this, 'getConversationRoute']),
        ];
    }

    public function getConversationRoute($type)
    {
        $page = $this->apps->getCurrentPage();
        if (null === $page) {
            throw new Exception('A page must be defined...');
        }

        return $this->router->generate('pushword_conversation', [
            'type' => $type,
            'referring' => $type.'-'.$page->getRealSlug(),
            'host' => $page->getHost(),
        ]);
    }

    public function showConversation(
        Twig $env,
        string $referring,
        string $orderBy = 'createdAt DESC',
        $limit = 0,
        string $view = '/conversation/messages_list.html.twig'
    ) {
        /** @var MessageRepository $msgRepo */
        $msgRepo = $this->em->getRepository($this->messageEntity);

        $messages = $msgRepo->getMessagesPublishedByReferring($referring, $orderBy, $limit);

        $view = $this->app->getView($view, $env, '@PushwordConversation');

        return $env->render($view, ['messages' => $messages]);
    }
}
