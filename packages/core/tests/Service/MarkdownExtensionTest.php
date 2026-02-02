<?php

namespace Pushword\Core\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\Markdown\Extension\Node\ObfuscatedLink;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;

#[Group('integration')]
class MarkdownExtensionTest extends KernelTestCase
{
    private ?MarkdownParser $markdownParser = null;

    private function getMarkdownParser(): MarkdownParser
    {
        if (null !== $this->markdownParser) {
            return $this->markdownParser;
        }

        $linkProvider = new LinkProvider(
            self::getContainer()->get(PushwordRouteGenerator::class),
            self::getContainer()->get(SiteRegistry::class),
            self::getContainer()->get('twig'),
            self::getContainer()->get(Security::class)
        );

        $this->markdownParser = new MarkdownParser(
            $linkProvider,
            self::getContainer()->get('twig'),
            self::getContainer()->get(SiteRegistry::class)
        );

        return $this->markdownParser;
    }

    // ===== Tests des liens obfusqués #[text](url) =====

    public function testObfuscatedLinkNode(): void
    {
        $node = new ObfuscatedLink('https://example.com', 'Example', 'Title');

        self::assertSame('https://example.com', $node->getUrl());
        self::assertSame('Title', $node->getTitle());

        $node->setAttributeClass('btn-primary');
        self::assertSame('btn-primary', $node->getAttributeClass());

        $node->setAttributeId('my-link');
        self::assertSame('my-link', $node->getAttributeId());
    }

    public function testObfuscatedLink(): void
    {
        $parser = $this->getMarkdownParser();
        $result = $parser->transform('Visit #[my site](https://piedweb.com)');

        self::assertStringContainsString('data-rot', $result);
        self::assertStringContainsString('my site', $result);
        self::assertStringNotContainsString('https://piedweb.com', $result);
    }

    public function testObfuscatedLinkWithClass(): void
    {
        $parser = $this->getMarkdownParser();
        $result = $parser->transform('Click #[here](https://example.com){.btn-primary}');

        self::assertStringContainsString('data-rot', $result);
        self::assertStringContainsString('btn-primary', $result);
    }

    public function testObfuscatedLinkWithId(): void
    {
        $parser = $this->getMarkdownParser();
        $result = $parser->transform('See #[docs](https://example.com){#main-link}');

        self::assertStringContainsString('data-rot', $result);
        self::assertStringContainsString('id="main-link"', $result);
    }

    public function testObfuscatedLinkWithTarget(): void
    {
        $parser = $this->getMarkdownParser();
        $result = $parser->transform('Visit #[my site](https://piedweb.com){target="_blank"}');

        self::assertStringContainsString('target="_blank"', $result);
        self::assertStringContainsString('data-rot="_cvrqjro.pbz"', $result);
        self::assertStringContainsString('my site</span>', $result);
    }

    // ===== Tests du rendu personnalisé des images =====

    public function testCustomImageRenderer(): void
    {
        $parser = $this->getMarkdownParser();
        $result = $parser->transform('![Alt text](/media/2.jpg)');

        // Vérifie que c'est bien le rendu personnalisé avec picture
        self::assertStringContainsString('<picture', $result);
        // The image is optimized to webp format in browser paths
        self::assertStringContainsString('/media/', $result);
        self::assertMatchesRegularExpression('/2\.(jpg|webp)/', $result);
        self::assertStringContainsString('Alt text', $result);
    }

    // ===== Tests de l'autolink email =====

    public function testEmailAutolink(): void
    {
        $toTest = [
            'Contact contact@example.com for info.',
            'contact@example.com for info.',
            'for info contact@example.com.',
            'for info contact@example.com',
        ];
        foreach ($toTest as $text) {
            $parser = $this->getMarkdownParser();
            $result = $parser->transform($text);
            self::assertStringContainsString('<span', $result);
            self::assertStringNotContainsString('contact@example.com', $result);
        }
    }

    public function testEmailAutolinkPreviouslyParsed(): void
    {
        $parser = $this->getMarkdownParser();

        $text = 'for info {{ mail("contact@example.com") }}';
        $result = trim($parser->transform($text));
        self::assertStringStartsWith('<p>for info {{ mail(&quot;contact@example.com&quot;) }}', $result);

        $text = self::getContainer()->get('twig')->render(self::getContainer()->get('twig')->createTemplate('Contact {{ mail("contact@example.com") }}.'));
        self::assertStringEndsWith('example.com</span> <span class="cea hidden">pbagnpg@rknzcyr.pbz</span>.', $text);

        $parser = $this->getMarkdownParser();
        $result = trim($parser->transform($text));
        self::assertStringEndsWith('example.com</span> <span class="cea hidden">pbagnpg@rknzcyr.pbz</span>.</p>', $result);
    }

    // ===== Tests de l'autolink téléphone =====

    public function testPhoneAutolink(): void
    {
        $parser = $this->getMarkdownParser();
        $result = $parser->transform('Call 01 23 45 67 89 today.');

        self::assertStringContainsString('data-rot', $result);
    }

    // ===== Tests des shortcodes date =====

    public function testDateShortcodes(): void
    {
        $parser = $this->getMarkdownParser();

        $result = $parser->transform('Copyright date(Y)');
        self::assertStringContainsString(date('Y'), $result);
        self::assertStringNotContainsString('date(Y)', $result);

        $result = $parser->transform('Season date(S) / date(W)');
        self::assertStringNotContainsString('date(S)', $result);
        self::assertStringNotContainsString('date(W)', $result);
    }

    // ===== Tests de non-conversion dans le code =====

    public function testNotConvertedInInlineCode(): void
    {
        $parser = $this->getMarkdownParser();

        $result = $parser->transform('Use `contact@example.com`, `01 23 45 67 89` and `date(Y)` in code.');

        self::assertStringContainsString('contact@example.com', $result);
        self::assertStringContainsString('01 23 45 67 89', $result);
        self::assertStringContainsString('date(Y)', $result);
        self::assertStringNotContainsString('<code><span', $result);
    }

    public function testNotConvertedInCodeBlock(): void
    {
        $parser = $this->getMarkdownParser();

        $markdown = <<<'MD'
```
contact@example.com
01 23 45 67 89
date(Y)
```

Outside: contact@example.com, 01 23 45 67 89, date(Y)
MD;

        $result = $parser->transform($markdown);

        // Dans le bloc de code : pas de conversion
        self::assertStringContainsString('<pre><code>contact@example.com', $result);
        self::assertStringContainsString('date(Y)', $result);

        // Hors du bloc : conversions (email et phone obfusqués, date remplacée)
        $emailObfuscated = substr_count($result, '<span class=nojs>contact');
        self::assertSame(1, $emailObfuscated, 'Seul l\'email hors du code block doit être obfusqué');

        $phoneObfuscated = substr_count($result, 'data-rot');
        self::assertSame(1, $phoneObfuscated, 'Seul le téléphone hors du code block doit être obfusqué');

        // La date devrait être remplacée hors du code (2025) mais pas dans le code (date(Y))
        self::assertStringContainsString(date('Y'), $result);
    }
}
