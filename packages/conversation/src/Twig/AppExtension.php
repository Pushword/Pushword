<?php

namespace Pushword\Conversation\Twig;

use Exception;
use Pushword\Conversation\Repository\MessageRepository;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;

use function Safe\json_encode;

use Symfony\Component\Routing\RouterInterface;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

class AppExtension
{
    private readonly AppConfig $app;

    public function __construct(
        private readonly AppPool $apps,
        private readonly RouterInterface $router,
        private readonly MessageRepository $messageRepo
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

        $baseUrl = $this->router->generate('pushword_conversation', [
            'type' => $type,
            'referring' => $type.'-'.$page->getRealSlug(),
        ]);

        return $baseUrl.'?'.http_build_query([
            'host' => $page->host,
            'locale' => $page->locale,
        ]);
    }

    #[AsTwigFunction('showConversation', needsEnvironment: true, isSafe: ['html'])]
    public function showConversation(
        Twig $twig,
        string $referring,
        string $orderBy = 'createdAt ASC',
        int $limit = 0,
        string $view = '/conversation/messages_list.html.twig'
    ): string {
        $messages = $this->messageRepo->getMessagesPublishedByReferring($referring, $orderBy, $limit);

        $view = $this->app->getView($view, '@PushwordConversation');

        return $twig->render($view, ['messages' => $messages]);
    }

    /**
     * @param Page|array<string>|string $pageOrTag
     */
    #[AsTwigFunction('reviewList', needsEnvironment: true, isSafe: ['html'])]
    #[AsTwigFunction('reviews', needsEnvironment: true, isSafe: ['html'])]
    public function renderReviewList(
        Twig $twig,
        Page|array|string|null $pageOrTag = null,
        int $limit = 10,
        string $template = '/conversation/reviewList.html.twig'
    ): string {
        $pageOrTag ??= $this->apps->getCurrentPage() ?? throw new Exception('No page or tag provided');

        $tags = $this->resolveReviewTag($pageOrTag);
        $reviews = [] === $tags && '#' !== $pageOrTag ? []
          : $this->messageRepo->getPublishedReviewsByTag($tags, $limit);
        $view = $this->app->getView($template, '@PushwordConversation');

        return $twig->render($view, [
            'reviews' => $reviews,
            'page' => $this->apps->getCurrentPage(),
        ]);
    }

    /**
     * @param Page|array<string>|string $pageOrTag
     */
    #[AsTwigFunction('reviewsCount', needsEnvironment: false)]
    public function count(
        Page|array|string|null $pageOrTag = null,
    ): int {
        $pageOrTag ??= $this->apps->getCurrentPage() ?? throw new Exception('No page or tag provided');

        $tags = $this->resolveReviewTag($pageOrTag);
        $reviews = [] === $tags && '#' !== $pageOrTag ? []
          : $this->messageRepo->getPublishedReviewsByTag($tags, 0);

        return \count($reviews);
    }

    /**
     * @param Page|string|array<string> $pageOrTag
     *
     * @return array<string>
     */
    private function resolveReviewTag(Page|string|array $pageOrTag): array
    {
        if (\is_array($pageOrTag)) {
            return $pageOrTag;
        }

        if ('#' === $pageOrTag) {
            return [];
        }

        if (\is_string($pageOrTag)) {
            return '' === $pageOrTag ? [] : [trim($pageOrTag)];
        }

        return [trim($pageOrTag->getSlug())];
    }

    /**
     * Get all conversation tags as JSON string for suggestions.
     */
    #[AsTwigFunction('pw_conversation_all_tags_json')]
    public function getAllTagsJson(): string
    {
        return json_encode($this->messageRepo->getAllTags());
    }
}
