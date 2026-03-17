<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\DependencyInjection\Configuration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

#[Group('integration')]
class ConfigurationTest extends KernelTestCase
{
    public function testConf(): void
    {
        self::bootKernel();

        $apps = self::getContainer()->get(SiteRegistry::class);

        self::assertSame(Configuration::DEFAULT_ASSETS, $apps->get()->get('static_assets'));
        // BC: static_copy still works
        self::assertSame(Configuration::DEFAULT_ASSETS, $apps->get()->get('static_copy'));
    }

    public function testStaticSymlinkAcceptsBool(): void
    {
        $config = new Configuration();
        $tree = $config->getConfigTreeBuilder()->buildTree();
        $normalized = $tree->normalize(['static_symlink' => true]);
        /** @var array{static_symlink: mixed} $finalized */
        $finalized = $tree->finalize($normalized);

        self::assertTrue($finalized['static_symlink']);
    }

    public function testStaticSymlinkAcceptsArray(): void
    {
        $config = new Configuration();
        $tree = $config->getConfigTreeBuilder()->buildTree();
        $normalized = $tree->normalize(['static_symlink' => ['media']]);
        /** @var array{static_symlink: mixed} $finalized */
        $finalized = $tree->finalize($normalized);

        self::assertSame(['media'], $finalized['static_symlink']);
    }

    public function testStaticSymlinkRejectsInvalidArray(): void
    {
        $config = new Configuration();
        $tree = $config->getConfigTreeBuilder()->buildTree();

        $this->expectException(InvalidConfigurationException::class);
        $normalized = $tree->normalize(['static_symlink' => ['invalid']]);
        $tree->finalize($normalized);
    }
}
