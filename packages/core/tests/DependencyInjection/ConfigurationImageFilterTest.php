<?php

namespace Pushword\Core\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pushword\Core\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationImageFilterTest extends TestCase
{
    public function testDefaultImageFilterSetsHasNoThumb(): void
    {
        $config = $this->process([]);

        /** @var array<string, mixed> $filterSets */
        $filterSets = $config['image_filter_sets'];
        self::assertArrayNotHasKey('thumb', $filterSets);
        self::assertArrayHasKey('xs', $filterSets);
        self::assertArrayHasKey('md', $filterSets);
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

        /** @var array{thumb: array{filters: array{coverDown: list<int>}}, xs: array{quality: int}} $filterSets */
        $filterSets = $config['image_filter_sets'];
        self::assertSame(660, $filterSets['thumb']['filters']['coverDown'][0]);
        self::assertSame(85, $filterSets['xs']['quality']);
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     *
     * @return array<string, mixed>
     */
    private function process(array $configs): array
    {
        $processor = new Processor();

        /** @var array<string, mixed> */
        return $processor->processConfiguration(new Configuration(), $configs);
    }
}
