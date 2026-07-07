<?php

namespace Pushword\Conversation\Twig;

use Exception;
use Pushword\Conversation\Repository\MessageRepository;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;

use function Safe\json_encode;

use Symfony\Component\Routing\RouterInterface;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

class AppExtension
{
    private readonly SiteConfig $app;

    public function __construct(
        private readonly SiteRegistry $apps,
        private readonly RouterInterface $router,
        private readonly MessageRepository $messageRepo
    ) {
        $this->app = $apps->get();
    }

    #[AsTwigFunction('conversation')]
    public function getConversationRoute(string $type, string $referring = ''): string
    {
        $page = $this->apps->getCurrentPage() ?? throw new Exception('Run from a Pushword Page context');

        $baseUrl = $this->router->generate('pushword_conversation', [
            'type' => $type,
            'referring' => ('' !== $referring ? $referring : $type).'_'.$page->host.'/'.$page->getRealSlug(),
        ]);

        // Prefix the page's live host so the URL is absolute. A statically served
        // page (pw:static, no PHP) must fetch the form from the dynamic host — a
        // relative route would resolve against its own PHP-less origin and 404.
        // base_live_url already sends the CORS headers for these origins.
        return $this->apps->get($page->host)->getStr('base_live_url').$baseUrl.'?'.http_build_query([
            'host' => $page->host,
            'locale' => $page->locale,
        ]);
    }

    #[AsTwigFunction('conversationFormBtn', needsEnvironment: true, isSafe: ['html'])]
    public function conversationFormBtn(
        Twig $twig,
        string $label,
        string $type = 'ms-message',
        string $class = 'link-btn',
        string $referring = '',
    ): string {
        $url = $this->getConversationRoute($type, $referring);
        $view = $this->app->getView('/conversation/formBtn.html.twig', '@PushwordConversation');

        return $twig->render($view, [
            'url' => LinkProvider::obfuscate($url),
            'label' => $label,
            'class' => $class,
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
            'defaultReplyAuthor' => $this->app->getStr('conversation_review_default_reply_author'),
            'siteName' => $this->app->getName(),
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
