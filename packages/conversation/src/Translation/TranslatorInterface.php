<?php

namespace Pushword\Conversation\Translation;

interface TranslatorInterface
{
    /**
     * Translate text from source to target locale.
     *
     * @throws TranslationException On translation failure
     */
    public function translate(string $text, ?string $sourceLocale, string $targetLocale): string;

    /**
     * Detect the language of the given text.
     *
     * @return string|null The detected locale code, or null if detection failed
     *
     * @throws TranslationException On API failure
     */
    public function detectLanguage(string $text): ?string;

    /**
     * Check if the translator service is available and configured.
     */
    public function isAvailable(): bool;

    /**
     * Get the translator service name for logging.
     */
    public function getName(): string;

    /**
     * Check if rate limit has been reached.
     */
    public function isRateLimited(): bool;
}
