<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\Generator;

use DateTime;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\StaticGenerator\Generator\AbstractGenerator;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\Generator\StaticPageRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class StaticPageRendererTest extends KernelTestCase
{
    public function testPagePagerAndFeedMatchHttpRendering(): void
    {
        self::bootKernel();
        self::getContainer()->get(PagesGenerator::class);
        $renderKernel = AbstractGenerator::getKernel();
        $renderer = $renderKernel->getContainer()->get(StaticPageRenderer::class);
        $pages = self::getContainer()->get(PageRepository::class)->getPublishedPages('localhost.dev');
        $parent = null;
        foreach ($pages as $page) {
            if ($page->hasChildrenPages()) {
                $parent = $page;

                break;
            }
        }

        self::assertInstanceOf(Page::class, $parent);

        foreach (['page', 'pager', 'feed'] as $kind) {
            $feed = 'feed' === $kind;
            $path = '/localhost.dev/'.$parent->getRealSlug().match ($kind) {
                'pager' => '/2',
                'feed' => '.xml',
                default => '',
            };
            $httpRequest = Request::create($path);
            $httpRequest->attributes->set('_pushword_page', $parent);
            $expected = $renderKernel->handle($httpRequest);

            $renderKernel->getContainer()->get('services_resetter')->reset();
            $actual = $renderer->render(Request::create($path), $parent, $feed);

            self::assertSame($expected->getStatusCode(), $actual->getStatusCode());
            self::assertSame($expected->headers->get('content-type'), $actual->headers->get('content-type'));
            self::assertSame($expected->getContent(), $actual->getContent());
        }
    }

    public function testNonPageRouteIsRejected(): void
    {
        self::bootKernel();
        self::getContainer()->get(PagesGenerator::class);
        $renderer = AbstractGenerator::getKernel()->getContainer()->get(StaticPageRenderer::class);
        $page = self::getContainer()->get(PageRepository::class)->getPage('homepage', 'localhost.dev');
        self::assertInstanceOf(Page::class, $page);

        $this->expectException(LogicException::class);
        $renderer->render(Request::create('/localhost.dev/robots.txt'), $page);
    }

    public function testInlineTwigCanReadTheSyntheticRequest(): void
    {
        self::bootKernel();
        self::getContainer()->get(PagesGenerator::class);
        $renderKernel = AbstractGenerator::getKernel();
        $renderer = $renderKernel->getContainer()->get(StaticPageRenderer::class);

        $page = new Page(false);
        $page->host = 'localhost.dev';
        $page->slug = 'static-request-context';
        $page->locale = '';
        $page->h1 = 'Static request context';
        $page->createdAt = new DateTime('2 days ago');
        $page->publishedAt = new DateTime('2 days ago');
        $page->mainContent = '{{ app.request.attributes.get("_route") }} / {{ app.request.attributes.get("pager") }}';

        $renderer->render(Request::create('/localhost.dev/static-request-context'), $page);
        self::assertSame('en', $page->locale);

        $renderKernel->getContainer()->get('services_resetter')->reset();
        $response = $renderer->render(Request::create('/localhost.dev/static-request-context/2'), $page);

        self::assertStringContainsString('custom_host_pushword_page / 2', (string) $response->getContent());
    }
}
