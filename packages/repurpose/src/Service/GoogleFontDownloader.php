<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches a Google Fonts family as a static TTF, for {@see FontInstaller}.
 *
 * The css2 endpoint serves TTF sources when the client does not advertise woff2
 * support, so we request the stylesheet with a legacy User-Agent and download the
 * first `fonts.gstatic.com/….ttf` URL it declares. A weight the family does not
 * have returns a 400 status, in which case we retry unweighted and take the
 * family's default instance.
 */
final readonly class GoogleFontDownloader
{
    private const string CSS_ENDPOINT = 'https://fonts.googleapis.com/css2?family=';

    /** A pre-woff2 UA: makes the API answer with truetype sources. */
    private const string LEGACY_UA = 'Mozilla/4.0';

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * The TTF bytes for a family at the given weight (falling back to the
     * family's default weight), or null when the family is unknown or the
     * network is unavailable.
     */
    public function download(string $family, int $weight): ?string
    {
        $query = str_replace(' ', '+', $family);
        $css = $this->fetch(self::CSS_ENDPOINT.$query.':wght@'.$weight)
            ?? $this->fetch(self::CSS_ENDPOINT.$query);
        if (null === $css || 1 !== preg_match('#https://fonts\.gstatic\.com/[^)\s]+\.ttf#', $css, $match)) {
            return null;
        }

        return $this->fetch($match[0]);
    }

    private function fetch(string $url): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['User-Agent' => self::LEGACY_UA],
                'timeout' => 20,
            ]);
            if (200 !== $response->getStatusCode()) {
                return null;
            }

            $content = $response->getContent();
        } catch (ExceptionInterface) {
            return null;
        }

        return '' === $content ? null : $content;
    }
}
