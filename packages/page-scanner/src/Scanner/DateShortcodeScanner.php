<?php

declare(strict_types=1);

namespace Pushword\PageScanner\Scanner;

use Override;
use Pushword\Core\Entity\Page;

/**
 * Report date shortcodes still readable in the rendered HTML. The markdown parser
 * and the Date entity filter both resolve them, so one that survives to the page
 * was printed from a field or a template that skips the content pipeline.
 */
final class DateShortcodeScanner extends AbstractScanner
{
    /**
     * The codes Date::convertDateShortCode() resolves — anything else is not a shortcode.
     * `\b` keeps `update(Y)` and friends out: only a standalone `date(` opens one.
     */
    private const string PATTERN = '/\bdate\([\'"]?%?(?:Y[-+]1|[YSWBMAe])[\'"]?\)/i';

    /** @var list<string>|null */
    private ?array $nativeShortcodes = null;

    #[Override]
    public function scan(Page $page, string $pageHtml, ?RenderedPageFacts $facts = null): array
    {
        $this->nativeShortcodes = $facts?->dateShortcodes;

        try {
            return parent::scan($page, $pageHtml);
        } finally {
            $this->nativeShortcodes = null;
        }
    }

    protected function run(): void
    {
        if (null === $this->nativeShortcodes) {
            preg_match_all(self::PATTERN, $this->stripLiteralBlocks(), $matches);
            $shortcodes = array_unique($matches[0]);
        } else {
            $shortcodes = $this->nativeShortcodes;
        }

        foreach ($shortcodes as $shortcode) {
            $this->addError(ScanErrorCode::DateShortcode, '<code>'.$shortcode.'</code> '.$this->trans('page_scanDateShortcode'));
        }
    }

    /**
     * A shortcode written in a code sample documents the syntax, and `Date(y)` inside
     * a script is JavaScript. Neither is content the pipeline forgot to convert.
     */
    private function stripLiteralBlocks(): string
    {
        return preg_replace('#<(code|pre|script|style)\b[^>]*>.*?</\1>#is', '', $this->pageHtml) ?? $this->pageHtml;
    }
}
