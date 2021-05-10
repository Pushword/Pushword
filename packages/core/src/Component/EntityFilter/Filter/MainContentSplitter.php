<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Knp\Menu\ItemInterface;
use Pushword\Core\AutowiringTrait\RequiredAppTrait;
use Pushword\Core\AutowiringTrait\RequiredEntityTrait;
use Pushword\Core\AutowiringTrait\RequiredTwigTrait;
use TOC\MarkupFixer;
use TOC\TocGenerator;

class MainContentSplitter extends AbstractFilter
{
    use RequiredAppTrait;
    use RequiredEntityTrait;
    use RequiredTwigTrait;

    // delimiter <!--break-->
    private array $parts = [
        'chapeau', // content before the first *delimiter*
        'intro', // when toc is active, content between the first delimiter (or from the begining) to the first hN
        'toc',  // Table of Content
        'content',
    ];

    private string $chapeau = '';

    private string $intro = '';

    private string $toc = '';

    private string $content = '';

    private string $originalContent = '';

    private array $contentParts = [];

    /**
     * @return self
     */
    public function apply($string)
    {
        $this->split($string);

        return $this;
    }

    private function split($mainContent): void
    {
        $this->content = (string) $mainContent;

        $parsedContent = explode('<!--break-->', $this->content, 2);

        $this->chapeau = isset($parsedContent[1]) ? $parsedContent[0] : '';
        $this->content = $parsedContent[1] ?? $parsedContent[0];

        if (null !== $this->entity->getCustomProperty('toc') || null !== $this->entity->getCustomProperty('tocTitle')) {
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

    public function getContentParts(): array
    {
        return $this->contentParts;
    }

    public function getIntro(): string
    {
        return $this->intro;
    }

    /**
     * @return string|ItemInterface
     */
    public function getToc(bool $html = true)
    {
        return $html ? (new TocGenerator())->getHtmlMenu($this->originalContent, 2)
            : (new TocGenerator())->getMenu($this->originalContent, 2);
    }
}
