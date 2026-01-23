<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Translation;

use Pushword\Conversation\Translation\TranslationException;
use Pushword\Conversation\Translation\TranslatorInterface;

/**
 * Testable version of TranslationManager that doesn't require AppPool.
 */
final class TestableTranslationManager
{
    /** @var array<int, TranslatorInterface[]> */
    private array $translators = [];

    public function addTranslator(TranslatorInterface $translator, int $priority = 0): void
    {
        $this->translators[$priority][] = $translator;
        krsort($this->translators);
    }

    public function translate(string $text, string $sourceLocale, string $targetLocale): string
    {
        if ('' === trim($text)) {
            return $text;
        }

        $errors = [];

        foreach ($this->translators as $priorityTranslators) {
            foreach ($priorityTranslators as $translator) {
                if (! $translator->isAvailable()) {
                    continue;
                }

                if ($translator->isRateLimited()) {
                    continue;
                }

                try {
                    return $translator->translate($text, $sourceLocale, $targetLocale);
                } catch (TranslationException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        throw TranslationException::allServicesFailed(implode('; ', $errors));
    }

    public function hasAvailableTranslator(): bool
    {
        foreach ($this->translators as $priorityTranslators) {
            foreach ($priorityTranslators as $translator) {
                if ($translator->isAvailable() && ! $translator->isRateLimited()) {
                    return true;
                }
            }
        }

        return false;
    }
}
