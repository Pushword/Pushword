<?php

namespace Pushword\Core\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Controller\FeedController;
use Pushword\Core\Controller\PageController;
use Pushword\Core\Controller\RobotsTxtController;
use Pushword\Core\Controller\SitemapController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Group('integration')]
class PageControllerTest extends KernelTestCase
{
    public function testShowHomepage(): void
    {
        $slug = 'homepage';
        $response = $this->getPageController()->show(Request::create($slug), $slug);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testShowAnotherPage(): void
    {
        $slug = 'kitchen-sink';
        $response = $this->getPageController()->show(Request::create($slug), $slug);
        // file_put_contents('debug.html', $response->getContent());
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $slug = 'kitchen-sink';
        $this->expectException(NotFoundHttpException::class);
        $response = $this->getPageController()->show(Request::create('/en/'.$slug), '/en/'.$slug);
        self::assertSame(404, $response->getStatusCode());
    }

    public function testShowFeed(): void
    {
        $slug = 'homepage';
        $response = $this->getFeedController()->show(Request::create('/'.$slug.'.xml'), $slug);
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testShowMainFeed(): void
    {
        $response = $this->getFeedController()->showMain(Request::create('/feed.xml'));
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testShowSitemap(): void
    {
        $response = $this->getSitemapController()->show(Request::create('/sitemap.xml'), 'xml');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testShowRobotsTxt(): void
    {
        $robotsTxtController = self::getContainer()->get(RobotsTxtController::class);

        $response = $robotsTxtController->show(); // Request::create('/robots.txt')
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
    }

    public function getPageController(): PageController
    {
        return self::getContainer()->get(PageController::class);
    }

    public function getFeedController(): FeedController
    {
        return self::getContainer()->get(FeedController::class);
    }

    public function getSitemapController(): SitemapController
    {
        return self::getContainer()->get(SitemapController::class);
    }
}
