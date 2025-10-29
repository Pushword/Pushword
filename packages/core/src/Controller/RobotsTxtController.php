<?php

namespace Pushword\Core\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles robots.txt rendering for SEO purposes.
 */
final class RobotsTxtController extends AbstractPushwordController
{
    #[Route(
        '/{_locale}robots.txt',
        name: 'pushword_page_robots_txt',
        methods: ['GET', 'HEAD'],
        requirements: ['_locale' => RoutePatterns::LOCALE],
        priority: -30
    )]
    #[Route(
        '/{host}/{_locale}robots.txt',
        name: 'custom_host_pushword_page_robots_txt',
        methods: ['GET', 'HEAD'],
        requirements: ['_locale' => RoutePatterns::LOCALE, 'host' => RoutePatterns::HOST],
        priority: -31
    )]
    public function show(): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/plain');

        return $this->render(
            $this->getView('/page/robots.txt.twig'),
            [
                'app_base_url' => $this->apps->getApp()->getBaseUrl(),
            ],
            $response
        );
    }
}
