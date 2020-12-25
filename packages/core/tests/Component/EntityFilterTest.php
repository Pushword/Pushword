<?php

namespace Pushword\Core\Tests\Component;

use DateTime;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Component\EntityFilter\Filter\HtmlEncryptedLink;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Component\EntityFilter\ManagerPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Service\LinkProvider;

use function Safe\file_get_contents;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EntityFilterTest extends KernelTestCase
{
    public function testIt(): void
    {
        $manager = $this->getManagerPool()->getManager($this->getPage());

        self::assertSame($this->getPage()->getH1(), $manager->title()); // @phpstan-ignore-line
        self::assertSame($this->getPage()->getH1(), $manager->getTitle()); // @phpstan-ignore-line
        self::assertSame('', $manager->getMainContent()->getChapeau());
        self::assertSame('<p>', substr(trim($manager->getMainContent()->getBody()), 0, 3));
    }

    public function testEncryptedLink(): void
    {
        $filter = new HtmlEncryptedLink();
        $filter->app = ($apps = self::getContainer()->get(AppPool::class))->getApp();
        $filter->twig = self::getContainer()->get('twig');
        $router = self::getContainer()->get(PushwordRouteGenerator::class);
        $filter->linkProvider = new LinkProvider($router, $apps, $filter->twig);
        self::assertSame('Lorem <span data-rot=_cvrqjro.pbz/>Test</span> ipsum', $filter->convertHtmlRelEncryptedLink('Lorem <a href="https://piedweb.com/" rel="encrypt">Test</a> ipsum'));
        self::assertSame('Lorem <span class=link-btn data-rot=_cvrqjro.pbz/>Test</span> ipsum', $filter->convertHtmlRelEncryptedLink('Lorem <a class="link-btn" href="https://piedweb.com/" rel="encrypt">Test</a> ipsum'));
        self::assertSame('Lorem <span class="link-btn btn-plus" data-rot=_cvrqjro.pbz/>Test</span> ipsum', $filter->convertHtmlRelEncryptedLink('Lorem <a class="link-btn btn-plus" href="https://piedweb.com/" rel="encrypt">Test</a> ipsum'));
        self::assertSame('Lorem <span class="link-btn btn-plus" data-rot=&>Test</span> ipsum', $filter->convertHtmlRelEncryptedLink('Lorem <a class="link-btn btn-plus" href="&" rel="encrypt">Test</a> ipsum'));

        self::assertSame('Lorem <a href="/a1" class="ninja">Test</a> <span data-rot=_cvrqjro.pbz/>Anchor 2</span>', $filter->convertHtmlRelEncryptedLink('Lorem <a href="/a1" class="ninja">Test</a> <a href="https://piedweb.com/" rel="encrypt">Anchor 2</a>'));
    }

    private function getManagerPool(): ManagerPool
    {
        self::bootKernel();

        return new ManagerPool(
            $apps = self::getContainer()->get(AppPool::class),
            $twig = self::getContainer()->get('twig'),
            self::getContainer()->get('event_dispatcher'),
            /** @var PushwordRouteGenerator */
            $router = self::getContainer()->get(PushwordRouteGenerator::class),
            new LinkProvider($router, $apps, $twig),
            self::getContainer()->get('doctrine.orm.default_entity_manager')
        );
    }

    public function testToc(): void
    {
        $page = $this->getPage($this->getContentReadyForToc());

        /** @var Manager */
        $manager = $this->getManagerPool()->getManager($page);

        self::assertSame('<p>my intro...</p>', trim($manager->getMainContent()->getIntro()));
        $toCheck = '<h2 id="first-title">First Title</h2>';
        self::assertSame($toCheck, substr(trim($manager->getMainContent()->getContent()), 0, \strlen($toCheck)));
    }

    private function getPage(?string $content = null): Page
    {
        $page = (new Page())
            ->setH1('Demo Page - Kitchen Sink  Markdown + Twig')
            ->setSlug('kitchen-sink')
            ->setLocale('en')
            ->setCreatedAt(new DateTime('1 day ago'))
            ->setUpdatedAt(new DateTime('1 day ago'))
            ->setMainContent($content ?? file_get_contents(__DIR__.'/../../../skeleton/src/DataFixtures/WelcomePage.md'));
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
