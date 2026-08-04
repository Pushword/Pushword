<?php

namespace Pushword\PageScanner\Scanner;

use Pushword\Core\Entity\Page;

/**
 * Report a page whose translations do not each speak a distinct other language.
 *
 * `addTranslation()` only refuses the page itself, so nothing stops a second page in
 * the same locale from joining the group. The result is silent: `getTranslation()`
 * returns whichever it walks into first, and the hreflang block emits two entries for
 * one language, which a crawler reads as a contradiction.
 */
final class TranslationLocaleScanner extends AbstractScanner
{
    protected function run(): void
    {
        $seen = [];

        foreach ($this->page->translations as $translation) {
            $locale = $translation->locale;

            if ($locale === $this->page->locale) {
                $this->addError(ScanErrorCode::TranslationSameLocale, $this->trans('page_scanTranslationSameLocale', [
                    '%locale%' => $locale,
                    '%page%' => $this->describe($translation),
                ]));

                continue;
            }

            if (isset($seen[$locale])) {
                $this->addError(ScanErrorCode::TranslationDuplicateLocale, $this->trans('page_scanTranslationDuplicateLocale', [
                    '%locale%' => $locale,
                    '%page%' => $this->describe($translation),
                    '%other%' => $seen[$locale],
                ]));

                continue;
            }

            $seen[$locale] = $this->describe($translation);
        }
    }

    private function describe(Page $page): string
    {
        // An empty host degrades to a leading slash on its own, which is the right
        // shape for a single-host site.
        return '<code>'.htmlspecialchars($page->host.'/'.$page->slug).'</code>';
    }
}
