<?php

namespace Pushword\Core\Controller;

use Override;
use Pushword\Core\Component\App\AppPool;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment as Twig;

/**
 * Abstract base controller for Pushword page-related controllers.
 *
 * Provides common functionality for multi-domain and multi-locale support.
 */
abstract class AbstractPushwordController extends AbstractController
{
    public function __construct(
        protected readonly RequestStack $requestStack,
        protected readonly AppPool $apps,
        protected readonly Twig $twig,
    ) {
        $this->initHost($requestStack);
    }

    /**
     * Returns a rendered view.
     * Use by abstract controller without deprecation message.
     *
     * @param array<mixed> $parameters
     */
    #[Override]
    protected function renderView(string $view, array $parameters = []): string
    {
        return $this->twig->render($view, $parameters);
    }

    /**
     * Initialize the current app based on the request host.
     * Supports both explicit host from request attributes and implicit host lookup.
     */
    protected function initHost(RequestStack|Request $request): void
    {
        $request = $request instanceof Request ? $request : $request->getCurrentRequest();

        if (! $request instanceof Request) {
            return;
        }

        $host = $request->attributes->getString('host', '');
        if ('' !== $host) {
            $this->apps->switchCurrentApp($host);

            return;
        }

        $host = $this->apps->findHost($request->getHost());
        if ('' !== $host) {
            $this->apps->switchCurrentApp($host);
        }
    }

    /**
     * Get the full view path for the current app.
     */
    protected function getView(string $path): string
    {
        return $this->apps->getApp()->getView($path);
    }
}
