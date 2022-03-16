<?php

namespace Pushword\Core\Tests\Controller;

use Pushword\Core\Controller\PageController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageControllerTest extends KernelTestCase
{
    public function testShowHomepage()
    {
        $slug = 'homepage';
        $response = $this->getPageController()->show(Request::create($slug), $slug, 'localhost.dev');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testShowAnotherPage()
    {
        $slug = 'kitchen-sink';
        $response = $this->getPageController()->show(Request::create($slug), $slug, '');
        // file_put_contents('debug.html', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());

        $slug = 'kitchen-sink';
        $this->expectException(NotFoundHttpException::class);
        $response = $this->getPageController()->show(Request::create('/en/'.$slug), '/en/'.$slug, '');
        // $this->assertSame(404, $response->getStatusCode());
    }

    public function testShowFeed()
    {
        $slug = 'homepage';
        $response = $this->getPageController()->showFeed(Request::create('/'.$slug.'.xml'), $slug, 'localhost.dev');
        $this->assertSame(301, $response->getStatusCode());
    }

    public function testShowMainFeed()
    {
        $response = $this->getPageController()->showMainFeed(Request::create('/feed.xml'), 'localhost.dev');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testShowSitemap()
    {
        $response = $this->getPageController()->showSitemap(Request::create('/sitemap.xml'), 'xml', 'localhost.dev');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testShowRobotsTxt()
    {
        $response = $this->getPageController()->showRobotsTxt(Request::create('/robots.txt'), 'localhost.dev');
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @return PageController
     */
    public function getPageController()
    {
        return $this->getService('Pushword\Core\Controller\PageController');
    }

    public function getService(string $service)
    {
        self::bootKernel();

        return self::$kernel->getContainer()->get($service);
    }
}
