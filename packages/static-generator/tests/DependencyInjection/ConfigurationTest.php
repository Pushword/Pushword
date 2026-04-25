<?php

namespace Pushword\StaticGenerator\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\DependencyInjection\Configuration;
use Pushword\StaticGenerator\Generator\CaddyfileGenerator;
use Pushword\StaticGenerator\Generator\HtaccessGenerator;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\Generator\RobotsGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

#[Group('integration')]
final class ConfigurationTest extends KernelTestCase
{
    public function testConf(): void
    {
        self::bootKernel();

        $apps = self::getContainer()->get(SiteRegistry::class);

        self::assertSame(Configuration::DEFAULT_ASSETS, $apps->get()->get('static_assets'));
        // BC: static_copy still works
        self::assertSame(Configuration::DEFAULT_ASSETS, $apps->get()->get('static_copy'));
    }

    public function testCacheDefaults(): void
    {
        $config = new Configuration();
        $tree = $config->getConfigTreeBuilder()->buildTree();
        /** @var array{static_html_max_age: int, static_html_stale_while_revalidate: int} $finalized */
        $finalized = $tree->finalize($tree->normalize([]));

        self::assertSame(10800, $finalized['static_html_max_age']);
        self::assertSame(3600, $finalized['static_html_stale_while_revalidate']);
    }

    public function testCacheCanBeOverridden(): void
    {
        $config = new Configuration();
        $tree = $config->getConfigTreeBuilder()->buildTree();
        /** @var array{static_html_max_age: int, static_html_stale_while_revalidate: int} $finalized */
        $finalized = $tree->finalize($tree->normalize([
            'static_html_max_age' => 86400,
            'static_html_stale_while_revalidate' => 0,
        ]));

        self::assertSame(86400, $finalized['static_html_max_age']);
        self::assertSame(0, $finalized['static_html_stale_while_revalidate']);
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

    public function testCacheModeDefaultsToNone(): void
    {
        $config = new Configuration();
        $tree = $config->getConfigTreeBuilder()->buildTree();
        /** @var array{cache: string} $finalized */
        $finalized = $tree->finalize($tree->normalize([]));

        self::assertSame('none', $finalized['cache']);
    }

    public function testCacheModeAcceptsStatic(): void
    {
        $config = new Configuration();
        $tree = $config->getConfigTreeBuilder()->buildTree();
        /** @var array{cache: string} $finalized */
        $finalized = $tree->finalize($tree->normalize(['cache' => 'static']));

        self::assertSame('static', $finalized['cache']);
    }

    public function testCacheModeRejectsInvalidValue(): void
    {
        $config = new Configuration();
        $tree = $config->getConfigTreeBuilder()->buildTree();

        $this->expectException(InvalidConfigurationException::class);
        $normalized = $tree->normalize(['cache' => 'invalid']);
        $tree->finalize($normalized);
    }

    public function testDefaultGeneratorCacheContainsOnlyPageRelatedGenerators(): void
    {
        self::assertContains(PagesGenerator::class, Configuration::DEFAULT_GENERATOR_CACHE);
        self::assertNotContains(HtaccessGenerator::class, Configuration::DEFAULT_GENERATOR_CACHE);
        self::assertNotContains(CaddyfileGenerator::class, Configuration::DEFAULT_GENERATOR_CACHE);
        self::assertNotContains(RobotsGenerator::class, Configuration::DEFAULT_GENERATOR_CACHE);
    }
}
