<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

class Date extends AbstractFilter
{
    use RequiredAppTrait;

    /**
     * @return string
     */
    public function apply($string)
    {
        return $this->convertDateShortCode($string, $this->getApp()->getDefaultLocale());
    }

    private function convertDateShortCode(string $string, ?string $locale = null): string
    {
        //var_dump($string); exit;
        if ($locale) {
            setlocale(\LC_TIME, $this->convertLocale($locale));
        }

        //$string = preg_replace('/date\([\'"]?([a-z% ]+)[\'"]?\)/i',
        //  strftime(strpos('\1', '%') ? '\1': '%\1'), $string);
        $string = preg_replace('/date\([\'"]?%?Y[\'"]?\)/i', strftime('%Y'), $string);
        $string = preg_replace('/date\([\'"]?%?(B|M)[\'"]?\)/i', strftime('%B'), $string);
        $string = preg_replace('/date\([\'"]?%?A[\'"]?\)/i', strftime('%A'), $string);
        $string = preg_replace('/date\([\'"]?%?e[\'"]?\)/i', strftime('%e'), $string);

        return $string;
    }

    private function convertLocale($locale)
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
