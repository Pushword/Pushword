<?php

namespace Pushword\Conversation\Translation;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleCloudTranslator implements TranslatorInterface
{
    private const string TRANSLATE_URL = 'https://translation.googleapis.com/language/translate/v2';

    private const string DETECT_URL = 'https://translation.googleapis.com/language/translate/v2/detect';

    private bool $rateLimited = false;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $apiKey,
    ) {
    }

    public function translate(string $text, ?string $sourceLocale, string $targetLocale): string
    {
        if (! $this->isAvailable()) {
            throw TranslationException::serviceUnavailable($this->getName());
        }

        if ($this->isRateLimited()) {
            throw TranslationException::rateLimited($this->getName());
        }

        $payload = [
            'q' => $text,
            'target' => $this->normalizeLocale($targetLocale),
            'format' => 'text',
        ];

        if (null !== $sourceLocale && '' !== $sourceLocale) {
            $payload['source'] = $this->normalizeLocale($sourceLocale);
        }

        $response = $this->httpClient->request('POST', self::TRANSLATE_URL, [
            'query' => ['key' => $this->apiKey],
            'json' => $payload,
        ]);

        $statusCode = $response->getStatusCode();

        if (429 === $statusCode || 403 === $statusCode) {
            $this->rateLimited = true;

            throw TranslationException::rateLimited($this->getName());
        }

        if (200 !== $statusCode) {
            throw TranslationException::apiError($this->getName(), 'HTTP '.$statusCode);
        }

        /** @var array{data?: array{translations?: array<int, array{translatedText?: string}>}} $data */
        $data = $response->toArray();

        $translatedText = $data['data']['translations'][0]['translatedText'] ?? null;
        if (! \is_string($translatedText)) {
            throw TranslationException::apiError($this->getName(), 'Invalid response format');
        }

        return $translatedText;
    }

    public function isAvailable(): bool
    {
        return null !== $this->apiKey && '' !== $this->apiKey;
    }

    public function getName(): string
    {
        return 'Google Cloud Translation';
    }

    public function isRateLimited(): bool
    {
        return $this->rateLimited;
    }

    public function detectLanguage(string $text): ?string
    {
        if (! $this->isAvailable()) {
            throw TranslationException::serviceUnavailable($this->getName());
        }

        if ($this->isRateLimited()) {
            throw TranslationException::rateLimited($this->getName());
        }

        $response = $this->httpClient->request('POST', self::DETECT_URL, [
            'query' => ['key' => $this->apiKey],
            'json' => ['q' => $text],
        ]);

        $statusCode = $response->getStatusCode();

        if (429 === $statusCode || 403 === $statusCode) {
            $this->rateLimited = true;

            throw TranslationException::rateLimited($this->getName());
        }

        if (200 !== $statusCode) {
            throw TranslationException::apiError($this->getName(), 'HTTP '.$statusCode);
        }

        /** @var array{data?: array{detections?: array<int, array<int, array{language?: string, confidence?: float}>>}} $data */
        $data = $response->toArray();

        $detected = $data['data']['detections'][0][0]['language'] ?? null;

        return \is_string($detected) ? $detected : null;
    }

    private function normalizeLocale(string $locale): string
    {
        // Google uses lowercase 2-letter codes
        return strtolower(explode('-', $locale)[0]);
    }
}
