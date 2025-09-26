<?php

namespace Pushword\Conversation\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Conversation\Entity\Message;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Symfony\Component\Routing\RouterInterface;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

class AppExtension
{
    private readonly AppConfig $app;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AppPool $apps,
        private readonly RouterInterface $router
    ) {
        $this->app = $apps->get();
    }

    #[AsTwigFunction('conversation')]
    public function getConversationRoute(string $type): string
    {
        $page = $this->apps->getCurrentPage();
        if (! $page instanceof Page) {
            throw new Exception('A page must be defined...');
        }

        return $this->router->generate('pushword_conversation', [
            'type' => $type,
            'referring' => $type.'-'.$page->getRealSlug(),
            'host' => $page->getHost(),
        ]);
    }

    #[AsTwigFunction('showConversation', isSafe: ['html'], needsEnvironment: true)]
    public function showConversation(
        Twig $twig,
        string $referring,
        string $orderBy = 'createdAt ASC',
        int $limit = 0,
        string $view = '/conversation/messages_list.html.twig'
    ): string {
        $msgRepo = $this->em->getRepository(Message::class);

        $messages = $msgRepo->getMessagesPublishedByReferring($referring, $orderBy, $limit);

        $view = $this->app->getView($view, '@PushwordConversation');

        return $twig->render($view, ['messages' => $messages]);
    }
}
