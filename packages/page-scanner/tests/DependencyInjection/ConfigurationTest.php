<?php

declare(strict_types=1);

namespace Pushword\PageScanner\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pushword\PageScanner\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

final class ConfigurationTest extends TestCase
{
    public function testNativePageFactsAreOptInAndTimeoutMustBePositive(): void
    {
        $tree = new Configuration()->getConfigTreeBuilder()->buildTree();
        $defaults = $tree->finalize($tree->normalize([]));
        self::assertIsArray($defaults);
        self::assertNull($defaults['native_page_facts']);
        self::assertSame(5.0, $defaults['native_page_facts_timeout']);

        $configured = $tree->finalize($tree->normalize([
            'native_page_facts' => '/opt/pushword/page-facts',
            'native_page_facts_timeout' => 0.5,
        ]));
        self::assertIsArray($configured);
        self::assertSame('/opt/pushword/page-facts', $configured['native_page_facts']);
        self::assertSame(0.5, $configured['native_page_facts_timeout']);

        $this->expectException(InvalidConfigurationException::class);
        $tree->finalize($tree->normalize(['native_page_facts_timeout' => 0]));
    }
}
