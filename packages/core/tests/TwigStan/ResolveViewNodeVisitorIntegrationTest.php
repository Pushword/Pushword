<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\TwigStan;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Twig\Node\Expression\FunctionExpression;

#[Group('integration')]
final class ResolveViewNodeVisitorIntegrationTest extends KernelTestCase
{
    public function testIsNotRegisteredInTheApplicationsTwigEnvironment(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $twig = $container->get(Environment::class);
        $template = $twig->parse($twig->tokenize($twig->getLoader()->getSourceContext('@Pushword/user/login.html.twig')));

        self::assertInstanceOf(FunctionExpression::class, $template->getNode('parent'));
    }
}
