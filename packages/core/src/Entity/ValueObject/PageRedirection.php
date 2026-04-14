<?php

namespace Pushword\Core\Entity\ValueObject;

use function Safe\preg_match;

final readonly class PageRedirection
{
    public function __construct(
        public string $url,
        public int $code = 301,
    ) {
    }

    public static function fromContent(string $mainContent): ?self
    {
        if (! str_starts_with($mainContent, 'Location:')) {
            return null;
        }

        $url = trim(substr($mainContent, 9));
        $code = 301;

        if (1 === preg_match('/ [1-5]\d{2}$/', $url, $match)) {
            /** @var array{0: string} $match */
            $code = (int) trim($match[0]);
            $url = preg_replace('/ [1-5]\d{2}$/', '', $url) ?? $url;
        }

        if (false !== filter_var($url, \FILTER_VALIDATE_URL) || 1 === preg_match('/^[^ ]+$/', $url)) {
            return new self($url, $code);
        }

        return null;
    }
}
