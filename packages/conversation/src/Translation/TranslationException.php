<?php

namespace Pushword\Conversation\Translation;

use Exception;

class TranslationException extends Exception
{
    public static function serviceUnavailable(string $serviceName): self
    {
        return new self(\sprintf('Translation service "%s" is not available or not configured.', $serviceName));
    }

    public static function rateLimited(string $serviceName): self
    {
        return new self(\sprintf('Translation service "%s" rate limit reached.', $serviceName));
    }

    public static function apiError(string $serviceName, string $message): self
    {
        return new self(\sprintf('Translation service "%s" API error: %s', $serviceName, $message));
    }

    public static function noSourceLocale(): self
    {
        return new self('Review has no source locale defined.');
    }

    public static function allServicesFailed(string $errors): self
    {
        return new self('All translation services failed: '.$errors);
    }
}
