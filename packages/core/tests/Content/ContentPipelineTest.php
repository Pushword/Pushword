<?php

namespace Pushword\Core\Tests\Content;

use Exception;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Component\EntityFilter\Filter\FilterInterface;
use Pushword\Core\Component\EntityFilter\FilterRegistry;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Component\EntityFilter\ManagerPool;
use Pushword\Core\Content\ContentPipeline;
use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;

class ContentPipelineTest extends TestCase
{
    /** @param array<string, string> $siteFilters */
    private function createPipeline(
        Page $page,
        ?FilterRegistry $filterRegistry = null,
        array $siteFilters = [],
        bool $entityCanOverrideFilters = false,
    ): ContentPipeline {
        $filterRegistry ??= new FilterRegistry([]);
        $eventDispatcher = new EventDispatcher();

        $params = new ParameterBag(['kernel.project_dir' => sys_get_temp_dir()]);
        $templateResolver = new TemplateResolver(new Twig(new FilesystemLoader()), new ArrayAdapter());

        $siteRegistry = new SiteRegistry(
            ['localhost' => [
                'hosts' => ['localhost'],
                'base_url' => 'https://localhost',
                'name' => 'Test',
                'locale' => 'en',
                'locales' => ['en'],
                'template' => '@Pushword',
                'entity_can_override_filters' => $entityCanOverrideFilters,
            ]],
            $templateResolver,
            $params,
        );

        $siteRegistry->get('localhost')->setFilters($siteFilters);

        $managerPool = new ManagerPool($siteRegistry, $eventDispatcher, $filterRegistry);
        $factory = new ContentPipelineFactory($siteRegistry, $eventDispatcher, $filterRegistry, $managerPool);

        return new ContentPipeline($factory, $eventDispatcher, $filterRegistry, $page, $siteRegistry);
    }

    public function testGetFilteredPropertyReturnsPageValue(): void
    {
        $page = new Page();
        $page->title = 'Hello World';

        $pipeline = $this->createPipeline($page);

        self::assertSame('Hello World', $pipeline->getTitle());
    }

    public function testGetFilteredPropertyCachesResult(): void
    {
        $callCount = 0;
        $filter = new class($callCount) implements FilterInterface {
            public function __construct(private int &$callCount)
            {
            }

            public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
            {
                ++$this->callCount;

                return 'Filtered Name';
            }
        };

        $filterRegistry = new FilterRegistry([$filter]);

        $page = new Page();
        $page->name = 'Cached Name';

        $pipeline = $this->createPipeline($page, $filterRegistry, ['string' => $filter::class]);

        $first = $pipeline->getName();
        $second = $pipeline->getName();

        self::assertSame($first, $second);
        self::assertSame(1, $callCount);
    }

    public function testGetFiltersUsesEntityOverrideWhenAllowed(): void
    {
        $filter = new class implements FilterInterface {
            public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
            {
                return 'entity-filtered';
            }
        };

        $filterRegistry = new FilterRegistry([$filter]);

        $page = new Page();
        $page->name = 'Original';
        $page->setCustomProperty('name_filters', $filter::class);

        $pipeline = $this->createPipeline($page, $filterRegistry, [], true);

        self::assertSame('entity-filtered', $pipeline->getName());
    }

    public function testGetFiltersFallsBackToSiteConfigFilters(): void
    {
        $filter = new class implements FilterInterface {
            public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
            {
                return 'site-filtered';
            }
        };

        $filterRegistry = new FilterRegistry([$filter]);

        $page = new Page();
        $page->name = 'Original';

        $pipeline = $this->createPipeline($page, $filterRegistry, ['name' => $filter::class]);

        self::assertSame('site-filtered', $pipeline->getName());
    }

    public function testGetFiltersHandlesCommaSeparatedString(): void
    {
        $filterA = new class implements FilterInterface {
            public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
            {
                \assert(\is_string($propertyValue));

                return $propertyValue.'-A';
            }
        };
        $filterB = new class implements FilterInterface {
            public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
            {
                \assert(\is_string($propertyValue));

                return $propertyValue.'-B';
            }
        };

        $filterRegistry = new FilterRegistry([$filterA, $filterB]);

        $page = new Page();
        $page->name = 'Start';

        $pipeline = $this->createPipeline($page, $filterRegistry, ['name' => $filterA::class.','.$filterB::class]);

        self::assertSame('Start-A-B', $pipeline->getName());
    }

    public function testStringFallbackFiltersAppliedToStringProperty(): void
    {
        $filter = new class implements FilterInterface {
            public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
            {
                return 'string-filtered';
            }
        };

        $filterRegistry = new FilterRegistry([$filter]);

        $page = new Page();
        $page->name = 'Original';

        // No 'name' filter, but 'string' filter is defined
        $pipeline = $this->createPipeline($page, $filterRegistry, ['string' => $filter::class]);

        self::assertSame('string-filtered', $pipeline->getName());
    }

    public function testApplyFiltersSkipsDisabledFilter(): void
    {
        $filter = new class implements FilterInterface {
            public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
            {
                throw new RuntimeException('Filter should not be called');
            }
        };

        $filterRegistry = new FilterRegistry([$filter]);

        $page = new Page();
        $page->name = 'Original';
        // ContentPipeline.className() uses substr from last '/' then lcfirst
        $className = lcfirst(substr($filter::class, (int) strrpos($filter::class, '/')));
        $page->setCustomProperty('filter_'.$className, 0);

        $pipeline = $this->createPipeline($page, $filterRegistry, ['name' => $filter::class]);

        self::assertSame('Original', $pipeline->getName());
    }

    public function testApplyFiltersThrowsWhenFilterNotFound(): void
    {
        $page = new Page();
        $page->name = 'Original';

        $pipeline = $this->createPipeline($page, null, ['name' => 'NonExistentFilter']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Filter `NonExistentFilter` not found');

        $pipeline->getName();
    }

    public function testApplyFiltersChainedInSequence(): void
    {
        $filterA = new class implements FilterInterface {
            public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
            {
                \assert(\is_string($propertyValue));

                return $propertyValue.'[A]';
            }
        };
        $filterB = new class implements FilterInterface {
            public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
            {
                \assert(\is_string($propertyValue));

                return $propertyValue.'[B]';
            }
        };

        $filterRegistry = new FilterRegistry([$filterA, $filterB]);

        $page = new Page();
        $pipeline = $this->createPipeline($page, $filterRegistry);

        $result = $pipeline->applyFilters('input', [$filterA::class, $filterB::class]);

        self::assertSame('input[A][B]', $result);
    }

    public function testMagicCallDelegatesToGetFilteredProperty(): void
    {
        $page = new Page();
        $page->title = 'Magic Title';

        $pipeline = $this->createPipeline($page);

        // __call converts 'title' -> 'getTitle' -> getFilteredProperty('Title')
        self::assertSame('Magic Title', $pipeline->__call('title'));
    }

    public function testCamelCaseToSnakeCaseConvertsCorrectly(): void
    {
        $filter = new class implements FilterInterface {
            public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
            {
                return 'filtered';
            }
        };

        $filterRegistry = new FilterRegistry([$filter]);

        $page = new Page();
        $page->setMainContent('raw');

        // The filter key for MainContent is 'main_content' (snake_case)
        $pipeline = $this->createPipeline($page, $filterRegistry, ['main_content' => $filter::class]);

        self::assertSame('filtered', $pipeline->getMainContent());
    }

    public function testGetFiltersReturnsEmptyWhenNoFiltersConfigured(): void
    {
        $page = new Page();
        $page->name = 'NoFilters';

        $pipeline = $this->createPipeline($page);

        // No name filters and no string filters -> value returned as-is
        self::assertSame('NoFilters', $pipeline->getName());
    }

    public function testEntityOverrideEmptyStringFallsBackToSiteConfig(): void
    {
        $filter = new class implements FilterInterface {
            public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
            {
                return 'site-applied';
            }
        };

        $filterRegistry = new FilterRegistry([$filter]);

        $page = new Page();
        $page->name = 'Original';
        // Entity override is empty string -> should fall back to site config
        $page->setCustomProperty('name_filters', '');

        $pipeline = $this->createPipeline($page, $filterRegistry, ['name' => $filter::class], true);

        self::assertSame('site-applied', $pipeline->getName());
    }
}
