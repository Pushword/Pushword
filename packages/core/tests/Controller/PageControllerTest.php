<?php

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

    public function testCustomHostRouteWithUnknownHostReturns404(): void
    {
        // /wp-aothait.php matches the custom-host route ({host}=wp-aothait.php, slug='')
        // but resolves to no configured site → cheap 404 instead of the default homepage.
        $request = Request::create('/wp-aothait.php');
        $request->attributes->set('host', 'wp-aothait.php');

        $this->expectException(NotFoundHttpException::class);
        $this->getPageController()->show($request, '');
    }

    public function testCustomHostRouteWithKnownHostIsServed(): void
    {
        // A configured host in the {host} segment must still resolve and serve.
        $request = Request::create('/localhost.dev');
        $request->attributes->set('host', 'localhost.dev');

        $response = $this->getPageController()->show($request, '');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testShowRedirectsFromRedirectFrom(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $destination = new Page();
        $destination->setH1('Redirect Destination');
        $destination->setSlug('redirect-from-destination');
        $destination->locale = 'en';
        $destination->host = 'localhost.dev';
        $destination->createdAt = new DateTime();
        $destination->updatedAt = new DateTime();
        $destination->setMainContent('Destination content');
        $destination->setRedirectFrom(['old-incoming' => 301]);

        $em->persist($destination);
        $em->flush();

        try {
            $response = $this->getPageController()->show(Request::create('/old-incoming'), 'old-incoming');
            self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode());
            self::assertStringContainsString('redirect-from-destination', (string) $response->headers->get('location'));
        } finally {
            $em->remove($destination);
            $em->flush();
        }
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

    /**
     * A per-page feed passes no `pages`, so the template falls back to the raw
     * childrenPages — the noindex filter in the template is the only one there,
     * unlike the main feed which is already filtered by getIndexablePagesQuery().
     */
    public function testPerPageFeedDropsNoindexChildrenWhateverTheDirectiveList(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $parent = $this->createFeedPage('feed-parent', 'Feed Parent');
        $em->persist($parent);

        $listed = $this->createFeedPage('feed-listed-child', 'Listed Child');
        $listed->setParentPage($parent);

        $em->persist($listed);

        $hidden = $this->createFeedPage('feed-noindex-child', 'Hidden Child');
        $hidden->setParentPage($parent);
        $hidden->setMetaRobots('noindex, noarchive');

        $em->persist($hidden);

        $em->flush();
        // childrenPages is EXTRA_LAZY and inverse: setParentPage() never touched the
        // collection the identity map still holds, and it would read as empty.
        $em->clear();

        try {
            $content = (string) $this->getFeedController()
                ->show(Request::create('/feed-parent.xml'), 'feed-parent')
                ->getContent();

            self::assertStringContainsString('Listed Child', $content);
            self::assertStringNotContainsString('Hidden Child', $content);
        } finally {
            $repository = $em->getRepository(Page::class);
            foreach (['feed-noindex-child', 'feed-listed-child', 'feed-parent'] as $slug) {
                $page = $repository->findOneBy(['slug' => $slug]);
                if ($page instanceof Page) {
                    $em->remove($page);
                }
            }

            $em->flush();
        }
    }

    private function createFeedPage(string $slug, string $h1): Page
    {
        $page = new Page();
        $page->setH1($h1);
        $page->setSlug($slug);
        $page->locale = 'en';
        $page->host = 'localhost.dev';
        $page->createdAt = new DateTime();
        $page->updatedAt = new DateTime();
        $page->setMainContent('content');

        return $page;
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

    public function testCustomCanonicalOverridesCanonical(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $page = new Page();
        $page->setH1('Custom canonical page');
        $page->setSlug('custom-canonical-test');
        $page->locale = 'en';
        $page->host = 'localhost.dev';
        $page->createdAt = new DateTime();
        $page->updatedAt = new DateTime();
        $page->setMainContent('Content here');
        $page->setCustomCanonical('https://example.tld/master-page');

        $em->persist($page);
        $em->flush();

        try {
            $content = (string) $this->getPageController()
                ->show(Request::create('/custom-canonical-test'), 'custom-canonical-test')
                ->getContent();

            self::assertStringContainsString(
                '<link rel="canonical" href="https://example.tld/master-page">',
                $content,
            );
            self::assertStringNotContainsString(
                'rel="canonical" href="https://localhost.dev/custom-canonical-test"',
                $content,
            );
        } finally {
            $em->remove($page);
            $em->flush();
        }
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
