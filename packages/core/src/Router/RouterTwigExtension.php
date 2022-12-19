<?php

namespace Pushword\Core\Router;

use Pushword\Core\Entity\PageInterface as Page;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RouterTwigExtension extends AbstractExtension
{
    public function __construct(private readonly RouterInterface $router)
    {
    }

    /**
     * @return \Twig\TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('homepage', $this->router->generatePathForHomePage(...)),
            new TwigFunction('page', $this->router->generate(...)),
            new TwigFunction('is_current_page', $this->isCurrentPage(...)), // used ?
        ];
    }

    public function isCurrentPage(string $uri, ?Page $currentPage): bool
    {
        return
            null === $currentPage || $uri !== $this->router->generate($currentPage->getRealSlug())
            ? false
            : true;
    }
}
