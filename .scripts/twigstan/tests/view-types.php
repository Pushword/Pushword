<?php

declare(strict_types=1);

namespace Pushword\TwigStan\Tests;

use function PHPStan\Testing\assertType;

use Pushword\Core\Controller\AbstractPushwordController;
use Pushword\Core\Site\SiteConfig;

function resolveSiteViews(SiteConfig $site, string $dynamic): void
{
    assertType("'@Pushword/component/link.html.twig'", $site->getView('/component/link.html.twig'));
    assertType("'@Pushword/component/link.html.twig'", $site->getView(path: '/component/link.html.twig', fallback: '@Pushword'));
    assertType("'@Pushword/component/link.html.twig'", $site->getView(fallback: '@Pushword', path: '/component/link.html.twig'));
    assertType("'@Pushword/component/link.html.twig'", $site->getView('@Pushword/component/link.html.twig'));
    assertType("'@Pushword/page/page.html.twig'", $site->getView(null, '@PushwordCore'));
    assertType("'@Pushword/page/page.html.twig'", $site->getView());
    assertType('string', $site->getView($dynamic));
    assertType('string', $site->getView('/component/link.html.twig', $dynamic));
}

abstract class ViewController extends AbstractPushwordController
{
    public function resolveControllerViews(string $dynamic): void
    {
        assertType("'@Pushword/component/link.html.twig'", $this->getView('@Pushword/component/link.html.twig'));
        assertType('string', $this->getView($dynamic));
    }
}
