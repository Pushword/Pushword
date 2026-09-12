<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Pushword\Conversation\Entity\Review;

final class ReviewTranslationTest extends TestCase
{
    public function testLocaleGetterSetter(): void
    {
        $review = new Review();

        self::assertNull($review->locale);

        $review->locale = 'en';
        self::assertSame('en', $review->locale);

        $review->locale = null;
        self::assertNull($review->locale);
    }

    public function testTranslationStorage(): void
    {
        $review = new Review();
        $review->locale = 'en';
        $review->setTitle('Great product');
        $review->setContent('This is amazing!');

        $review->setTranslation('fr', 'Excellent produit', "C'est incroyable !");

        self::assertTrue($review->hasTranslation('fr'));
        self::assertFalse($review->hasTranslation('de'));

        $translation = $review->getTranslation('fr');
        self::assertNotNull($translation);
        self::assertArrayHasKey('title', $translation);
        self::assertArrayHasKey('content', $translation);
        self::assertSame('Excellent produit', $translation['title']);
        self::assertSame("C'est incroyable !", $translation['content']);
    }

    public function testTranslatedGetters(): void
    {
        $review = new Review();
        $review->locale = 'en';
        $review->setTitle('Original title');
        $review->setContent('Original content');
        $review->setTranslation('fr', 'Titre traduit', 'Contenu traduit');

        // Get translated content
        self::assertSame('Titre traduit', $review->getTranslatedTitle('fr'));
        self::assertSame('Contenu traduit', $review->getTranslatedContent('fr'));

        // Fallback to original for non-existing locale
        self::assertSame('Original title', $review->getTranslatedTitle('de'));
        self::assertSame('Original content', $review->getTranslatedContent('de'));
    }

    public function testRemoveTranslation(): void
    {
        $review = new Review();
        $review->setTranslation('fr', 'Title', 'Content');
        $review->setTranslation('de', 'Titel', 'Inhalt');

        self::assertTrue($review->hasTranslation('fr'));

        $review->removeTranslation('fr');

        self::assertFalse($review->hasTranslation('fr'));
        self::assertTrue($review->hasTranslation('de'));
    }

    public function testAvailableLocales(): void
    {
        $review = new Review();
        $review->locale = 'en';
        $review->setTranslation('fr', 'Title', 'Content');
        $review->setTranslation('de', 'Titel', 'Inhalt');

        $locales = $review->getAvailableLocales();

        self::assertContains('en', $locales);
        self::assertContains('fr', $locales);
        self::assertContains('de', $locales);
    }

    public function testEmptyTranslationIsRemoved(): void
    {
        $review = new Review();
        $review->setTranslation('fr', 'Title', 'Content');

        self::assertTrue($review->hasTranslation('fr'));

        // Setting empty values removes the translation
        $review->setTranslation('fr', '', '');

        self::assertFalse($review->hasTranslation('fr'));
    }

    public function testGetTranslationsReturnsEmptyArrayWhenNoTranslations(): void
    {
        $review = new Review();

        self::assertSame([], $review->getTranslations());
    }

    public function testPartialTranslation(): void
    {
        $review = new Review();
        $review->locale = 'en';
        $review->setTitle('Original title');
        $review->setContent('Original content');

        // Only translate title
        $review->setTranslation('fr', 'Titre traduit', null);

        $translation = $review->getTranslation('fr');
        self::assertNotNull($translation);
        self::assertArrayHasKey('title', $translation);
        self::assertSame('Titre traduit', $translation['title']);
        self::assertArrayNotHasKey('content', $translation);

        // Fallback for content
        self::assertSame('Original content', $review->getTranslatedContent('fr'));
    }

    public function testLocaleFallbackToBaseLocale(): void
    {
        $review = new Review();
        $review->locale = 'en';
        $review->setTitle('Original title');
        $review->setContent('Original content');
        $review->setTranslation('fr', 'Titre français', 'Contenu français');

        // fr-CA should fallback to fr
        self::assertTrue($review->hasTranslation('fr-CA'));
        self::assertSame('Titre français', $review->getTranslatedTitle('fr-CA'));
        self::assertSame('Contenu français', $review->getTranslatedContent('fr-CA'));

        // fr-FR should also fallback to fr
        self::assertTrue($review->hasTranslation('fr-FR'));
        self::assertSame('Titre français', $review->getTranslatedTitle('fr-FR'));

        // en-US without en translation should fallback to original
        self::assertFalse($review->hasTranslation('en-US'));
        self::assertSame('Original title', $review->getTranslatedTitle('en-US'));
    }

    public function testExactLocaleMatchTakesPrecedence(): void
    {
        $review = new Review();
        $review->locale = 'en';
        $review->setTitle('Original title');
        $review->setTranslation('fr', 'Titre français', 'Contenu français');
        $review->setTranslation('fr-CA', 'Titre québécois', 'Contenu québécois');

        // Exact match should take precedence
        self::assertSame('Titre québécois', $review->getTranslatedTitle('fr-CA'));
        self::assertSame('Contenu québécois', $review->getTranslatedContent('fr-CA'));

        // fr should still return fr translation
        self::assertSame('Titre français', $review->getTranslatedTitle('fr'));
    }
}
