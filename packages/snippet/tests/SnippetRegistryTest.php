<?php

namespace Pushword\Snippet\Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use Pushword\Snippet\Registry\SnippetRegistry;
use Pushword\Snippet\Tests\Fixtures\DummySnippet;
use Pushword\Snippet\Tests\Fixtures\NoAttributeSnippet;

final class SnippetRegistryTest extends TestCase
{
    public function testItRegistersComponentFromAttribute(): void
    {
        $registry = new SnippetRegistry([new DummySnippet()]);

        self::assertTrue($registry->hasComponent('dummy'));
        self::assertFalse($registry->hasComponent('unknown'));
        self::assertSame('dummy.html.twig', $registry->getTemplate('dummy'));
        self::assertInstanceOf(DummySnippet::class, $registry->getComponent('dummy'));
    }

    public function testItExposesDefinitionsForTheEditor(): void
    {
        $definitions = new SnippetRegistry([new DummySnippet()])->getDefinitions();

        self::assertArrayHasKey('dummy', $definitions);
        self::assertSame('Dummy', $definitions['dummy']['label']);
        self::assertSame(['title' => ['type' => 'string']], $definitions['dummy']['schema']);
    }

    public function testItRejectsComponentWithoutAttribute(): void
    {
        $this->expectException(LogicException::class);

        new SnippetRegistry([new NoAttributeSnippet()]);
    }
}
