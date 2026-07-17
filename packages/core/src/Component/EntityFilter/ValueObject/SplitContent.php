<?php

namespace Pushword\Core\Component\EntityFilter\ValueObject;

use DOMComment;
use DOMXPath;
use Knp\Menu\ItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\Toc\DomCapturingHtml5;
use Stringable;
use Throwable;
use TOC\MarkupFixer;
use TOC\TocGenerator;

final readonly class SplitContent implements Stringable
{
    /** Comments after which headings are left out of the TOC. */
    private const array TOC_CUTOFF_MARKERS = ['stop-toc', 'end-toc'];

    /** Bump when fixHeadings()'s output changes (MarkupFixer behaviour, heading extraction). */
    private const int TOC_CACHE_VERSION = 1;

    /** Every heading, plus the comments a cutoff marker could hide in, in document order. */
    private const string TOC_NODES_XPATH = '//*[local-name() = "h1" or local-name() = "h2" or local-name() = "h3"'
        .' or local-name() = "h4" or local-name() = "h5" or local-name() = "h6"] | //comment()';

    private string $chapeau;

    private string $intro;

    private string $content;

    private string $originalContent;

    /** Heading tags alone, enough for TocGenerator to build the menu from. */
    private string $tocHeadings;

    /** @var string[] */
    private array $contentParts;

    public function __construct(string $mainContent, Page $page, private ?CacheItemPoolInterface $cache = null)
    {
        $content = $mainContent;

        $parsedContent = explode('<!--break-->', $content, 2);

        $this->chapeau = isset($parsedContent[1]) ? $parsedContent[0] : '';
        $content = $parsedContent[1] ?? $parsedContent[0];

        if (null !== $page->getCustomProperty('toc') || null !== $page->getCustomProperty('tocTitle')) {
            [$content, $intro, $originalContent, $tocHeadings] = $this->parseToc($content);
            $this->intro = $intro;
            $this->originalContent = $originalContent;
            $this->tocHeadings = $tocHeadings;
        } else {
            $this->intro = '';
            $this->originalContent = '';
            $this->tocHeadings = '';
        }

        [$this->content, $this->contentParts] = $this->splitContentToParts($content);
    }

    /**
     * @return array{string, string, string, string}
     */
    private function parseToc(string $content): array
    {
        [$content, $tocHeadings] = $this->fixHeadings($content);

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

        return [$content, $intro, $originalContent, $tocHeadings];
    }

    /**
     * Inject the missing heading ids and serialize the headings the TOC is built
     * from. A pure function of the input string — and by far the most expensive
     * step of a page render (a full HTML5 parse + serialize round-trip) — so the
     * result is cached by content hash. No invalidation needed: a content change
     * changes the key.
     *
     * @return array{string, string} [fixed content, serialized headings]
     */
    private function fixHeadings(string $content): array
    {
        if (null === $this->cache) {
            return $this->doFixHeadings($content);
        }

        try {
            $item = $this->cache->getItem('pw_toc.'.hash('xxh3', self::TOC_CACHE_VERSION.'|'.$content));
            if ($item->isHit()) {
                /** @var array{string, string} */
                return $item->get();
            }

            $result = $this->doFixHeadings($content);
            $item->set($result);
            $this->cache->save($item);

            return $result;
        } catch (Throwable) {
            // A cache backend hiccup must never break rendering.
            return $this->doFixHeadings($content);
        }
    }

    /**
     * @return array{string, string}
     */
    private function doFixHeadings(string $content): array
    {
        $html5 = new DomCapturingHtml5();
        $content = new MarkupFixer($html5)->fix($content); // this work only on good html

        // MarkupFixer just parsed the whole document to inject the heading ids;
        // harvest the headings from that same DOM so getToc() does not have to
        // parse it all over again.
        return [$content, $this->extractTocHeadings($html5)];
    }

    /**
     * Serialize every heading, in document order, up to the first cutoff comment.
     */
    private function extractTocHeadings(DomCapturingHtml5 $html5): string
    {
        if (null === $html5->lastDocument) {
            return '';
        }

        $nodes = new DOMXPath($html5->lastDocument)->query(self::TOC_NODES_XPATH);

        if (false === $nodes) {
            return '';
        }

        $headings = '';
        foreach ($nodes as $node) {
            if ($node instanceof DOMComment) {
                if (\in_array(trim($node->textContent), self::TOC_CUTOFF_MARKERS, true)) {
                    break;
                }

                continue;
            }

            $headings .= $html5->saveHTML($node);
        }

        return $headings;
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

        return $html ? new TocGenerator()->getHtmlMenu($this->tocHeadings, 2)
            : new TocGenerator()->getMenu($this->tocHeadings, 2);
    }

    public function __toString(): string
    {
        return $this->getBody(true);
    }
}
