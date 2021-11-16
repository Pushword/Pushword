<?php

namespace Pushword\Conversation\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Conversation\Entity\MessageInterface;
use Pushword\Conversation\Repository\MessageRepository;
use Pushword\Core\Component\App\AppPool;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private \Doctrine\ORM\EntityManagerInterface $em;

    private \Pushword\Core\Component\App\AppConfig $app;

    private \Pushword\Core\Component\App\AppPool $apps;

    /**
     * @var class-string<MessageInterface>
     */
    private string $messageEntity;

    private \Symfony\Component\Routing\RouterInterface $router;

    /**
     * @param class-string<MessageInterface> $messageEntity
     */
    public function __construct(EntityManagerInterface $entityManager, string $messageEntity, AppPool $appPool, RouterInterface $router)
    {
        $this->em = $entityManager;
        $this->apps = $appPool;
        $this->app = $appPool->get();
        $this->messageEntity = $messageEntity;
        $this->router = $router;
    }

    /**
     * @return \Twig\TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('showConversation', function (Twig $twig, string $referring, string $orderBy, $limit, string $view): string {
                return $this->showConversation($twig, $referring, $orderBy, $limit, $view);
            }, ['is_safe' => ['html'], 'needs_environment' => true]),
            new TwigFunction('conversation', function ($type): string {
                return $this->getConversationRoute($type);
            }),
        ];
    }

    public function getConversationRoute(string $type): string
    {
        $page = $this->apps->getCurrentPage();
        if (! $page instanceof \Pushword\Core\Entity\PageInterface) {
            throw new Exception('A page must be defined...');
        }

        return $this->router->generate('pushword_conversation', [
            'type' => $type,
            'referring' => $type.'-'.$page->getRealSlug(),
            'host' => $page->getHost(),
        ]);
    }

    public function showConversation(
        Twig $twig,
        string $referring,
        string $orderBy = 'createdAt ASC',
        int $limit = 0,
        string $view = '/conversation/messages_list.html.twig'
    ): string {
        /** @var MessageRepository $msgRepo */
        $msgRepo = $this->em->getRepository($this->messageEntity);

        $messages = $msgRepo->getMessagesPublishedByReferring($referring, $orderBy, $limit);

        $view = $this->app->getView($view, '@PushwordConversation');

        return $twig->render($view, ['messages' => $messages]);
    }
}
