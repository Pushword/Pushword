<?php

namespace Pushword\Conversation\Translation;

use Psr\Log\LoggerInterface;
use Pushword\Conversation\DependencyInjection\Configuration;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TranslationManager
{
    /** @var array<int, TranslatorInterface[]> */
    private array $translators = [];

    /** @var array<string, int> Monthly limits per service name */
    private array $monthlyLimits = [];

    private bool $initialized = false;

    private ?string $lastUsedTranslatorName = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SiteRegistry $apps,
        private readonly TranslationUsageTracker $usageTracker,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        $app = $this->apps->get();

        $deeplApiKey = $app->getCustomProperty('translation_deepl_api_key');
        $googleApiKey = $app->getCustomProperty('translation_google_api_key');
        $deeplUseFreeApi = $app->getCustomProperty('translation_deepl_use_free_api');
        $deeplMonthlyLimit = $app->getCustomProperty('translation_deepl_monthly_limit');
        $googleMonthlyLimit = $app->getCustomProperty('translation_google_monthly_limit');

        if (\is_string($deeplApiKey) && '' !== $deeplApiKey) {
            $translator = new DeepLTranslator($this->httpClient, $deeplApiKey, (bool) $deeplUseFreeApi);
            $this->addTranslator($translator, 100);
            $this->monthlyLimits[$translator->getName()] = \is_int($deeplMonthlyLimit)
                ? $deeplMonthlyLimit
                : Configuration::DEFAULT_DEEPL_MONTHLY_LIMIT;
        }

        if (\is_string($googleApiKey) && '' !== $googleApiKey) {
            $translator = new GoogleCloudTranslator($this->httpClient, $googleApiKey);
            $this->addTranslator($translator, 50);
            $this->monthlyLimits[$translator->getName()] = \is_int($googleMonthlyLimit)
                ? $googleMonthlyLimit
                : Configuration::DEFAULT_GOOGLE_MONTHLY_LIMIT;
        }
    }

    public function addTranslator(TranslatorInterface $translator, int $priority = 0): void
    {
        $this->translators[$priority][] = $translator;
        krsort($this->translators);
    }

    public function translate(string $text, ?string $sourceLocale, string $targetLocale): string
    {
        $this->initialize();

        if ('' === trim($text)) {
            return $text;
        }

        $characterCount = mb_strlen($text);
        $errors = [];

        foreach ($this->translators as $priorityTranslators) {
            foreach ($priorityTranslators as $translator) {
                if (! $translator->isAvailable()) {
                    continue;
                }

                if ($translator->isRateLimited()) {
                    $this->logger?->debug(\sprintf(
                        'Skipping %s translator: rate limited',
                        $translator->getName()
                    ));

                    continue;
                }

                $monthlyLimit = $this->monthlyLimits[$translator->getName()] ?? 0;
                if (! $this->usageTracker->isWithinLimit($translator->getName(), $monthlyLimit)) {
                    $this->logger?->debug(\sprintf(
                        'Skipping %s translator: monthly limit exceeded',
                        $translator->getName()
                    ));
                    $errors[] = \sprintf('%s: monthly limit exceeded', $translator->getName());

                    continue;
                }

                try {
                    $result = $translator->translate($text, $sourceLocale, $targetLocale);
                    $this->lastUsedTranslatorName = $translator->getName();
                    $this->usageTracker->addUsage($translator->getName(), $characterCount);
                    $this->logger?->info(\sprintf(
                        'Translated with %s: %s -> %s',
                        $translator->getName(),
                        $sourceLocale ?? 'auto',
                        $targetLocale
                    ));

                    return $result;
                } catch (TranslationException $e) {
                    $errors[] = $e->getMessage();
                    $this->logger?->warning(\sprintf(
                        'Translation failed with %s: %s',
                        $translator->getName(),
                        $e->getMessage()
                    ));
                }
            }
        }

        throw TranslationException::allServicesFailed(implode('; ', $errors));
    }

    /**
     * Detect the language of the given text.
     *
     * @return string|null The detected locale code (2-letter), or null if detection failed
     */
    public function detectLanguage(string $text): ?string
    {
        $this->initialize();

        if ('' === trim($text)) {
            return null;
        }

        $characterCount = mb_strlen($text);

        foreach ($this->translators as $priorityTranslators) {
            foreach ($priorityTranslators as $translator) {
                if (! $translator->isAvailable()) {
                    continue;
                }

                if ($translator->isRateLimited()) {
                    continue;
                }

                $monthlyLimit = $this->monthlyLimits[$translator->getName()] ?? 0;
                if (! $this->usageTracker->isWithinLimit($translator->getName(), $monthlyLimit)) {
                    continue;
                }

                try {
                    $detected = $translator->detectLanguage($text);
                    if (null !== $detected) {
                        $this->usageTracker->addUsage($translator->getName(), $characterCount);
                        $this->logger?->info(\sprintf(
                            'Language detected with %s: %s',
                            $translator->getName(),
                            $detected
                        ));

                        return $detected;
                    }
                } catch (TranslationException $e) {
                    $this->logger?->warning(\sprintf(
                        'Language detection failed with %s: %s',
                        $translator->getName(),
                        $e->getMessage()
                    ));
                }
            }
        }

        return null;
    }

    public function hasAvailableTranslator(): bool
    {
        $this->initialize();

        foreach ($this->translators as $priorityTranslators) {
            foreach ($priorityTranslators as $translator) {
                if (! $translator->isAvailable()) {
                    continue;
                }

                if ($translator->isRateLimited()) {
                    continue;
                }

                $monthlyLimit = $this->monthlyLimits[$translator->getName()] ?? 0;
                if ($this->usageTracker->isWithinLimit($translator->getName(), $monthlyLimit)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getLastUsedTranslatorName(): string
    {
        return $this->lastUsedTranslatorName ?? 'unknown';
    }

    public function hasConfiguredTranslator(): bool
    {
        $this->initialize();

        foreach ($this->translators as $priorityTranslators) {
            foreach ($priorityTranslators as $translator) {
                if ($translator->isAvailable()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isMonthlyLimitExceeded(): bool
    {
        $this->initialize();

        foreach ($this->translators as $priorityTranslators) {
            foreach ($priorityTranslators as $translator) {
                if (! $translator->isAvailable()) {
                    continue;
                }

                $monthlyLimit = $this->monthlyLimits[$translator->getName()] ?? 0;
                if ($this->usageTracker->isWithinLimit($translator->getName(), $monthlyLimit)) {
                    return false;
                }
            }
        }

        return true;
    }
}
