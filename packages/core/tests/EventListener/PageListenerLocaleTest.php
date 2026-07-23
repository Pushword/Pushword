<?php

namespace Pushword\Core\Tests\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Entity\Page;
use Pushword\Core\EventListener\PageListener;
use Pushword\Core\Service\PageOpenGraphImageGenerator;
use Pushword\Core\Service\TailwindGenerator;
use Pushword\Core\Service\VariantManager;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;

/**
 * `Page::$locale` defaults to ''. The admin (PageCrudController::initializeNewPage) and
 * the flat importer each filled it themselves, but the API write path only did when the
 * payload happened to carry `locale:` — so pages written by GA-Editor & co landed with ''
 * and every read path patched them back in memory forever.
 *
 * The invariant now lives on prePersist so all write paths agree.
 */
final class PageListenerLocaleTest extends TestCase
{
    public function testAnEmptyLocaleIsFilledFromThePageHost(): void
    {
        $page = $this->buildPage('us.example.com');
        self::assertSame('', $page->locale, 'A new Page starts with no locale');

        $this->buildListener()->prePersist($page);

        self::assertSame('en-US', $page->locale);
    }

    public function testTheLocaleIsResolvedPerHostNotFromTheDefaultSite(): void
    {
        $page = $this->buildPage('www.example.com');

        $this->buildListener()->prePersist($page);

        self::assertSame('fr', $page->locale, 'Each host declares its own locale');
    }

    public function testAnExplicitLocaleIsNeverOverwritten(): void
    {
        $page = $this->buildPage('www.example.com');
        $page->locale = 'it';

        $this->buildListener()->prePersist($page);

        self::assertSame('it', $page->locale);
    }

    private function buildPage(string $host): Page
    {
        $page = new Page();
        $page->setSlug('locale-invariant-test');
        $page->setH1('Test');
        $page->host = $host;

        return $page;
    }

    private function buildListener(): PageListener
    {
        $security = self::createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $generator = self::createStub(PageOpenGraphImageGenerator::class);
        $generator->method('setPage')->willReturnSelf();

        $suppressor = new PageCacheSuppressor();

        return new PageListener(
            $security,
            $generator,
            self::createStub(TailwindGenerator::class),
            $suppressor,
            new VariantManager(self::createStub(EntityManagerInterface::class)),
            $this->buildSiteRegistry(),
        );
    }

    private function buildSiteRegistry(): SiteRegistry
    {
        $site = static fn (string $host, string $locale): array => [
            'hosts' => [$host],
            'base_url' => 'https://'.$host,
            'name' => $host,
            'locale' => $locale,
            'locales' => [$locale],
            'template' => '@Pushword',
            'entity_can_override_filters' => false,
        ];

        return new SiteRegistry(
            [
                'www.example.com' => $site('www.example.com', 'fr'),
                'us.example.com' => $site('us.example.com', 'en-US'),
            ],
            new TemplateResolver(new Twig(new FilesystemLoader()), new ArrayAdapter()),
            new ParameterBag(['kernel.project_dir' => sys_get_temp_dir()]),
        );
    }
}
