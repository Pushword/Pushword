<?php

namespace Pushword\Core\Tests\Component\EntityFilter\Filter;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Pushword\Core\Component\EntityFilter\Filter\Twig as TwigFilter;
use Pushword\Core\Entity\Page;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TemplateWrapper;

/**
 * The filter must not pay a createTemplate() round-trip for strings that contain
 * no Twig token — markdown attribute syntax (`{id=…}`, `{.class}`) hits it on
 * every block of every page.
 */
final class TwigGateTest extends TestCase
{
    /** @var Environment&object{createTemplateCalls: int} */
    private Environment $twig;

    private function render(string $content): string
    {
        $this->twig = new class(new ArrayLoader()) extends Environment {
            public int $createTemplateCalls = 0;

            public function createTemplate(string $template, ?string $name = null): TemplateWrapper
            {
                ++$this->createTemplateCalls;

                return parent::createTemplate($template, $name);
            }
        };

        $filter = new class($this->twig, new NullLogger()) extends TwigFilter {
            public function renderPublic(string $string, Page $page): string
            {
                return $this->render($string, $page);
            }
        };

        $page = new Page();
        $page->host = 'localhost';

        return $filter->renderPublic($content, $page);
    }

    public function testMarkdownAttributeSyntaxSkipsTwigEntirely(): void
    {
        self::assertSame('{id=anchor} ## Title {.lead}', $this->render('{id=anchor} ## Title {.lead}'));
        self::assertSame(0, $this->twig->createTemplateCalls);
    }

    public function testTwigTokensStillRender(): void
    {
        self::assertSame('2', $this->render('{{ 1 + 1 }}'));
        self::assertSame(1, $this->twig->createTemplateCalls);

        self::assertSame('ab', $this->render('a{% if true %}b{% endif %}'));

        // A Twig comment must still be stripped by Twig, not returned verbatim.
        self::assertSame('', $this->render('{# editor note #}'));
    }
}
