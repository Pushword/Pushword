<?php

namespace Pushword\Core\Component\Router;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RouterTwigExtension extends AbstractExtension
{
    /** @var RouterInterface */
    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('homepage', [$this->router, 'generatePathForHomePage']),
            new TwigFunction('page', [$this->router, 'generate']),
        ];
    }
}
