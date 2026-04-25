<?php

namespace Pushword\Core\Controller;

use Override;
use Pushword\Core\Site\RequestContext;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Twig\Environment as Twig;

abstract class AbstractPushwordController extends AbstractController
{
    public function __construct(
        protected readonly SiteRegistry $apps,
        protected readonly RequestContext $requestContext,
        protected readonly Twig $twig,
    ) {
    }

    /** @param array<mixed> $parameters */
    #[Override]
    protected function renderView(string $view, array $parameters = []): string
    {
        return $this->twig->render($view, $parameters);
    }

    protected function getView(string $path): string
    {
        return $this->requestContext->getCurrentSite()->getView($path);
    }
}
