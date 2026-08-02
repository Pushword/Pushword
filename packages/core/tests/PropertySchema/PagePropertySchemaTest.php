<?php

namespace Pushword\Core\Tests\PropertySchema;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;
use Pushword\Core\PropertySchema\CorePagePropertiesProvider;
use Pushword\Core\PropertySchema\PagePropertySchemaFactory;
use Pushword\Core\PropertySchema\PagePropertySchemaRegistry;
use Pushword\Core\PropertySchema\PagePropertyType;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;

final class PagePropertySchemaTest extends TestCase
{
    public function testFactoryBuildsFullDescriptor(): void
    {
        $schema = PagePropertySchemaFactory::fromConfig('level', [
            'type' => 'string',
            'required' => true,
            'constraints' => [
                ['Choice' => ['choices' => ['Débutant', 'Initié', 'Galop 3']]],
                ['Length' => ['max' => 60]],
                'NotBlank',
            ],
        ]);

        self::assertSame('level', $schema->name);
        self::assertSame(PagePropertyType::String, $schema->type);
        self::assertTrue($schema->required);
        self::assertCount(3, $schema->constraints);
        self::assertInstanceOf(Choice::class, $schema->constraints[0]);
        self::assertSame(['Débutant', 'Initié', 'Galop 3'], $schema->constraints[0]->choices);
        self::assertInstanceOf(Length::class, $schema->constraints[1]);
        self::assertSame(60, $schema->constraints[1]->max);
        self::assertInstanceOf(NotBlank::class, $schema->constraints[2]);
    }

    public function testFactoryDefaults(): void
    {
        $schema = PagePropertySchemaFactory::fromConfig('subtitle', []);

        self::assertSame(PagePropertyType::String, $schema->type);
        self::assertFalse($schema->required);
        self::assertSame([], $schema->constraints);
    }

    public function testFactoryAcceptsConstraintFqcn(): void
    {
        $schema = PagePropertySchemaFactory::fromConfig('x', [
            'constraints' => [[NotBlank::class => null]],
        ]);

        self::assertInstanceOf(NotBlank::class, $schema->constraints[0]);
    }

    public function testFactoryRejectsUnknownDescriptorKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('unknown option(s) `contraints`');
        PagePropertySchemaFactory::fromConfig('x', ['contraints' => []]);
    }

    public function testFactoryRejectsUnknownType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('unknown type `text`');
        PagePropertySchemaFactory::fromConfig('x', ['type' => 'text']);
    }

    public function testFactoryRejectsUnknownConstraintName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('unknown constraint `Choise`');
        PagePropertySchemaFactory::fromConfig('x', ['constraints' => [['Choise' => null]]]);
    }

    public function testFactoryRejectsTypoInConstraintOption(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('invalid options for constraint `Choice`');
        PagePropertySchemaFactory::fromConfig('x', ['constraints' => [['Choice' => ['choicez' => ['a']]]]]);
    }

    public function testFactoryRejectsNonListConstraints(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('`constraints` must be a list');
        PagePropertySchemaFactory::fromConfig('x', ['constraints' => ['Choice' => ['choices' => ['a']]]]);
    }

    public function testFactoryRejectsNonMapDescriptor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('descriptor must be a map');
        PagePropertySchemaFactory::fromConfig('x', 'string');
    }

    public function testTypeAccepts(): void
    {
        self::assertTrue(PagePropertyType::String->accepts('a'));
        self::assertFalse(PagePropertyType::String->accepts(1));
        self::assertTrue(PagePropertyType::Int->accepts(350));
        self::assertFalse(PagePropertyType::Int->accepts('350'));
        self::assertTrue(PagePropertyType::Float->accepts(1.5));
        self::assertTrue(PagePropertyType::Float->accepts(2), 'float accepts an int value');
        self::assertTrue(PagePropertyType::Bool->accepts(true));
        self::assertFalse(PagePropertyType::Bool->accepts(1));
        self::assertTrue(PagePropertyType::Date->accepts(1704153600), 'unquoted YAML date arrives as timestamp');
        self::assertTrue(PagePropertyType::Date->accepts('2024-06-01'));
        self::assertFalse(PagePropertyType::Date->accepts('not a date'));
        self::assertTrue(PagePropertyType::List->accepts(['a']));
        self::assertFalse(PagePropertyType::List->accepts('a'));
    }

    public function testRegistryMergesProvidersAndSiteConfigPerHost(): void
    {
        $registry = $this->createRegistry([
            'one.tld' => [
                'page_properties' => [
                    'price_from' => ['type' => 'int'],
                    // a site overrides a bundle-declared descriptor whole
                    'tocTitle' => ['type' => 'string', 'constraints' => [['Length' => ['max' => 30]]]],
                    // `~` un-declares a bundle-declared property for this host
                    'toc' => null,
                ],
            ],
            'two.tld' => [],
        ]);

        $one = $registry->for('one.tld');
        self::assertSame(PagePropertyType::Int, $one['price_from']->type);
        self::assertCount(1, $one['tocTitle']->constraints);
        self::assertArrayNotHasKey('toc', $one);
        self::assertArrayHasKey('searchExcerpt', $one, 'bundle-declared properties fall through');

        $two = $registry->for('two.tld');
        self::assertArrayNotHasKey('price_from', $two, 'site declarations do not leak across hosts');
        self::assertArrayHasKey('toc', $two);
        self::assertSame(PagePropertyType::Bool, $two['toc']->type);

        self::assertNull($registry->schema('one.tld', 'undeclared'));
        self::assertSame($one['price_from'], $registry->schema('one.tld', 'price_from'));
    }

    public function testComplianceForReportsNearMissesAndMissingRequired(): void
    {
        $registry = $this->createRegistry([
            'one.tld' => [
                'page_properties' => [
                    'author' => ['type' => 'string', 'required' => true],
                ],
            ],
        ]);

        $page = new Page();
        $page->host = 'one.tld';
        $page->setCustomProperty('toc_titel', 'Sommaire'); // near-miss for tocTitle
        $page->setCustomProperty('tocTitle', 'Sommaire'); // declared by the core provider
        $page->setCustomProperty('ogTitle', 'x'); // managed key, never reported

        self::assertSame(
            ['undeclared' => ['toc_titel'], 'missingRequired' => ['author']],
            $registry->complianceFor($page),
        );

        $page->setCustomProperty('author', 'Robin');
        self::assertSame(
            ['undeclared' => ['toc_titel'], 'missingRequired' => []],
            $registry->complianceFor($page),
            'required is satisfied once the key exists',
        );
    }

    /** @param array<string, array<string, mixed>> $appsExtra */
    private function createRegistry(array $appsExtra): PagePropertySchemaRegistry
    {
        $rawApps = [];
        foreach ($appsExtra as $host => $extra) {
            $rawApps[$host] = [
                'hosts' => [$host],
                'base_url' => 'https://'.$host,
                'name' => 'Test',
                'locale' => 'en',
                'locales' => ['en'],
                'template' => '@Pushword',
                'entity_can_override_filters' => false,
                ...$extra,
            ];
        }

        $siteRegistry = new SiteRegistry(
            $rawApps,
            new TemplateResolver(new Twig(new FilesystemLoader()), new ArrayAdapter()),
            new ParameterBag(['kernel.project_dir' => sys_get_temp_dir()]),
        );

        return new PagePropertySchemaRegistry($siteRegistry, [new CorePagePropertiesProvider()]);
    }
}
