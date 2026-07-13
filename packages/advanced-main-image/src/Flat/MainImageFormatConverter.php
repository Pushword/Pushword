<?php

namespace Pushword\AdvancedMainImage\Flat;

use Pushword\AdvancedMainImage\DependencyInjection\Configuration;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\Converter\FlatPropertyConverterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Converts mainImageFormat between integer (database) and translated label (flat file).
 */
final readonly class MainImageFormatConverter implements FlatPropertyConverterInterface
{
    /**
     * Shared prefix of the default format translation keys
     * (adminPageMainImageFormatNone, …). The suffix is the format's human name.
     */
    private const string TRANSLATION_KEY_PREFIX = 'adminPageMainImageFormat';

    public function __construct(
        private SiteRegistry $apps,
        private TranslatorInterface $translator,
    ) {
    }

    public function getPropertyName(): string
    {
        return 'mainImageFormat';
    }

    public function toFlatValue(mixed $value): mixed
    {
        if (! \is_int($value)) {
            return $value;
        }

        $formats = $this->getFormats();
        foreach ($formats as $translationKey => $intValue) {
            if ($intValue === $value) {
                return $this->translator->trans($translationKey);
            }
        }

        return $value;
    }

    public function fromFlatValue(mixed $value): mixed
    {
        if (! \is_string($value)) {
            return $value;
        }

        $formats = $this->getFormats();

        // First: try to match against translated values (current locale)
        foreach ($formats as $translationKey => $intValue) {
            if ($this->translator->trans($translationKey) === $value) {
                return $intValue;
            }
        }

        // Second: try the value as a translation key directly
        if (isset($formats[$value])) {
            return $formats[$value];
        }

        // Third: try numeric value (backward compatibility)
        if (is_numeric($value)) {
            return (int) $value;
        }

        // Fourth: accept the format's human name — the key suffix after the
        // standard prefix (e.g. "None" for adminPageMainImageFormatNone). This
        // makes the obvious word work even when the translated label is a symbol
        // like ∅; without it "None" would be rejected while "Normal" (whose label
        // happens to be the word) worked, an inconsistency for API/flat clients.
        $needle = strtolower($value);
        foreach ($formats as $translationKey => $intValue) {
            if (! str_starts_with($translationKey, self::TRANSLATION_KEY_PREFIX)) {
                continue;
            }

            $name = substr($translationKey, \strlen(self::TRANSLATION_KEY_PREFIX));
            if ('' !== $name && strtolower($name) === $needle) {
                return $intValue;
            }
        }

        // Unresolvable string: this property is integer-backed, so a value that
        // matches no label, key or number is invalid. Return null rather than
        // the raw string — callers skip null (flat import) or reject it (API),
        // instead of storing a string that crashes the int-typed hero render.
        return null;
    }

    /**
     * @return array<string, int>
     */
    private function getFormats(): array
    {
        /** @var array<string, int> */
        return $this->apps->get()->getArray(
            'main_image_formats',
            Configuration::DEFAULT_MAIN_IMAGE_FORMATS,
        );
    }
}
