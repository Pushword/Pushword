<?php

namespace Pushword\Core\Tests\Component\EntityFilter\Filter;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Component\EntityFilter\Filter\Extended;
use Pushword\Core\Component\EntityFilter\FilterRegistry;
use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;

/**
 * The filter walks to another page's pipeline, so it needs the real factory
 * rather than the Manager stub the value-only filters are tested with.
 */
final class ExtendedTest extends TestCase
{
    private function createFactory(Extended $extended): ContentPipelineFactory
    {
        $siteRegistry = new SiteRegistry(
            ['localhost' => [
                'hosts' => ['localhost'],
                'base_url' => 'https://localhost',
                'name' => 'Test',
                'locale' => 'en',
                'locales' => ['en'],
                'template' => '@Pushword',
                'entity_can_override_filters' => false,
            ]],
            new TemplateResolver(new Twig(new FilesystemLoader()), new ArrayAdapter()),
            new ParameterBag(['kernel.project_dir' => sys_get_temp_dir()]),
        );
        $siteRegistry->get('localhost')->filters = ['title' => Extended::class];

        return new ContentPipelineFactory(
            $siteRegistry,
            new EventDispatcher(),
            new FilterRegistry([$extended]),
        );
    }

    private function apply(Page $page, string $propertyValue = ''): mixed
    {
        $extended = new Extended();

        return $extended->apply(
            $propertyValue,
            $page,
            $this->createFactory($extended)->getLegacyManager($page),
            'Title',
        );
    }

    public function testAValueOfItsOwnIsKept(): void
    {
        $page = new Page();
        $page->extendedPage = new Page();
        $page->extendedPage->title = 'Inherited';

        self::assertSame('Own title', $this->apply($page, 'Own title'));
    }

    public function testAnEmptyValueIsInheritedFromTheExtendedPage(): void
    {
        $page = new Page();
        $page->extendedPage = new Page();
        $page->extendedPage->title = 'Inherited';

        self::assertSame('Inherited', $this->apply($page));
    }

    public function testAnEmptyValueStaysEmptyWithoutAnExtendedPage(): void
    {
        self::assertSame('', $this->apply(new Page()));
    }

    public function testAnEmptyExtendedPageResolvesToEmptyRatherThanLooping(): void
    {
        $page = new Page();
        $page->extendedPage = new Page();

        self::assertSame('', $this->apply($page));
    }
}
