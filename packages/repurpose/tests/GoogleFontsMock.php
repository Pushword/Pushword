<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Stands in for Google Fonts so installs run offline: the css2 stylesheet points
 * at a TTF that serves the bundled Roboto, so an installed face still renders.
 */
final class GoogleFontsMock
{
    /** @var list<string> */
    private array $requests = [];

    /**
     * @param list<string> $unknown    families Google answers 400 for
     * @param list<string> $weightless families without the requested weight (400 on a weighted query)
     */
    public function __construct(
        private readonly array $unknown = [],
        private readonly array $weightless = [],
    ) {
    }

    /**
     * The URLs requested since the last call, which forgets them.
     *
     * @return list<string>
     *
     * @phpstan-impure
     */
    public function takeRequests(): array
    {
        $requests = $this->requests;
        $this->requests = [];

        return $requests;
    }

    public function client(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->requests[] = $url;
            if (str_starts_with($url, 'https://fonts.gstatic.com/')) {
                return new MockResponse((string) file_get_contents(__DIR__.'/../src/Resources/font/roboto-regular.ttf'));
            }

            $query = (string) parse_url($url, \PHP_URL_QUERY);
            $family = str_replace('+', ' ', explode(':', substr($query, \strlen('family=')))[0]);
            if (\in_array($family, $this->unknown, true) || (str_contains($query, ':wght@') && \in_array($family, $this->weightless, true))) {
                return new MockResponse('', ['http_code' => 400]);
            }

            return new MockResponse("@font-face { font-family: '".$family."'; src: url(https://fonts.gstatic.com/s/font/v1/font.ttf) format('truetype'); }");
        });
    }
}
