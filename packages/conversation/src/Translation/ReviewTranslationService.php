<?php

namespace Pushword\Conversation\Translation;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Conversation\Entity\Review;

final readonly class ReviewTranslationService
{
    public function __construct(
        private TranslationManager $translationManager,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public const string SKIPPED = 'skipped';

    public const string FAILED = 'failed';

    /**
     * Translate a review to target locales.
     *
     * @param string[] $targetLocales
     *
     * @return array<string, string> Results per locale (service name, self::SKIPPED, or self::FAILED)
     */
    public function translateReview(
        Review $review,
        array $targetLocales,
        bool $force = false,
    ): array {
        $sourceLocale = $review->locale;
        if (null === $sourceLocale || '' === $sourceLocale) {
            throw TranslationException::noSourceLocale();
        }

        $results = [];

        foreach ($targetLocales as $targetLocale) {
            if ($targetLocale === $sourceLocale) {
                continue;
            }

            if (! $force && $review->hasTranslation($targetLocale)) {
                $results[$targetLocale] = self::SKIPPED;

                continue;
            }

            $title = $review->getTitle();
            $content = $review->getContent();

            if ('' === $title && '' === $content) {
                $results[$targetLocale] = self::SKIPPED;

                continue;
            }

            try {
                $translatedTitle = '' !== $title
                    ? $this->translationManager->translate($title, $sourceLocale, $targetLocale)
                    : '';

                $translatedContent = '' !== $content
                    ? $this->translationManager->translate($content, $sourceLocale, $targetLocale)
                    : '';

                $review->setTranslation($targetLocale, $translatedTitle, $translatedContent);
                $review->updatedAt = new DateTime();
                $results[$targetLocale] = $this->translationManager->getLastUsedTranslatorName();
            } catch (TranslationException $e) {
                $results[$targetLocale] = self::FAILED.': '.$e->getMessage();
            }
        }

        return $results;
    }

    public function persist(Review $review): void
    {
        $this->entityManager->persist($review);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
