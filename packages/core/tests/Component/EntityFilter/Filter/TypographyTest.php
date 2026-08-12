<?php

namespace Pushword\Core\Tests\Component\EntityFilter\Filter;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Component\EntityFilter\Filter\Typography;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\Typographer;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use ReflectionClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;

final class TypographyTest extends TestCase
{
    private function createFilter(string $siteLocale = 'en'): Typography
    {
        $params = new ParameterBag(['kernel.project_dir' => sys_get_temp_dir()]);
        $templateResolver = new TemplateResolver(new Twig(new FilesystemLoader()), new ArrayAdapter());

        $siteRegistry = new SiteRegistry(
            ['localhost' => [
                'hosts' => ['localhost'],
                'base_url' => 'https://localhost',
                'name' => 'Test',
                'locale' => $siteLocale,
                'locales' => [$siteLocale],
                'template' => '@Pushword',
            ]],
            $templateResolver,
            $params,
        );

        return new Typography(new Typographer(), $siteRegistry);
    }

    private function createManagerStub(): Manager
    {
        return new ReflectionClass(Manager::class)->newInstanceWithoutConstructor();
    }

    public function testUsesPageLocale(): void
    {
        $page = new Page();
        $page->host = 'localhost';
        $page->locale = 'fr';

        $result = $this->createFilter()->apply("<p>l'ami : oui</p>", $page, $this->createManagerStub());

        self::assertSame("<p>l’ami\u{A0}: oui</p>", $result);
    }

    public function testFallsBackToSiteLocale(): void
    {
        $page = new Page();
        $page->host = 'localhost';

        $result = $this->createFilter('fr')->apply('ok !', $page, $this->createManagerStub());

        self::assertSame("ok\u{202F}!", $result);
    }

    public function testNonStringAndEmptyPassthrough(): void
    {
        $filter = $this->createFilter();
        $page = new Page();
        $page->host = 'localhost';

        self::assertSame('', $filter->apply('', $page, $this->createManagerStub()));
        self::assertSame(3, $filter->apply(3, $page, $this->createManagerStub()));
        self::assertNull($filter->apply(null, $page, $this->createManagerStub()));
    }
}
