<?php

namespace Pushword\Core\Tests\Controller;

use Pushword\Core\Controller\PageController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

class PageControllerTest extends KernelTestCase
{
    public function testShowHomepage()
    {
        $slug = 'homepage';
        $response = $this->getPageController()->show($slug, 'localhost.dev', Request::create($slug));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testShowAnotherPage()
    {
        $slug = 'kitchen-sink';
        $response = $this->getPageController()->show($slug, '', Request::create('/en/'.$slug));
        $this->assertSame(301, $response->getStatusCode());
    }

    public function testShowFeed()
    {
        $slug = 'homepage';
        $response = $this->getPageController()->showFeed($slug, 'localhost.dev', Request::create('/'.$slug.'.xml'));
        $this->assertSame(301, $response->getStatusCode());
    }

    public function testShowMainFeed()
    {
        $response = $this->getPageController()->showMainFeed('localhost.dev', Request::create('/feed.xml'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testShowSitemap()
    {
        $response = $this->getPageController()->showSitemap('xml', 'localhost.dev', Request::create('/sitemap.xml'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testShowRobotsTxt()
    {
        $response = $this->getPageController()->showRobotsTxt('localhost.dev', Request::create('/robots.txt'));
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
