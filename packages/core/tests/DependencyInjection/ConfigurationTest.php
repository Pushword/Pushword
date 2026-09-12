<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pushword\Core\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testConf(): void
    {
        $config = $this->process([]);

        self::assertSame('%kernel.default_locale%', $config['locale']);
        self::assertTrue($config['media_cache_is_local']);
        self::assertSame('', $config['media_cache_public_url']);
        self::assertNull($config['native_content_analyzer']);
        self::assertSame(5.0, $config['native_content_analyzer_timeout']);
    }

    public function testNativeContentAnalyzerOptions(): void
    {
        $config = new Processor()->processConfiguration(new Configuration(), [['native_content_analyzer' => '/opt/pushword/content', 'native_content_analyzer_timeout' => 2.5]]);
        self::assertSame('/opt/pushword/content', $config['native_content_analyzer']);
        self::assertSame(2.5, $config['native_content_analyzer_timeout']);
    }

    /**
     * @param array<int, array<string, bool>> $configs
     */
    protected function process(array $configs): array // @phpstan-ignore-line
    {
        $processor = new Processor();

        return $processor->processConfiguration(new Configuration(), $configs);
    }
}
