<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Exception;
use IntlDateFormatter;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;

use function Safe\preg_replace;

#[AsFilter]
class Date implements FilterInterface
{
    /** @var array<string, IntlDateFormatter> */
    private array $formatterCache = [];

    public function __construct(
        private readonly AppPool $apps,
    ) {
    }

    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        assert(is_scalar($propertyValue));
        $propertyValue = (string) $propertyValue;

        return $this->convertDateShortCode($propertyValue, $this->apps->get($page->host)->getLocale());
    }

    private function getFormatter(string $locale): IntlDateFormatter
    {
        return $this->formatterCache[$locale] ??= new IntlDateFormatter(
            $locale,
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE
        );
    }

    private function convertDateShortCode(string $string, ?string $locale = null): string
    {
        $locale = null !== $locale ? $this->convertLocale($locale) : 'fr_FR';
        $intlDateFormatter = $this->getFormatter($locale);

        // $string = preg_replace('/date\([\'"]?([a-z% ]+)[\'"]?\)/i',
        //  strftime(strpos('\1', '%') ? '\1': '%\1'), $string);
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

        return \is_string($string) ? $string : throw new Exception();
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
        if ('fr' === $locale) {
            return 'fr_FR';
        }

        if ('en' === $locale) {
            return 'en_UK';
        }

        return $locale;
    }
}
