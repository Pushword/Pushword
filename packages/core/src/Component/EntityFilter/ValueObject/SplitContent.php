<?php

namespace Pushword\Core\Component\EntityFilter\ValueObject;

use Knp\Menu\ItemInterface;
use Pushword\Core\Entity\Page;
use Stringable;
use TOC\MarkupFixer;
use TOC\TocGenerator;

final readonly class SplitContent implements Stringable
{
    private string $chapeau;

    private string $intro;

    private string $content;

    private string $originalContent;

    /** @var string[] */
    private array $contentParts;

    public function __construct(string $mainContent, Page $page)
    {
        $content = $mainContent;

        $parsedContent = explode('<!--break-->', $content, 2);

        $this->chapeau = isset($parsedContent[1]) ? $parsedContent[0] : '';
        $content = $parsedContent[1] ?? $parsedContent[0];

        if (null !== $page->getCustomProperty('toc') || null !== $page->getCustomProperty('tocTitle')) {
            [$content, $intro, $originalContent] = $this->parseToc($content);
            $this->intro = $intro;
            $this->originalContent = $originalContent;
        } else {
            $this->intro = '';
            $this->originalContent = '';
        }

        [$this->content, $this->contentParts] = $this->splitContentToParts($content);
    }

    /**
     * @return array{string, string, string}
     */
    private function parseToc(string $content): array
    {
        $content = new MarkupFixer()->fix($content); // this work only on good html

        // this is a bit crazy
        // Because if there is a wrapper, it will make shit ?!
        $originalContent = $content;
        $contentParts = explode('<h', $content, 2);

        $intro = isset($contentParts[1]) ? $contentParts[0] : '';
        $content = isset($contentParts[1]) ? '<h'.$contentParts[1] : $contentParts[0];

        // Fix split
        if (str_ends_with(trim($intro), '<!--break-->')) {
            $content = '<!--break-->'.$content;
        }

        return [$content, $intro, $originalContent];
    }

    /**
     * @return array{string, string[]}
     */
    private function splitContentToParts(string $content): array
    {
        $parsedContent = explode('<!--break-->', $content);

        if (! isset($parsedContent[1])) {
            return [$content, []];
        }

        $mainContent = $parsedContent[0];
        unset($parsedContent[0]);
        $contentParts = array_values($parsedContent);

        return [$mainContent, $contentParts];
    }

    public function getChapeau(): string
    {
        return $this->chapeau;
    }

    public function getIntro(): string
    {
        return $this->intro;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getBody(bool $withChapeau = false): string
    {
        return ($withChapeau ? $this->chapeau : '').$this->intro.$this->content;
    }

    /** @return string[] */
    public function getContentParts(): array
    {
        return $this->contentParts;
    }

    public function getToc(bool $html = true): ItemInterface|string
    {
        if ('' === $this->originalContent) {
            return '';
        }

        $content = str_replace('<!--stop-toc-->', '<!--end-toc-->', $this->originalContent);
        $content = explode('<!--end-toc-->', $content, 2);
        $content = $content[0];

        return $html ? new TocGenerator()->getHtmlMenu($content, 2)
            : new TocGenerator()->getMenu($content, 2);
    }

    public function __toString(): string
    {
        return $this->getBody(true);
    }
}
