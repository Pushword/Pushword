<?php

namespace Pushword\Core\Image\License;

/**
 * The rights metadata a file carries in its own bytes (XMP, IPTC-IIM or EXIF).
 *
 * Empty string / empty array means "absent": a whitespace-only dc:rights and a
 * `<dc:creator><rdf:Seq/></dc:creator>` are both nothing, not a value.
 */
final readonly class EmbeddedRights
{
    /** @param string[] $creator */
    public function __construct(
        public string $license = '',
        public string $acquireLicensePage = '',
        public string $creditText = '',
        public array $creator = [],
        public string $copyrightNotice = '',
        public string $digitalSourceType = '',
    ) {
    }

    /**
     * First non-empty value wins, property by property: XMP, then IIM, then EXIF.
     * A file whose XMP holds only a credit still gets its EXIF Artist.
     */
    public static function merge(self ...$sources): self
    {
        $merged = new self();

        foreach ($sources as $source) {
            $merged = new self(
                license: '' !== $merged->license ? $merged->license : $source->license,
                acquireLicensePage: '' !== $merged->acquireLicensePage ? $merged->acquireLicensePage : $source->acquireLicensePage,
                creditText: '' !== $merged->creditText ? $merged->creditText : $source->creditText,
                creator: [] !== $merged->creator ? $merged->creator : $source->creator,
                copyrightNotice: '' !== $merged->copyrightNotice ? $merged->copyrightNotice : $source->copyrightNotice,
                digitalSourceType: '' !== $merged->digitalSourceType ? $merged->digitalSourceType : $source->digitalSourceType,
            );
        }

        return $merged;
    }

    /**
     * Provenance is not authorship. A generator's credit line ("AI Generated") is a
     * note about how the pixels were made, not somebody claiming the image — leaving
     * it in creditText would publish it to Google as the attribution, and would make
     * the file look third-party so the site's own licensing never gets applied.
     */
    public function stripGeneratorMarkers(): self
    {
        if (! MediaLicense::isGeneratorCredit($this->creditText)) {
            return $this;
        }

        return new self(
            license: $this->license,
            acquireLicensePage: $this->acquireLicensePage,
            creditText: '',
            creator: $this->creator,
            copyrightNotice: $this->copyrightNotice,
            digitalSourceType: '' !== $this->digitalSourceType
                ? $this->digitalSourceType
                : MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.MediaLicense::TRAINED_ALGORITHMIC_MEDIA,
        );
    }

    public function hasRightsValue(): bool
    {
        return MediaLicense::hasRightsValue($this->toCustomProperties());
    }

    /**
     * @return array<string, mixed>
     */
    public function toCustomProperties(): array
    {
        $properties = [];

        foreach ([
            MediaLicense::LICENSE => $this->license,
            MediaLicense::ACQUIRE_LICENSE_PAGE => $this->acquireLicensePage,
            MediaLicense::CREDIT_TEXT => $this->creditText,
            MediaLicense::COPYRIGHT_NOTICE => $this->copyrightNotice,
            MediaLicense::DIGITAL_SOURCE_TYPE => $this->digitalSourceType,
        ] as $key => $value) {
            if ('' !== $value) {
                $properties[$key] = $value;
            }
        }

        if ([] !== $this->creator) {
            // Bare names in, {name, type} out: nothing in a file tells a person apart
            // from an agency, so each imported creator falls back to Person. A wrong
            // @type is cosmetic, not a false claim, and it is editable per name.
            $properties[MediaLicense::CREATOR] = MediaLicense::normalizeCreators($this->creator);
        }

        return $properties;
    }
}
