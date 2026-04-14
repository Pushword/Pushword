<?php

namespace Pushword\Core\Router;

use Exception;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Twig\Attribute\AsTwigFunction;

final readonly class RouterTwigExtension
{
    public function __construct(
        private PushwordRouteGenerator $router,
        private PageRepository $pageRepository,
    ) {
    }

    #[AsTwigFunction('page')]
    public function getPageUri(mixed ...$args): string
    {
        $slug = $args[0] ?? throw new Exception('must use a string or page object');
        if (! \is_string($slug) && ! $slug instanceof Page) {
            throw new Exception('`page()` first argument must be a string or a Page Object');
        }

        if ($slug instanceof Page) {
            $host = $slug->host;
        }

        $arg2 = $args[1] ?? null;
        if (\is_string($arg2)) {
            $host = $arg2;
        } elseif (\is_int($arg2)) {
            $pager = $arg2;
        }

        $canonical = \is_bool($arg2) ? $arg2 : false;

        $arg3 = $args[2] ?? null;
        if (! isset($host) && \is_string($arg3)) {
            $host = $arg3;
        }

        $pager ??= \is_int($arg3) ? $arg3 : null;

        $arg4 = $args[3] ?? null;
        $host ??= \is_string($arg4) ? $arg4 : null;

        $page = $slug instanceof Page ? $slug : $this->pageRepository->getPageBySlug($slug, $host ?? '');

        if (null !== $page && $page->hasRedirection()) {
            $redirectionUrl = $page->getRedirectionUrl();
            if (str_starts_with($redirectionUrl, '/')) {
                $targetSlug = ltrim($redirectionUrl, '/');
                $targetPage = $this->pageRepository->getPageBySlug($targetSlug, $page->host);
                if (null !== $targetPage) {
                    return $this->router->generate($targetPage, $canonical, $pager, $host);
                }
            }

            return $redirectionUrl;
        }

        return $this->router->generate($slug, $canonical, $pager, $host);
    }

    #[AsTwigFunction('is_current_page')]
    public function isCurrentPage(string $uri, ?Page $currentPage): bool
    {
        return
            null === $currentPage || $uri !== $this->router->generate($currentPage->getRealSlug())
            ? false
            : true;
    }
}
