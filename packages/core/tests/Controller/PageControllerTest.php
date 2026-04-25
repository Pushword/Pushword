<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Controller;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Controller\FeedController;
use Pushword\Core\Controller\PageController;
use Pushword\Core\Controller\RobotsTxtController;
use Pushword\Core\Controller\SitemapController;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Group('integration')]
final class PageControllerTest extends KernelTestCase
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

    /**
     * Test that sitemap hreflang URLs use the correct host for same-host multi-locale translations.
     * Fixtures: homepage (en, localhost.dev) ↔ fr/homepage (fr, localhost.dev) ↔ fr-ca/homepage (fr-CA, localhost.dev).
     */
    public function testSitemapHreflangSameHost(): void
    {
        $response = $this->getSitemapController()->show(Request::create('/sitemap.xml'), 'xml');
        $content = (string) $response->getContent();

        // The EN homepage should have hreflang links to FR and FR-CA, all on localhost.dev
        self::assertStringContainsString('hreflang="en"', $content);
        self::assertStringContainsString('hreflang="fr"', $content);

        // All hreflang URLs for same-host translations must use the same base URL
        self::assertStringContainsString('hreflang="fr" href="https://localhost.dev/fr/homepage"', $content);
        // The self-referencing hreflang for EN homepage
        self::assertStringContainsString('hreflang="en" href="https://localhost.dev/"', $content);

        // Must NOT have wrong cross-host URLs (the bug we fixed)
        self::assertStringNotContainsString('hreflang="fr" href="https://admin-block-editor.test', $content);
    }

    /**
     * Test that sitemap hreflang URLs use the correct host for cross-host translations.
     */
    public function testSitemapHreflangCrossHost(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        // Create a page on localhost.dev with a translation on admin-block-editor.test
        $enPage = new Page();
        $enPage->setH1('Cross-host EN');
        $enPage->setSlug('cross-host-test');
        $enPage->locale = 'en';
        $enPage->host = 'localhost.dev';
        $enPage->createdAt = new DateTime();
        $enPage->updatedAt = new DateTime();
        $enPage->setMainContent('English page');

        $dePage = new Page();
        $dePage->setH1('Cross-host DE');
        $dePage->setSlug('cross-host-test-de');
        $dePage->locale = 'de';
        $dePage->host = 'admin-block-editor.test';
        $dePage->createdAt = new DateTime();
        $dePage->updatedAt = new DateTime();
        $dePage->setMainContent('German page');

        $enPage->addTranslation($dePage);

        $em->persist($enPage);
        $em->persist($dePage);
        $em->flush();

        try {
            $response = $this->getSitemapController()->show(Request::create('/sitemap.xml'), 'xml');
            $content = (string) $response->getContent();

            // The EN page's hreflang for DE must point to admin-block-editor.test, not localhost.dev
            self::assertStringContainsString(
                'hreflang="de" href="https://admin-block-editor.test/cross-host-test-de"',
                $content,
            );
            // Self-referencing hreflang must point to localhost.dev
            self::assertStringContainsString(
                'hreflang="en" href="https://localhost.dev/cross-host-test"',
                $content,
            );
        } finally {
            // Re-fetch entities since they may have been detached
            $enPage = $em->getRepository(Page::class)->findOneBy(['slug' => 'cross-host-test']);
            $dePage = $em->getRepository(Page::class)->findOneBy(['slug' => 'cross-host-test-de']);
            if (null !== $enPage && null !== $dePage) {
                $enPage->removeTranslation($dePage);
                $em->remove($dePage);
                $em->remove($enPage);
                $em->flush();
            }
        }
    }

    public function testShowRobotsTxt(): void
    {
        $robotsTxtController = self::getContainer()->get(RobotsTxtController::class);

        $response = $robotsTxtController->show(); // Request::create('/robots.txt')
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testDateShortcodeResolvedInMetaAndAlt(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $media = $em->getRepository(Media::class)->findOneBy([]);

        $page = new Page();
        $page->setH1('Escapade en date(Y)');
        $page->setSlug('date-shortcode-test');
        $page->setTitle('Voyage en date(Y)');
        $page->setSearchExcerpt('Une escapade inoubliable en date(Y).');
        $page->locale = 'en';
        $page->createdAt = new DateTime();
        $page->updatedAt = new DateTime();
        $page->setMainContent('Content here');
        if (null !== $media) {
            $page->setMainImage($media);
        }

        $em->persist($page);
        $em->flush();

        $response = $this->getPageController()->show(
            Request::create('/date-shortcode-test'),
            'date-shortcode-test',
        );

        $content = (string) $response->getContent();

        self::assertStringNotContainsString('date(Y)', $content);
        self::assertStringContainsString(date('Y'), $content);

        $em->remove($page);
        $em->flush();
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
