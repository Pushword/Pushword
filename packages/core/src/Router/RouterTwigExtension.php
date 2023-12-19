<?php

namespace Pushword\Core\Router;

use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Entity\PageInterface as Page;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[\Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag('twig.extension')]
final class RouterTwigExtension extends AbstractExtension
{
    public function __construct(private readonly PushwordRouteGenerator $router)
    {
    }

    /**
     * @return \Twig\TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('homepage', $this->router->generatePathForHomePage(...)),
            new TwigFunction('page', $this->getPageUri(...)),
            new TwigFunction('is_current_page', $this->isCurrentPage(...)), // used ?
        ];
    }

    private function getPageUri(mixed ...$args): string
    {
        $slug = $args[0] ?? throw new \Exception('must use a string or page object');
        if (! \is_string($slug) && ! $slug instanceof PageInterface) {
            throw new \Exception('`page()` first argument must be a string or a Page Object');
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

        return $this->router->generate($slug, $canonical, $pager, $host);
    }

    public function isCurrentPage(string $uri, ?Page $currentPage): bool
    {
        return
            null === $currentPage || $uri !== $this->router->generate($currentPage->getRealSlug())
            ? false
            : true;
    }
}
