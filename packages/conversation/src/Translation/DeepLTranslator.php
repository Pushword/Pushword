<?php

namespace Pushword\Conversation\Translation;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class DeepLTranslator implements TranslatorInterface
{
    private const string TRANSLATE_URL_FREE = 'https://api-free.deepl.com/v2/translate';

    private const string TRANSLATE_URL_PRO = 'https://api.deepl.com/v2/translate';

    private bool $rateLimited = false;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $apiKey,
        private readonly bool $useFreeApi = true,
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
            'text' => [$text],
            'target_lang' => $this->normalizeLocale($targetLocale),
        ];

        if (null !== $sourceLocale && '' !== $sourceLocale) {
            $payload['source_lang'] = $this->normalizeLocale($sourceLocale);
        }

        $response = $this->httpClient->request('POST', $this->getTranslateUrl(), [
            'headers' => [
                'Authorization' => 'DeepL-Auth-Key '.$this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        $statusCode = $response->getStatusCode();

        if (429 === $statusCode) {
            $this->rateLimited = true;

            throw TranslationException::rateLimited($this->getName());
        }

        if (200 !== $statusCode) {
            throw TranslationException::apiError($this->getName(), 'HTTP '.$statusCode);
        }

        /** @var array{translations?: array<int, array{text?: string}>} $data */
        $data = $response->toArray();

        $translatedText = $data['translations'][0]['text'] ?? null;
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
        return 'DeepL';
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

        // DeepL doesn't have a dedicated detection endpoint, but we can use translate
        // with a dummy target and extract detected_source_language from response
        $response = $this->httpClient->request('POST', $this->getTranslateUrl(), [
            'headers' => [
                'Authorization' => 'DeepL-Auth-Key '.$this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'text' => [$text],
                'target_lang' => 'EN', // Use EN as dummy target
            ],
        ]);

        $statusCode = $response->getStatusCode();

        if (429 === $statusCode) {
            $this->rateLimited = true;

            throw TranslationException::rateLimited($this->getName());
        }

        if (200 !== $statusCode) {
            throw TranslationException::apiError($this->getName(), 'HTTP '.$statusCode);
        }

        /** @var array{translations?: array<int, array{detected_source_language?: string}>} $data */
        $data = $response->toArray();

        $detected = $data['translations'][0]['detected_source_language'] ?? null;

        return \is_string($detected) ? strtolower($detected) : null;
    }

    private function getTranslateUrl(): string
    {
        return $this->useFreeApi ? self::TRANSLATE_URL_FREE : self::TRANSLATE_URL_PRO;
    }

    private function normalizeLocale(string $locale): string
    {
        // DeepL uses uppercase 2-letter codes, with special handling for variants
        $locale = strtoupper(explode('-', $locale)[0]);
        // Handle Portuguese variants - default to European Portuguese
        if ('PT' === $locale) {
            return 'PT-PT';
        }

        return $locale;
    }
}
