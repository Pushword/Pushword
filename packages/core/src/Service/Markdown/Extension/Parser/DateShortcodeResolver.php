<?php

namespace Pushword\Core\Service\Markdown\Extension\Parser;

use IntlDateFormatter;
use Pushword\Core\Site\SiteRegistry;

use function Safe\date as safeDate;

final readonly class DateShortcodeResolver
{
    public function __construct(
        private SiteRegistry $apps,
    ) {
    }

    public function resolve(string $text): string
    {
        return (string) preg_replace_callback(
            '/date\(([^)]+)\)/',
            fn (array $matches): string => $this->convertDateShortcode(trim($matches[1], '\'"%')),
            $text,
        );
    }

    public function convertDateShortcode(string $format): string
    {
        $locale = $this->convertLocale($this->apps->get()->getLocale());
        $intlDateFormatter = new IntlDateFormatter($locale, IntlDateFormatter::FULL, IntlDateFormatter::NONE);

        return match ($format) {
            'S' => $this->getSummerYear(),
            'W' => $this->getWinterYear(),
            'Y-1' => date('Y', strtotime('-1 year')),
            'Y+1' => date('Y', strtotime('next year')),
            'Y' => date('Y'),
            'B', 'M' => $this->formatDate($intlDateFormatter, 'MMMM'),
            'A' => $this->formatDate($intlDateFormatter, 'cccc'),
            'e' => $this->formatDate($intlDateFormatter, 'd'),
            default => $format,
        };
    }

    private function formatDate(IntlDateFormatter $formatter, string $pattern): string
    {
        $formatter->setPattern($pattern);

        return (string) $formatter->format(time());
    }

    private function getWinterYear(): string
    {
        return date('m') < 4 ? safeDate('Y') : safeDate('Y', strtotime('next year'));
    }

    private function getSummerYear(): string
    {
        return date('m') < 10 ? safeDate('Y') : safeDate('Y', strtotime('next year'));
    }

    private function convertLocale(string $locale): string
    {
        return match ($locale) {
            'fr' => 'fr_FR',
            'en' => 'en_UK',
            default => $locale,
        };
    }
}
