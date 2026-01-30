<?php

namespace Pushword\Core\Tests\Component;

use DateTime;
use Pushword\Core\Component\EntityFilter\Filter\HtmlObfuscateLink;
use Pushword\Core\Component\EntityFilter\ManagerPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\ContentExtension;

use function Safe\file_get_contents;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;

class EntityFilterTest extends KernelTestCase
{
    public function testIt(): void
    {
        $page = $this->getPage();
        $manager = $this->getManagerPool()->getManager($page);

        self::assertSame($page->getH1(), $manager->title()); // @phpstan-ignore-line
        self::assertSame($page->getH1(), $manager->getTitle()); // @phpstan-ignore-line

        $splitContent = $this->getContentExtension()->mainContentSplit($page);
        self::assertSame('', $splitContent->getChapeau());
        self::assertSame('<p>', substr(trim($splitContent->getBody()), 0, 3));
    }

    public function testObfuscateLink(): void
    {
        $apps = self::getContainer()->get(SiteRegistry::class);
        $twig = self::getContainer()->get('twig');
        $router = self::getContainer()->get(PushwordRouteGenerator::class);
        $security = self::getContainer()->get(Security::class);
        $linkProvider = new LinkProvider($router, $apps, $twig, $security);

        $filter = new HtmlObfuscateLink($linkProvider);

        self::assertSame('Lorem <span data-rot="_cvrqjro.pbz/">Test</span> ipsum', $filter->convertHtmlRelObfuscateLink('Lorem <a href="https://piedweb.com/" rel="obfuscate">Test</a> ipsum'));
        self::assertSame('Lorem <span class="link-btn" data-rot="_cvrqjro.pbz/">Test</span> ipsum', $filter->convertHtmlRelObfuscateLink('Lorem <a class="link-btn" href="https://piedweb.com/" rel="obfuscate">Test</a> ipsum'));
        self::assertSame('Lorem <span class="link-btn btn-plus" data-rot="_cvrqjro.pbz/">Test</span> ipsum', $filter->convertHtmlRelObfuscateLink('Lorem <a class="link-btn btn-plus" href="https://piedweb.com/" rel="obfuscate">Test</a> ipsum'));
        self::assertSame('Lorem <span class="link-btn btn-plus" data-rot="&">Test</span> ipsum', $filter->convertHtmlRelObfuscateLink('Lorem <a class="link-btn btn-plus" href="&" rel="obfuscate">Test</a> ipsum'));

        self::assertStringNotContainsString(';', $filter->convertHtmlRelObfuscateLink('Lorem <a class="link-btn btn-plus" href="https://example.tld/?test1=abvc&test=2158" rel="obfuscate">Test</a> ipsum'));

        self::assertSame('Lorem <a href="/a1" class="ninja">Test</a> <span data-rot="_cvrqjro.pbz/">Anchor 2</span>', $filter->convertHtmlRelObfuscateLink('Lorem <a href="/a1" class="ninja">Test</a> <a href="https://piedweb.com/" rel="obfuscate">Anchor 2</a>'));
    }

    private function getManagerPool(): ManagerPool
    {
        self::bootKernel();

        return self::getContainer()->get(ManagerPool::class);
    }

    private function getContentExtension(): ContentExtension
    {
        self::bootKernel();

        return self::getContainer()->get(ContentExtension::class);
    }

    public function testToc(): void
    {
        $page = $this->getPage($this->getContentReadyForToc());

        $splitContent = $this->getContentExtension()->mainContentSplit($page);

        self::assertSame('<p>my intro...</p>', trim($splitContent->getIntro()));
        $toCheck = '<h2 id="first-title">First Title</h2>';
        self::assertSame($toCheck, substr(trim($splitContent->getContent()), 0, \strlen($toCheck)));
    }

    private function getPage(?string $content = null): Page
    {
        $page = new Page();
        $page->setH1('Demo Page - Kitchen Sink  Markdown + Twig');
        $page->setSlug('kitchen-sink');
        $page->locale = 'en';
        $page->createdAt = new DateTime('1 day ago');
        $page->updatedAt = new DateTime('1 day ago');
        $page->setMainContent($content ?? file_get_contents(__DIR__.'/../../../skeleton/src/DataFixtures/WelcomePage.md'));
        $page->setCustomProperty('toc', true);

        return $page;
    }

    private function getContentReadyForToc(): string
    {
        return 'my intro...'
            .\chr(10).'## First Title'
            .\chr(10).'first paragraph'
            .\chr(10).'## Second Title'
            .\chr(10).'second paragraph';
    }
}
