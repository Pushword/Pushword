<?php

namespace Pushword\Core\Tests\Service\Markdown\Extension\Renderer;

use InvalidArgumentException;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\Markdown\Extension\Renderer\FencedCodeRenderer;

final class FencedCodeRendererTest extends TestCase
{
    private function makeNode(string $literal, string $info = ''): FencedCode
    {
        $node = new FencedCode(3, '`', 0);
        $node->setLiteral($literal);
        if ('' !== $info) {
            $node->setInfo($info);
        }

        return $node;
    }

    private function childRenderer(): ChildNodeRendererInterface
    {
        return $this->createMock(ChildNodeRendererInterface::class);
    }

    public function testPreClassIsApplied(): void
    {
        $renderer = new FencedCodeRenderer('microlight');
        $html = (string) $renderer->render($this->makeNode('code', 'php'), $this->childRenderer());

        self::assertStringContainsString('<pre class="microlight">', $html);
    }

    public function testLanguagePrefixAddedToInfoWord(): void
    {
        $renderer = new FencedCodeRenderer('x');
        $html = (string) $renderer->render($this->makeNode('', 'js'), $this->childRenderer());

        self::assertStringContainsString('class="language-js"', $html);
        self::assertStringNotContainsString('language-language-', $html);
    }

    public function testExistingLanguagePrefixNotDoubled(): void
    {
        $renderer = new FencedCodeRenderer('x');
        $html = (string) $renderer->render($this->makeNode('', 'language-php'), $this->childRenderer());

        self::assertStringContainsString('class="language-php"', $html);
        self::assertStringNotContainsString('language-language-', $html);
    }

    public function testNoInfoWordProducesCodeWithNoClass(): void
    {
        $renderer = new FencedCodeRenderer('hl');
        $html = (string) $renderer->render($this->makeNode('x'), $this->childRenderer());

        self::assertStringContainsString('<code>', $html);
        self::assertStringNotContainsString('language-', $html);
    }

    public function testLiteralContentIsEscaped(): void
    {
        $renderer = new FencedCodeRenderer('hl');
        $html = (string) $renderer->render($this->makeNode('<b>&foo</b>'), $this->childRenderer());

        self::assertStringContainsString('&lt;b&gt;', $html);
        self::assertStringNotContainsString('<b>', $html);
    }

    public function testThrowsOnWrongNodeType(): void
    {
        $renderer = new FencedCodeRenderer('hl');
        $wrong = $this->createMock(Node::class);

        $this->expectException(InvalidArgumentException::class);
        $renderer->render($wrong, $this->childRenderer());
    }
}
