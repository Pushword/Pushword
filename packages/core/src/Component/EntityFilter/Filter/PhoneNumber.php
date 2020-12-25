<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Service\LinkProvider;

use function Safe\preg_match_all;

use Twig\Environment;

class PhoneNumber extends AbstractFilter
{
    public LinkProvider $linkProvider;

    public AppConfig $app;

    public Environment $twig;

    public function apply(mixed $propertyValue): string
    {
        return $this->convertPhoneNumber($this->string($propertyValue));
    }

    private function convertPhoneNumber(string $body): string
    {
        // \xC2\xA0 ➜ parse aussi les n° des svg
        $rgx = '/ (?:(?:\+|00)33|0)(\s|&nbsp;|\xC2\xA0)*[1-9](?:([\s.-]|&nbsp;|\xC2\xA0)*\d{2}){4}(?P<after>( |&nbsp;)|\.<\/|\. |$)/iU';
        preg_match_all($rgx, $body, $matches);

        /** @var array{0: ?string[], 'after': string[] } $matches */
        if (! isset($matches[0])) {
            return $body;
        }

        foreach ($matches[0] as $k => $m) {
            $after = $matches['after'][$k] ?? '';
            $body = str_replace($m, ' '.$this->linkProvider->renderPhoneNumber(trim(substr($m, 0, -\strlen($after)))).$after, $body);
        }

        return $body;
    }
}
