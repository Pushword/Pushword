<?php

namespace Pushword\Core\Tests\Controller;

use Pushword\Core\Controller\PageController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        $slug = 'kitchen-sink-block';
        $request = Request::create($slug);
        $request->attributes->set('host', 'admin-block-editor.test');

        $response = $this->getPageController()->show($request, $slug);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $slug = 'kitchen-sink';
        $this->expectException(NotFoundHttpException::class);
        $response = $this->getPageController()->show(Request::create('/en/'.$slug), '/en/'.$slug);
        self::assertSame(404, $response->getStatusCode());
    }

    public function testShowFeed(): void
    {
        $slug = 'homepage';
        $response = $this->getPageController()->showFeed(Request::create('/'.$slug.'.xml'), $slug);
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testShowMainFeed(): void
    {
        $response = $this->getPageController()->showMainFeed(Request::create('/feed.xml'));
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testShowSitemap(): void
    {
        $response = $this->getPageController()->showSitemap(Request::create('/sitemap.xml'), 'xml');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testShowRobotsTxt(): void
    {
        $response = $this->getPageController()->showRobotsTxt();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
    }

    public function getPageController(): PageController
    {
        return self::getContainer()->get(PageController::class);
    }
}
