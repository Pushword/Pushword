<?php

namespace Pushword\Conversation\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pushword\Conversation\Repository\MessageRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'message')]
class Review extends Message
{
    #[Assert\Range(min: 1, max: 5)]
    protected ?int $rating = null;

    public function getRating(): ?int
    {
        if (null !== $this->rating) {
            return $this->rating;
        }

        $rating = $this->getCustomProperty('rating');

        return \is_numeric($rating) ? (int) $rating : null;
    }

    public function setRating(?int $rating): self
    {
        if (null === $rating) {
            $this->rating = null;
            $this->removeCustomProperty('rating');

            return $this;
        }

        $this->rating = $rating;
        $this->setCustomProperty('rating', $rating);

        return $this;
    }

    public function getTitle(): string
    {
        $title = $this->getCustomProperty('title');

        return \is_string($title) ? $title : '';
    }

    public function setTitle(?string $title): self
    {
        if (null === $title || '' === trim($title)) {
            $this->removeCustomProperty('title');

            return $this;
        }

        $this->setCustomProperty('title', $title);

        return $this;
    }

    /**
     * Get all translations as array.
     *
     * @return array<string, array{title?: string, content?: string}>
     */
    public function getTranslations(): array
    {
        $translations = $this->getCustomProperty('translations');

        /** @var array<string, array{title?: string, content?: string}> */
        return \is_array($translations) ? $translations : [];
    }

    /**
     * Set translation for a specific locale.
     */
    public function setTranslation(string $locale, ?string $title, ?string $content): self
    {
        $translations = $this->getTranslations();
        $translations[$locale] = array_filter([
            'title' => $title,
            'content' => $content,
        ], static fn (?string $v): bool => null !== $v && '' !== $v);

        if ([] === $translations[$locale]) {
            unset($translations[$locale]);
        }

        if ([] === $translations) {
            $this->removeCustomProperty('translations');
        } else {
            $this->setCustomProperty('translations', $translations);
        }

        return $this;
    }

    /**
     * Get translation for a specific locale with fallback.
     *
     * If locale is "fr-CA" and not found, tries "fr" before returning null.
     *
     * @return array{title?: string, content?: string}|null
     */
    public function getTranslation(string $locale): ?array
    {
        $translations = $this->getTranslations();

        if (isset($translations[$locale])) {
            return $translations[$locale];
        }

        if (str_contains($locale, '-')) {
            $baseLocale = explode('-', $locale)[0];
            if (isset($translations[$baseLocale])) {
                return $translations[$baseLocale];
            }
        }

        return null;
    }

    /**
     * Check if translation exists for locale (including fallback to base locale).
     */
    public function hasTranslation(string $locale): bool
    {
        return null !== $this->getTranslation($locale);
    }

    /**
     * Get translated title (with fallback to original).
     */
    public function getTranslatedTitle(string $locale): string
    {
        $translation = $this->getTranslation($locale);

        return $translation['title'] ?? $this->getTitle();
    }

    /**
     * Get translated content (with fallback to original).
     */
    public function getTranslatedContent(string $locale): string
    {
        $translation = $this->getTranslation($locale);

        return $translation['content'] ?? $this->getContent();
    }

    /**
     * Remove translation for a specific locale.
     */
    public function removeTranslation(string $locale): self
    {
        $translations = $this->getTranslations();
        unset($translations[$locale]);

        if ([] === $translations) {
            $this->removeCustomProperty('translations');
        } else {
            $this->setCustomProperty('translations', $translations);
        }

        return $this;
    }

    /**
     * Get all available locales including original.
     *
     * @return string[]
     */
    public function getAvailableLocales(): array
    {
        $locales = array_keys($this->getTranslations());
        $originalLocale = $this->locale;
        if (null !== $originalLocale && '' !== $originalLocale && ! \in_array($originalLocale, $locales, true)) {
            array_unshift($locales, $originalLocale);
        }

        return $locales;
    }
}
