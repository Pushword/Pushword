<?php

namespace Pushword\Core\Controller;

use Override;
use Pushword\Core\Component\App\AppPool;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Twig\Environment as Twig;

/**
 * Abstract base controller for Pushword page-related controllers.
 *
 * Provides common functionality for multi-domain and multi-locale support.
 */
abstract class AbstractPushwordController extends AbstractController
{
    public function __construct(
        protected readonly AppPool $apps,
        protected readonly Twig $twig,
    ) {
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
     * Get the full view path for the current app.
     */
    protected function getView(string $path): string
    {
        return $this->apps->getApp()->getView($path);
    }
}
