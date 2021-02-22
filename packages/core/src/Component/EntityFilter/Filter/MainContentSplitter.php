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

    private array $parts = ['chapeau', 'intro', 'toc', 'content', 'postContent'];

    private string $chapeau = '';
    private string $intro = '';
    private string $toc = '';
    private string $content = '';
    private string $postContent = '';
    private array $contentPart = [];

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

        $parsedContent = explode('<!--break-->', $this->content);

        $this->chapeau = isset($parsedContent[1]) ? $parsedContent[0] : '';
        $this->content = $parsedContent[1] ?? $parsedContent[0];

        if (isset($parsedContent[1])) {
            unset($parsedContent[0], $parsedContent[1]);
        } else {
            unset($parsedContent[0]);
        }

        $this->contentPart = array_values($parsedContent);

        if (null !== $this->entity->getCustomProperty('toc')) {
            $this->parseToc();
        }
    }

    private function parseToc(): void
    {
        $this->content = (new MarkupFixer())->fix($this->content); // this work only on good html

        // this is a bit crazy
        // Because if there is a wrapper, it will make shit ?!
        $content = $this->content;
        $content = explode('<h', $content, 2);

        $this->intro = isset($content[1]) ? $content[0] : '';
        $this->content = isset($content[1]) ? '<h'.$content[1] : $content[0];
    }

    public function getBody(bool $withChapeau = false): string
    {
        return ($withChapeau ? $this->chapeau : '').$this->intro.$this->content.$this->postContent;
    }

    public function getChapeau(): string
    {
        return $this->chapeau;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getContentPart($key): string
    {
        return $this->contentPart[$key - 1];
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
        return $html ? (new TocGenerator())->getHtmlMenu($this->content, 2)
            : (new TocGenerator())->getMenu($this->content, 2);
    }
}
