<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Service;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\DependencyInjection\PushwordNewsletterExtension;
use Pushword\Newsletter\Service\EmailTemplateRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class EmailTemplateRegistryTest extends TestCase
{
    public function testSitesWithoutPresetsReceiveAnEmptyRegistry(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('pw.apps', []);
        new PushwordNewsletterExtension()->load([], $container);

        self::assertSame([], $container->getDefinition(EmailTemplateRegistry::class)->getArgument('$templates'));
        self::assertSame([], new EmailTemplateRegistry([])->choices());
    }

    public function testChoicesComeOnlyFromConfiguredTwigTemplates(): void
    {
        $registry = new EmailTemplateRegistry(['brand' => ['label' => 'Brand newsletter', 'template' => 'newsletter/brand.html.twig']]);
        self::assertSame(['newsletter.template.siteGroup' => ['Brand newsletter' => 'twig:brand']], $registry->choices());
        self::assertSame('newsletter/brand.html.twig', $registry->template('brand'));
        foreach (['simple', 'framed', 'personal', 'twig:brand'] as $layout) {
            $registry->assertLayout($layout);
        }
    }

    public function testAnUnregisteredTemplateCannotBeRenderedAsATwigPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Unknown newsletter email template "newsletter/arbitrary.html.twig".');
        new EmailTemplateRegistry([])->template('newsletter/arbitrary.html.twig');
    }

    /** @return iterable<string, array{string}> */
    public static function unsupportedLayouts(): iterable
    {
        yield 'saved template' => ['saved:123'];
        yield 'unregistered Twig preset' => ['twig:unknown'];
        yield 'unregistered Twig path' => ['newsletter/arbitrary.html.twig'];
    }

    #[DataProvider('unsupportedLayouts')]
    public function testUnsupportedLayoutsAreRejected(string $layout): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EmailTemplateRegistry([])->assertLayout($layout);
    }
}
