<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pushword\Core\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationImageFilterTest extends TestCase
{
    public function testDefaultImageFilterSetsHasNoThumb(): void
    {
        $config = $this->process([]);

        self::assertArrayNotHasKey('thumb', $config['image_filter_sets']);
        self::assertArrayHasKey('xs', $config['image_filter_sets']);
        self::assertArrayHasKey('md', $config['image_filter_sets']);
    }

    public function testImageFilterSetsAcceptsArrayOverride(): void
    {
        $config = $this->process([
            [
                'image_filter_sets' => [
                    'thumb' => [
                        'quality' => 90,
                        'filters' => ['coverDown' => [660, 660]],
                        'formats' => ['webp'],
                    ],
                    'xs' => [
                        'quality' => 85,
                        'filters' => ['scaleDown' => [576]],
                        'formats' => ['webp'],
                    ],
                ],
            ],
        ]);

        self::assertSame(660, $config['image_filter_sets']['thumb']['filters']['coverDown'][0]);
        self::assertSame(85, $config['image_filter_sets']['xs']['quality']);
    }

    /** @param array<int, array<string, mixed>> $configs */
    private function process(array $configs): array // @phpstan-ignore-line
    {
        $processor = new Processor();

        return $processor->processConfiguration(new Configuration(), $configs);
    }
}
