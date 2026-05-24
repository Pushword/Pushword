<?php

namespace Pushword\Snippet\Tests;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Snippet\Editor\SnippetEditorToolProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class SnippetEditorToolProviderTest extends KernelTestCase
{
    public function testProvidesSnippetToolForTheBlockEditor(): void
    {
        self::bootKernel();
        $tools = self::getContainer()->get(SnippetEditorToolProvider::class)->getToolsConfig('localhost.dev');

        self::assertArrayHasKey('snippet', $tools);
        self::assertSame('Snippet', $tools['snippet']['className']);
        self::assertArrayHasKey('cta', $tools['snippet']['config']['definitions']);
    }

    public function testSnippetToolDoesNotExposeAnchorOrClassTunes(): void
    {
        // The snippet() Twig function renders to a raw HTML block before Markdown
        // runs, so a `{#id .class}` attribute line in front of it is dropped.
        // Exposing those tunes would be a control that silently does nothing.
        self::bootKernel();
        $tools = self::getContainer()->get(SnippetEditorToolProvider::class)->getToolsConfig('localhost.dev');

        self::assertArrayNotHasKey('tunes', $tools['snippet']);
    }
}
