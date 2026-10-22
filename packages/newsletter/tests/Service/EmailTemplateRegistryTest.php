<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Service;

use InvalidArgumentException;
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
    }

    public function testAnUnregisteredTemplateCannotBeRenderedAsATwigPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Unknown newsletter email template "newsletter/arbitrary.html.twig".');

        new EmailTemplateRegistry([])->template('newsletter/arbitrary.html.twig');
    }
}
