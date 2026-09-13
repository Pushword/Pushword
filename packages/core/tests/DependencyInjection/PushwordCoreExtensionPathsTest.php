<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pushword\Core\DependencyInjection\PushwordCoreExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

final class PushwordCoreExtensionPathsTest extends TestCase
{
    public function testMonorepoPathsAreAbsolute(): void
    {
        $rootDir = sys_get_temp_dir().'/pushword-paths-'.bin2hex(random_bytes(8));
        $projectDir = $rootDir.'/packages/dev-app';
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.project_dir' => $projectDir,
            'kernel.default_locale' => 'en',
        ]));

        new PushwordCoreExtension()->load([['apps' => [['hosts' => ['example.tld']]]]], $container);

        self::assertSame($rootDir.'/packages', $container->getParameter('pw.package_dir'));
        self::assertSame($rootDir.'/vendor', $container->getParameter('vendor_dir'));
    }
}
