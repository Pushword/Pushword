<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Click;

/** Explicit template roles and optional author-provided CTA identities. */
final readonly class LinkMetadata
{
    public function __construct(public string $scope, public ?string $linkId)
    {
    }

    public static function fromHtml(string $html, string $defaultScope = 'body'): self
    {
        $scope = self::attribute($html, 'data-newsletter-scope') ?? $defaultScope;
        $scope = \in_array($scope, ['body', 'navigation', 'footer', 'system'], true) ? $scope : 'unknown';

        $linkId = self::attribute($html, 'data-newsletter-link-id');

        return new self($scope, null !== $linkId && '' !== $linkId && mb_strlen($linkId) <= 100 ? $linkId : null);
    }

    private static function attribute(string $html, string $name): ?string
    {
        return 1 === preg_match('/\s'.preg_quote($name, '/').'\s*=\s*([\'"])(.*?)\1/is', $html, $matches)
            ? html_entity_decode($matches[2], \ENT_QUOTES | \ENT_HTML5) : null;
    }
}
