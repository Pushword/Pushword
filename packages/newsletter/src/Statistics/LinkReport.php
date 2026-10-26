<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Statistics;

/** Destination remains part of the identity when a stable CTA changes over time. */
final readonly class LinkReport
{
    public function __construct(public string $url, public ?string $linkId, public string $scope, public Metric $clicks, public Metric $clickers)
    {
    }

    public function safeUrl(): ?string
    {
        $parts = parse_url($this->url);

        return false !== $parts && isset($parts['host']) && \in_array($parts['scheme'] ?? '', ['http', 'https'], true) ? $this->url : null;
    }

    public function host(): string
    {
        return (string) parse_url($this->url, \PHP_URL_HOST);
    }

    public function path(): string
    {
        return parse_url($this->url, \PHP_URL_PATH) ?: '/';
    }
}
