<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Translation;

use PHPUnit\Framework\TestCase;
use Pushword\Conversation\Translation\TranslationException;
use Pushword\Conversation\Translation\TranslatorInterface;

final class TranslationManagerTest extends TestCase
{
    public function testTranslateWithAvailableTranslator(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('isAvailable')->willReturn(true);
        $translator->method('isRateLimited')->willReturn(false);
        $translator->method('translate')->willReturn('Translated text');

        $manager = $this->createTranslationManagerWithTranslator($translator, 100);

        $result = $manager->translate('Original text', 'en', 'fr');

        self::assertSame('Translated text', $result);
    }

    public function testFallbackToSecondTranslator(): void
    {
        $primary = $this->createMock(TranslatorInterface::class);
        $primary->method('isAvailable')->willReturn(true);
        $primary->method('isRateLimited')->willReturn(true);
        $primary->method('getName')->willReturn('Primary');

        $fallback = $this->createMock(TranslatorInterface::class);
        $fallback->method('isAvailable')->willReturn(true);
        $fallback->method('isRateLimited')->willReturn(false);
        $fallback->method('translate')->willReturn('Fallback translation');
        $fallback->method('getName')->willReturn('Fallback');

        $manager = $this->createTranslationManagerWithTranslators([
            [100, $primary],
            [50, $fallback],
        ]);

        $result = $manager->translate('Original text', 'en', 'fr');

        self::assertSame('Fallback translation', $result);
    }

    public function testEmptyTextReturnsEmpty(): void
    {
        $manager = $this->createTranslationManagerWithTranslators([]);

        $result = $manager->translate('', 'en', 'fr');

        self::assertSame('', $result);
    }

    public function testWhitespaceOnlyTextReturnsEmpty(): void
    {
        $manager = $this->createTranslationManagerWithTranslators([]);

        $result = $manager->translate('   ', 'en', 'fr');

        self::assertSame('   ', $result);
    }

    public function testNoAvailableTranslatorThrows(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('isAvailable')->willReturn(false);

        $manager = $this->createTranslationManagerWithTranslator($translator, 100);

        $this->expectException(TranslationException::class);

        $manager->translate('Text', 'en', 'fr');
    }

    public function testHasAvailableTranslatorReturnsTrueWhenAvailable(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('isAvailable')->willReturn(true);
        $translator->method('isRateLimited')->willReturn(false);

        $manager = $this->createTranslationManagerWithTranslator($translator, 100);

        self::assertTrue($manager->hasAvailableTranslator());
    }

    public function testHasAvailableTranslatorReturnsFalseWhenNoneAvailable(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('isAvailable')->willReturn(false);

        $manager = $this->createTranslationManagerWithTranslator($translator, 100);

        self::assertFalse($manager->hasAvailableTranslator());
    }

    public function testHasAvailableTranslatorReturnsFalseWhenAllRateLimited(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('isAvailable')->willReturn(true);
        $translator->method('isRateLimited')->willReturn(true);

        $manager = $this->createTranslationManagerWithTranslator($translator, 100);

        self::assertFalse($manager->hasAvailableTranslator());
    }

    public function testTranslationExceptionFallsBackToNextTranslator(): void
    {
        $primary = $this->createMock(TranslatorInterface::class);
        $primary->method('isAvailable')->willReturn(true);
        $primary->method('isRateLimited')->willReturn(false);
        $primary->method('getName')->willReturn('Primary');
        $primary->method('translate')->willThrowException(
            TranslationException::apiError('Primary', 'API Error')
        );

        $fallback = $this->createMock(TranslatorInterface::class);
        $fallback->method('isAvailable')->willReturn(true);
        $fallback->method('isRateLimited')->willReturn(false);
        $fallback->method('translate')->willReturn('Fallback translation');
        $fallback->method('getName')->willReturn('Fallback');

        $manager = $this->createTranslationManagerWithTranslators([
            [100, $primary],
            [50, $fallback],
        ]);

        $result = $manager->translate('Text', 'en', 'fr');

        self::assertSame('Fallback translation', $result);
    }

    /**
     * @param array<array{int, TranslatorInterface}> $translatorsWithPriority
     */
    private function createTranslationManagerWithTranslators(array $translatorsWithPriority): TestableTranslationManager
    {
        $manager = new TestableTranslationManager();

        foreach ($translatorsWithPriority as [$priority, $translator]) {
            $manager->addTranslator($translator, $priority);
        }

        return $manager;
    }

    private function createTranslationManagerWithTranslator(TranslatorInterface $translator, int $priority): TestableTranslationManager
    {
        return $this->createTranslationManagerWithTranslators([[$priority, $translator]]);
    }
}
