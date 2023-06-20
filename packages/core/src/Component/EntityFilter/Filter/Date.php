<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\AutowiringTrait\RequiredAppTrait;
use Pushword\Core\Utils\F;

class Date extends AbstractFilter
{
    use RequiredAppTrait;

    public function apply(mixed $propertyValue): string
    {
        return $this->convertDateShortCode($this->string($propertyValue), $this->getApp()->getDefaultLocale());
    }

    private function convertDateShortCode(string $string, string $locale = null): string
    {
        $locale = null !== $locale ? $this->convertLocale($locale) : 'fr_FR';
        $intlDateFormatter = new \IntlDateFormatter($locale, \IntlDateFormatter::FULL, \IntlDateFormatter::NONE);

        // $string = preg_replace('/date\([\'"]?([a-z% ]+)[\'"]?\)/i',
        //  strftime(strpos('\1', '%') ? '\1': '%\1'), $string);
        $string = F::preg_replace_str('/date\([\'"]?%?S[\'"]?\)/i', $this->getSummerYear(), $string);
        $string = F::preg_replace_str('/date\([\'"]?%?W[\'"]?\)/i', $this->getWinterYear(), $string);
        $string = F::preg_replace_str('/date\([\'"]?%?Y-1[\'"]?\)/i', date('Y', strtotime('-1 year')), $string);
        $string = F::preg_replace_str('/date\([\'"]?%?Y\+1[\'"]?\)/i', date('Y', strtotime('next year')), $string);
        $string = F::preg_replace_str('/date\([\'"]?%?Y[\'"]?\)/i', date('Y'), $string);

        $intlDateFormatter->setPattern('MMMM');
        $string = F::preg_replace_str('/date\([\'"]?%?(B|M)[\'"]?\)/i', (string) $intlDateFormatter->format(time()), $string);

        $intlDateFormatter->setPattern('cccc');
        $string = F::preg_replace_str('/date\([\'"]?%?A[\'"]?\)/i', (string) $intlDateFormatter->format(time()), $string);

        $intlDateFormatter->setPattern('d');

        return F::preg_replace_str('/date\([\'"]?%?e[\'"]?\)/i', (string) $intlDateFormatter->format(time()), $string);
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
        if ('fr' == $locale) {
            return 'fr_FR';
        }

        if ('en' == $locale) {
            return 'en_UK';
        }

        return $locale;
    }
}
