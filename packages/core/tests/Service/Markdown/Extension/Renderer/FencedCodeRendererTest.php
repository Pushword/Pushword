<?php

namespace Pushword\Core\Tests\Service\Markdown\Extension\Renderer;

use InvalidArgumentException;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\Markdown\Extension\Renderer\FencedCodeRenderer;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Contracts\Cache\CacheInterface;
use Twig\Environment as Twig;

final class FencedCodeRendererTest extends TestCase
{
    /**
     * The renderer reads the class off the current site at render time, so the
     * unit under test needs a registry holding one app that declares it.
     */
    private function rendererFor(string $preClass): FencedCodeRenderer
    {
        $registry = new SiteRegistry(
            ['x.tld' => ['hosts' => ['x.tld'], 'locale' => 'en', SiteConfig::FENCED_CODE_PRE_CLASS => $preClass]],
            new TemplateResolver(self::createStub(Twig::class), self::createStub(CacheInterface::class)),
            new ParameterBag([]),
        );

        return new FencedCodeRenderer($registry);
    }

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
        return self::createStub(ChildNodeRendererInterface::class);
    }

    public function testPreClassIsApplied(): void
    {
        $renderer = $this->rendererFor('microlight');
        $html = (string) $renderer->render($this->makeNode('code', 'php'), $this->childRenderer());

        self::assertStringContainsString('<pre class="microlight">', $html);
    }

    public function testSiteDeclaringNoPreClassGetsABarePre(): void
    {
        // This renderer is registered for every site, so a site that declares
        // nothing must come out exactly as League's default would render it —
        // a bare <pre>, not <pre class="">.
        $renderer = $this->rendererFor('');
        $html = (string) $renderer->render($this->makeNode('code', 'php'), $this->childRenderer());

        self::assertStringContainsString('<pre>', $html);
        self::assertStringNotContainsString('<pre class', $html);
    }

    public function testLanguagePrefixAddedToInfoWord(): void
    {
        $renderer = $this->rendererFor('x');
        $html = (string) $renderer->render($this->makeNode('', 'js'), $this->childRenderer());

        self::assertStringContainsString('class="language-js"', $html);
        self::assertStringNotContainsString('language-language-', $html);
    }

    public function testExistingLanguagePrefixNotDoubled(): void
    {
        $renderer = $this->rendererFor('x');
        $html = (string) $renderer->render($this->makeNode('', 'language-php'), $this->childRenderer());

        self::assertStringContainsString('class="language-php"', $html);
        self::assertStringNotContainsString('language-language-', $html);
    }

    public function testNoInfoWordProducesCodeWithNoClass(): void
    {
        $renderer = $this->rendererFor('hl');
        $html = (string) $renderer->render($this->makeNode('x'), $this->childRenderer());

        self::assertStringContainsString('<code>', $html);
        self::assertStringNotContainsString('language-', $html);
    }

    public function testLiteralContentIsEscaped(): void
    {
        $renderer = $this->rendererFor('hl');
        $html = (string) $renderer->render($this->makeNode('<b>&foo</b>'), $this->childRenderer());

        self::assertStringContainsString('&lt;b&gt;', $html);
        self::assertStringNotContainsString('<b>', $html);
    }

    public function testThrowsOnWrongNodeType(): void
    {
        $renderer = $this->rendererFor('hl');
        $wrong = self::createStub(Node::class);

        $this->expectException(InvalidArgumentException::class);
        $renderer->render($wrong, $this->childRenderer());
    }
}
