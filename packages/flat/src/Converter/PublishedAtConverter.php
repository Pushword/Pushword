<?php

declare(strict_types=1);

namespace Pushword\Flat\Converter;

use DateTimeInterface;
use Pushword\Core\Service\EditorialTimezone;

final readonly class PublishedAtConverter
{
    public const string DRAFT_VALUE = 'draft';

    public function __construct(
        private EditorialTimezone $editorialTimezone,
    ) {
    }

    /**
     * Convert publishedAt value for export (to flat file).
     * Returns 'draft' if publishedAt is null, otherwise the date in the editorial timezone, with its offset.
     */
    public function toFlatValue(?DateTimeInterface $publishedAt): string
    {
        if (null === $publishedAt) {
            return self::DRAFT_VALUE;
        }

        return $this->editorialTimezone->format($publishedAt);
    }

    /**
     * Convert publishedAt value from import (from flat file).
     * Returns null if value is 'draft', otherwise returns DateTime.
     */
    public function fromFlatValue(mixed $value): ?DateTimeInterface
    {
        if (self::DRAFT_VALUE === $value) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (\is_scalar($value)) {
            return $this->editorialTimezone->parse((string) $value);
        }

        return null;
    }
}
