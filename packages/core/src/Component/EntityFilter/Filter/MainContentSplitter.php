<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Knp\Menu\ItemInterface;
use Pushword\Core\Entity\Page;
use TOC\MarkupFixer;
use TOC\TocGenerator;

class MainContentSplitter extends AbstractFilter
{
    public Page $page;

    private string $chapeau = '';

    private string $intro = '';

    private string $content = '';

    private string $originalContent = '';

    /** @var string[] */
    private array $contentParts = [];

    public function apply(mixed $propertyValue): self
    {
        $this->split($this->string($propertyValue));

        return $this;
    }

    private function split(string $mainContent): void
    {
        $this->content = $mainContent;

        $parsedContent = explode('<!--break-->', $this->content, 2);

        $this->chapeau = isset($parsedContent[1]) ? $parsedContent[0] : '';
        $this->content = $parsedContent[1] ?? $parsedContent[0];

        if (null !== $this->page->getCustomProperty('toc') || null !== $this->page->getCustomProperty('tocTitle')) {
            $this->parseToc();
        }

        $this->splitContentToParts();
    }

    private function splitContentToParts(): void
    {
        $parsedContent = explode('<!--break-->', $this->content);

        if (! isset($parsedContent[1])) {
            return;
        }

        $this->content = $parsedContent[0];
        unset($parsedContent[0]);
        $this->contentParts = array_values($parsedContent);
    }

    private function fixSplit(): void
    {
        if (str_ends_with(trim($this->intro), '<!--break-->')) {
            $this->content = '<!--break-->'.$this->content;
        }
    }

    private function parseToc(): void
    {
        $this->content = (new MarkupFixer())->fix($this->content); // this work only on good html

        // this is a bit crazy
        // Because if there is a wrapper, it will make shit ?!
        $this->originalContent = $this->content;
        $content = explode('<h', $this->content, 2);

        $this->intro = isset($content[1]) ? $content[0] : '';
        $this->content = isset($content[1]) ? '<h'.$content[1] : $content[0];

        $this->fixSplit();
    }

    public function getBody(bool $withChapeau = false): string
    {
        return ($withChapeau ? $this->chapeau : '').$this->intro.$this->content;
    }

    public function getChapeau(): string
    {
        return $this->chapeau;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /** @return string[] */
    public function getContentParts(): array
    {
        return $this->contentParts;
    }

    public function getIntro(): string
    {
        return $this->intro;
    }

    public function getToc(bool $html = true): ItemInterface|string
    {
        $content = str_replace('<!--stop-toc-->', '<!--end-toc-->', $this->originalContent);
        $content = explode('<!--end-toc-->', $content, 2);
        $content = $content[0];

        return $html ? (new TocGenerator())->getHtmlMenu($content, 2)
            : (new TocGenerator())->getMenu($content, 2);
    }
}
