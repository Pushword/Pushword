<?php

namespace Pushword\Newsletter\Utm;

use Pushword\Core\Component\EntityFilter\Filter\HtmlUnpublishedLink;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Audience;

/**
 * Appends `utm_*` to the links of a mail body.
 *
 * This is attribution, not click tracking: no redirect stands between the reader
 * and the destination, and nothing is recorded against a contact. The parameters
 * are read by the destination's own analytics — which is why only hosts this
 * installation serves are tagged, the rest would carry our labels onto somebody
 * else's domain for nothing.
 */
final readonly class UtmDecorator
{
    private const string MEDIUM = 'email';

    public function __construct(
        private SiteRegistry $siteRegistry,
    ) {
    }

    public function decorate(string $html, Audience $audience, ?UtmTag $utmTag): string
    {
        $source = $audience->utmSource;

        if (null === $source || ! $utmTag instanceof UtmTag || ! str_contains($html, '<a ')) {
            return $html;
        }

        return preg_replace_callback(
            HtmlUnpublishedLink::HTML_REGEX,
            fn (array $match): string => $this->tagLink($match, $source, $utmTag),
            $html
        ) ?? $html;
    }

    /** @param array<int|string, string> $match */
    private function tagLink(array $match, string $source, UtmTag $utmTag): string
    {
        $tagged = $this->tagUrl(html_entity_decode($match['href'], \ENT_QUOTES | \ENT_HTML5), $source, $utmTag);

        if (null === $tagged) {
            return $match[0];
        }

        $quote = $match['quote'];

        return '<a'.$match['before'].'href='.$quote.htmlspecialchars($tagged, \ENT_QUOTES | \ENT_HTML5).$quote
            .$match['after'].'>'.$match['content'].'</a>';
    }

    /** @return string|null the tagged URL, or null when this link is none of our business */
    private function tagUrl(string $url, string $source, UtmTag $utmTag): ?string
    {
        $parts = parse_url($url);

        // A mailto:, an anchor, a relative path: nothing with analytics behind it.
        if (false === $parts
            || ! isset($parts['scheme'], $parts['host'])
            || ! \in_array($parts['scheme'], ['http', 'https'], true)) {
            return null;
        }

        if (! $this->siteRegistry->isKnownHost($parts['host'])) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);

        // An author who tagged a link by hand meant it; do not argue.
        if (isset($query['utm_source'])) {
            return null;
        }

        $query['utm_source'] = $source;
        $query['utm_medium'] = self::MEDIUM;
        $query['utm_campaign'] = $utmTag->campaign;

        if (null !== $utmTag->content) {
            $query['utm_content'] = $utmTag->content;
        }

        return $parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '')
            .'?'.http_build_query($query)
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
