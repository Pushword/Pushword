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

        return $value;
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
