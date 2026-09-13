<?php

declare(strict_types=1);

namespace Pushword\PageScanner\Scanner;

use Override;
use Pushword\Core\Entity\Page;

/**
 * Report rendered images carrying no alternative text.
 *
 * An empty `alt=""` is the valid way to mark an image as decorative, so it is not
 * reported on its own — only together with the `role`/`aria-hidden` an assistive
 * technology actually reads. Without one of those, an empty alt is indistinguishable
 * from a media whose alt was never filled in, which is what this looks for.
 */
final class MissingAltScanner extends AbstractScanner
{
    private const string IMG_PATTERN = '/<img\s[^>]*>/i';

    private const string ALT_PATTERN = '/\salt\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i';

    private const string DECORATIVE_PATTERN = '/\s(?:role\s*=\s*["\']?presentation|aria-hidden\s*=\s*["\']?true)/i';

    private const string SRC_PATTERN = '/\ssrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i';

    /** @var list<string>|null */
    private ?array $nativeMissingAlt = null;

    #[Override]
    public function scan(Page $page, string $pageHtml, ?RenderedPageFacts $facts = null): array
    {
        $this->nativeMissingAlt = $facts?->missingAlt;

        try {
            return parent::scan($page, $pageHtml);
        } finally {
            $this->nativeMissingAlt = null;
        }
    }

    protected function run(): void
    {
        if ('' === $this->pageHtml) {
            return;
        }

        if (null !== $this->nativeMissingAlt) {
            foreach ($this->nativeMissingAlt as $label) {
                $this->report($label);
            }

            return;
        }

        if (false === preg_match_all(self::IMG_PATTERN, $this->pageHtml, $matches)) {
            return;
        }

        $reported = [];
        foreach ($matches[0] as $img) {
            if (1 === preg_match(self::DECORATIVE_PATTERN, $img)) {
                continue;
            }

            if ('' !== $this->attribute($img, self::ALT_PATTERN)) {
                continue;
            }

            $src = $this->attribute($img, self::SRC_PATTERN);
            if (isset($reported[$src])) {
                continue;
            }

            $reported[$src] = true;
            // With no src there is nothing to name the image by, so quote the tag.
            $label = '' !== $src ? $src : $img;
            $this->report($label);
        }
    }

    private function report(string $label): void
    {
        $this->addError(ScanErrorCode::ImageAltMissing, '<code>'.htmlspecialchars($label).'</code> '.$this->trans('page_scanMissingAlt'));
    }

    /**
     * The attribute value, trimmed; an empty string when it is absent or blank.
     *
     * Only one of the three alternatives in the pattern can match, so concatenating
     * them yields that one — whichever quoting style the markup used.
     */
    private function attribute(string $img, string $pattern): string
    {
        if (1 !== preg_match($pattern, $img, $match)) {
            return '';
        }

        return trim(($match[1] ?? '').($match[2] ?? '').($match[3] ?? ''));
    }
}
