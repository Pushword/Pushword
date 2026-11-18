<?php

declare(strict_types=1);

namespace Pushword\Core\Component\EntityFilter\Filter;

use DateTimeImmutable;
use Exception;
use IntlDateFormatter;
use MessageFormatter;
use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;

use function Safe\preg_replace;

#[AsFilter]
class Date implements FilterInterface
{
    /** @var array<string, IntlDateFormatter> */
    private array $formatterCache = [];

    public function __construct(
        private readonly SiteRegistry $apps,
    ) {
    }

    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        assert(is_scalar($propertyValue));
        $propertyValue = (string) $propertyValue;

        return $this->convertDateShortCode($propertyValue, $this->apps->get($page->host)->locale);
    }

    private function getFormatter(string $locale): IntlDateFormatter
    {
        return $this->formatterCache[$locale] ??= new IntlDateFormatter(
            $locale,
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE
        );
    }

    public function convertDateShortCode(string $string, ?string $locale = null): string
    {
        $locale = null !== $locale ? $this->convertLocale($locale) : 'fr_FR';
        $intlDateFormatter = $this->getFormatter($locale);

        $string = preg_replace('/date\([\'"]?%?S[\'"]?\)/i', $this->getSummerYear(), $string);
        $string = preg_replace('/date\([\'"]?%?W[\'"]?\)/i', $this->getWinterYear(), $string);
        $string = preg_replace('/date\([\'"]?%?Y-1[\'"]?\)/i', date('Y', strtotime('-1 year')), $string);
        $string = preg_replace('/date\([\'"]?%?Y\+1[\'"]?\)/i', date('Y', strtotime('next year')), $string);
        $string = preg_replace('/date\([\'"]?%?Y[\'"]?\)/i', date('Y'), $string);

        $intlDateFormatter->setPattern('MMMM');
        $string = preg_replace('/date\([\'"]?%?(B|M)[\'"]?\)/i', (string) $intlDateFormatter->format(time()), $string);

        $intlDateFormatter->setPattern('cccc');
        $string = preg_replace('/date\([\'"]?%?A[\'"]?\)/i', (string) $intlDateFormatter->format(time()), $string);

        $intlDateFormatter->setPattern('d');

        $string = preg_replace('/date\([\'"]?%?e[\'"]?\)/i', (string) $intlDateFormatter->format(time()), $string);

        $string = preg_replace_callback('/\b(yearsSince|durationSince)\((\d{4}(?:-\d{2}-\d{2})?)\)/', fn (array $match): string => $this->getElapsedTime($match, $locale), $string);

        return \is_string($string) ? $string : throw new Exception();
    }

    /** @param array<int|string, string> $match */
    private function getElapsedTime(array $match, string $locale): string
    {
        $today = new DateTimeImmutable('today');
        if ('yearsSince' === $match[1] && 4 === \strlen($match[2])) {
            return (string) ((int) $today->format('Y') - (int) $match[2]);
        }

        $date = 4 === \strlen($match[2]) ? $match[2].'-01-01' : $match[2];
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (false === $start || $start->format('Y-m-d') !== $date) {
            return $match[0];
        }

        $interval = $start->diff($today);
        if ('yearsSince' === $match[1]) {
            return (string) (int) $interval->format('%r%y');
        }

        $unit = $interval->y < 2 ? 'month' : 'year';
        $count = $interval->y < 2 ? $interval->y * 12 + $interval->m : $interval->y;
        if (1 === $interval->invert) {
            $count = -$count;
        }

        return (string) MessageFormatter::formatMessage($locale, '{0, number, ::measure-unit/duration-'.$unit.' unit-width-full-name}', [$count]);
    }

    private function getWinterYear(): string
    {
        return date('m') < 4 ? \Safe\date('Y') : \Safe\date('Y', strtotime('next year'));
    }

    private function getSummerYear(): string
    {
        return date('m') < 10 ? \Safe\date('Y') : \Safe\date('Y', strtotime('next year'));
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
