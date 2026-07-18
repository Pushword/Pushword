<?php

namespace Pushword\Core\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Guards the `markdown_inline` Twig filter: inline nodes are rendered exactly
 * like via `markdown`, but no block tag is ever emitted.
 */
#[Group('integration')]
final class MarkdownInlineTest extends KernelTestCase
{
    private function getMarkdownParser(): MarkdownParser
    {
        self::bootKernel();

        return self::getContainer()->get(MarkdownParser::class);
    }

    public function testAnchorLinkWithoutParagraphWrapper(): void
    {
        self::assertSame(
            'détaillés dans l\'<a href="#intro">article complet</a>.',
            $this->getMarkdownParser()->transformInline('détaillés dans l\'[article complet](#intro).')
        );
    }

    public function testEmphasisAndCode(): void
    {
        self::assertSame(
            '<strong>gras</strong>, <em>italique</em>, <code>code</code> et <del>barré</del>',
            $this->getMarkdownParser()->transformInline('**gras**, *italique*, `code` et ~~barré~~')
        );
    }

    public function testRawInlineHtmlPassesThrough(): void
    {
        self::assertSame(
            'un <span class="x">span</span> brut',
            $this->getMarkdownParser()->transformInline('un <span class="x">span</span> brut')
        );
    }

    public function testBlockSyntaxStaysLiteral(): void
    {
        self::assertSame(
            '# Pas un titre',
            $this->getMarkdownParser()->transformInline('# Pas un titre')
        );
    }

    public function testMultiParagraphInputNeverProducesBlocks(): void
    {
        $result = $this->getMarkdownParser()->transformInline("first\n\nsecond");

        self::assertStringNotContainsString('<p>', $result);
        self::assertSame('first second', preg_replace('/\s+/', ' ', $result));
    }

    public function testLinkAttributes(): void
    {
        $result = $this->getMarkdownParser()->transformInline('[doc](/doc){.btn}');

        self::assertStringContainsString('class="btn"', $result);
        self::assertStringContainsString('href="/doc"', $result);
    }

    public function testInlineOutputMatchesBlockFilterUnwrapped(): void
    {
        $parser = $this->getMarkdownParser();
        $markdown = 'Visit #[my site](https://piedweb.com){.btn} date(Y)';

        $inline = $parser->transformInline($markdown);

        self::assertStringContainsString('data-rot', $inline);
        self::assertStringNotContainsString('https://piedweb.com', $inline);
        self::assertSame('<p>'.$inline.'</p>', trim($parser->transform($markdown)));
    }

    public function testEmailAutolink(): void
    {
        $result = $this->getMarkdownParser()->transformInline('Contact contact@example.com for info.');

        self::assertStringContainsString('<span', $result);
        self::assertStringNotContainsString('contact@example.com', $result);
    }

    public function testFilterIsRegisteredInTwig(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get('twig');

        self::assertSame(
            '<strong>gras</strong>',
            $twig->createTemplate("{{ '**gras**'|markdown_inline }}")->render()
        );
    }
}
